<?php declare(strict_types=1);

namespace Bhp\Login;

class LoginPromptService
{
    /**
     * 处理prompt
     * @param string $message
     * @param int $maxChar
     * @return string
     */
    public function prompt(string $message, int $maxChar = 100): string
    {
        $stdin = fopen('php://stdin', 'r');
        echo '# ' . $message;
        $input = fread($stdin, $maxChar);
        fclose($stdin);

        return str_replace(PHP_EOL, '', $input);
    }
}
