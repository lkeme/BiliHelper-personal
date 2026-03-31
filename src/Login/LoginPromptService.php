<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Util\Exceptions\LoginException;

class LoginPromptService
{
    /**
     * @return array{mode:string,url:string}
     */
    public function resolveQrcodeDisplay(string $option, string $qr): array
    {
        return match ($option) {
            '1' => [
                'mode' => 'terminal',
                'url' => $qr,
            ],
            '2' => [
                'mode' => 'browser',
                'url' => 'https://cli.im/api/qrcode/code?text=' . urlencode($qr),
            ],
            default => throw new LoginException('无效的选项', 3600),
        };
    }

    public function prompt(string $message, int $maxChar = 100): string
    {
        $stdin = fopen('php://stdin', 'r');
        echo '# ' . $message;
        $input = fread($stdin, $maxChar);
        fclose($stdin);

        return str_replace(PHP_EOL, '', $input);
    }
}
