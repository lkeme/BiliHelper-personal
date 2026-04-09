<?php declare(strict_types=1);

namespace Bhp\Login;

class LoginPromptService
{
    public function prompt(string $message, int $maxChar = 100): string
    {
        $stdin = fopen('php://stdin', 'r');
        echo '# ' . $message;
        $input = fread($stdin, $maxChar);
        fclose($stdin);

        return str_replace(PHP_EOL, '', $input);
    }
}
