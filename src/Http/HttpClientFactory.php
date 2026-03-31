<?php declare(strict_types=1);

namespace Bhp\Http;

use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;

final class HttpClientFactory
{
    public static function create(bool $followRedirects = true, bool $verifyPeer = true): HttpClient
    {
        $builder = new HttpClientBuilder();
        $builder = $builder->usingPool(
            new UnlimitedConnectionPool(
                new DefaultConnectionFactory(null, self::createConnectContext($verifyPeer))
            )
        );
        if (!$followRedirects) {
            $builder = $builder->followRedirects(0);
        }

        return $builder->build();
    }

    public static function createConnectContext(bool $verifyPeer): ConnectContext
    {
        $tlsContext = new ClientTlsContext('');
        if (!$verifyPeer) {
            $tlsContext = $tlsContext->withoutPeerVerification();
        }

        return (new ConnectContext())->withTlsContext($tlsContext);
    }
}
