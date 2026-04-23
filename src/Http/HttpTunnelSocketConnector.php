<?php declare(strict_types=1);

namespace Bhp\Http;

use Amp\ByteStream\ResourceStream;
use Amp\Cancellation;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use Amp\Socket\ConnectException;
use Amp\Socket\ResourceSocket;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Amp\Socket\SocketConnector;
use Amp\Socket\SocketException;
use Amp\Socket\TlsState;
use function Amp\async;
use function Amp\ByteStream\pipe;
use function Amp\Socket\createSocketPair;
use function Amp\Socket\socketConnector;

final class HttpTunnelSocketConnector implements SocketConnector
{
    private readonly string $proxyScheme;
    private readonly string $proxyHost;
    private readonly int $proxyPort;
    private readonly ?string $proxyUsername;
    private readonly ?string $proxyPassword;

    public function __construct(
        string $proxyUri,
        private readonly bool $verifyPeer = true,
        private readonly ?SocketConnector $delegate = null,
    ) {
        $parts = \parse_url($proxyUri);
        if (!\is_array($parts)) {
            throw new \InvalidArgumentException('network_proxy.proxy 不是有效的代理 URL');
        }

        $scheme = \strtolower((string)($parts['scheme'] ?? ''));
        if (!\in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException('network_proxy.proxy 仅支持 http:// 或 https:// 代理');
        }

        $host = (string)($parts['host'] ?? '');
        if ($host === '') {
            throw new \InvalidArgumentException('network_proxy.proxy 缺少代理主机');
        }

        $this->proxyScheme = $scheme;
        $this->proxyHost = $host;
        $this->proxyPort = (int)($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $this->proxyUsername = isset($parts['user']) ? \rawurldecode((string)$parts['user']) : null;
        $this->proxyPassword = isset($parts['pass']) ? \rawurldecode((string)$parts['pass']) : null;
    }

    #[\Override]
    public function connect(
        SocketAddress|string $uri,
        ?ConnectContext $context = null,
        ?Cancellation $cancellation = null
    ): Socket {
        $context ??= new ConnectContext();
        $connector = $this->delegate ?? socketConnector();
        $targetAuthority = $this->normalizeAuthority($uri);
        $targetTlsContext = $context->getTlsContext();
        $proxyContext = $this->createProxyConnectContext($context);

        $remoteSocket = $connector->connect(
            'tcp://' . $this->formatAuthority($this->proxyHost, $this->proxyPort),
            $proxyContext,
            $cancellation
        );

        try {
            if ($this->proxyScheme === 'https' && $remoteSocket->getTlsState() === TlsState::Disabled) {
                $remoteSocket->setupTls($cancellation);
            }

            $this->establishTunnel($remoteSocket, $targetAuthority, $cancellation);

            if ($this->proxyScheme === 'https') {
                return $this->createForwardingSocket($remoteSocket, $targetTlsContext);
            }

            if ($targetTlsContext !== null) {
                return ResourceSocket::fromClientSocket($this->extractResource($remoteSocket), $targetTlsContext);
            }

            return $remoteSocket;
        } catch (\Throwable $exception) {
            $remoteSocket->close();
            throw $exception;
        }
    }

    private function createProxyConnectContext(ConnectContext $context): ConnectContext
    {
        $proxyContext = $context->withoutTlsContext();
        if ($this->proxyScheme !== 'https') {
            return $proxyContext;
        }

        $tlsContext = new ClientTlsContext($this->proxyHost);
        if (!$this->verifyPeer) {
            $tlsContext = $tlsContext->withoutPeerVerification();
        }

        return $proxyContext->withTlsContext($tlsContext);
    }

    private function establishTunnel(Socket $socket, string $targetAuthority, ?Cancellation $cancellation): void
    {
        $headers = [
            'CONNECT ' . $targetAuthority . ' HTTP/1.1',
            'Host: ' . $targetAuthority,
            'Proxy-Connection: keep-alive',
        ];
        if ($this->proxyUsername !== null) {
            $headers[] = 'Proxy-Authorization: Basic ' . \base64_encode($this->proxyUsername . ':' . ($this->proxyPassword ?? ''));
        }

        $socket->write(\implode("\r\n", $headers) . "\r\n\r\n");
        $response = $this->readHeaders($socket, $cancellation);
        $statusLine = \preg_split("/\r\n|\n|\r/", $response, 2)[0] ?? '';

        if (!\preg_match('#^HTTP/\d+(?:\.\d+)?\s+(\d{3})(?:\s+(.*))?$#i', $statusLine, $matches)) {
            throw new SocketException('代理响应格式无效: ' . \trim($statusLine));
        }

        $statusCode = (int)$matches[1];
        if ($statusCode !== 200) {
            $reason = isset($matches[2]) ? \trim((string)$matches[2]) : '';
            $message = '代理 CONNECT 失败，状态码 ' . $statusCode;
            if ($reason !== '') {
                $message .= ' (' . $reason . ')';
            }

            throw new ConnectException($message);
        }
    }

    private function readHeaders(Socket $socket, ?Cancellation $cancellation): string
    {
        $buffer = '';

        while (\strlen($buffer) < 65536) {
            $chunk = $socket->read($cancellation, 8192);
            if ($chunk === null) {
                throw new SocketException('代理在建立 CONNECT 隧道前关闭了连接');
            }

            $buffer .= $chunk;
            if (\str_contains($buffer, "\r\n\r\n") || \str_contains($buffer, "\n\n")) {
                return $buffer;
            }
        }

        throw new SocketException('代理响应头过大，CONNECT 建立失败');
    }

    private function createForwardingSocket(Socket $remoteSocket, ?ClientTlsContext $targetTlsContext): Socket
    {
        [$proxySide, $clientSide] = createSocketPair();

        async(static function () use ($proxySide, $remoteSocket): void {
            try {
                pipe($proxySide, $remoteSocket);
            } catch (\Throwable) {
            } finally {
                $proxySide->close();
                $remoteSocket->close();
            }
        });

        async(static function () use ($remoteSocket, $proxySide): void {
            try {
                pipe($remoteSocket, $proxySide);
            } catch (\Throwable) {
            } finally {
                $remoteSocket->close();
                $proxySide->close();
            }
        });

        if ($targetTlsContext !== null) {
            return ResourceSocket::fromClientSocket($this->extractResource($clientSide), $targetTlsContext);
        }

        return $clientSide;
    }

    private function normalizeAuthority(SocketAddress|string $uri): string
    {
        if ($uri instanceof SocketAddress) {
            return $uri->toString();
        }

        $parts = \parse_url((string)$uri);
        if (!\is_array($parts)) {
            throw new \InvalidArgumentException('目标地址格式无效');
        }

        $host = (string)($parts['host'] ?? '');
        $port = (int)($parts['port'] ?? 0);
        if ($host === '' || $port <= 0) {
            throw new \InvalidArgumentException('目标地址缺少主机或端口');
        }

        return $this->formatAuthority($host, $port);
    }

    private function formatAuthority(string $host, int $port): string
    {
        if (\str_contains($host, ':') && !\str_starts_with($host, '[')) {
            return '[' . $host . ']:' . $port;
        }

        return $host . ':' . $port;
    }

    /**
     * @return resource|object
     */
    private function extractResource(Socket $socket)
    {
        if (!$socket instanceof ResourceStream) {
            throw new \RuntimeException('当前 socket 不支持提取底层资源，无法建立代理隧道');
        }

        $resource = $socket->getResource();
        if ($resource === null) {
            throw new \RuntimeException('socket 资源不可用，无法建立代理隧道');
        }

        return $resource;
    }
}
