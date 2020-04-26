<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2020 ~ 2021
 *  Source: https://github.com/NeverBehave/BilibiliHelper
 */

class ConfigGenerator
{
    public $filename;
    public $template;
    private $options = ['APP_USER', 'APP_PASS'];
    private $default_filename = 'user.conf.example';

    /**
     * ConfigGenerator constructor.
     */
    public function __construct()
    {
        $this->cliInput('请注意生成程序只会填写基础配置，Enter继续: ');
    }

    /**
     * @param string $key
     * @param string $value
     * @param string $content
     * @return string|string[]|null
     */
    private function envReplace(string $key, string $value, string $content)
    {
        return preg_replace(
            '/^' . $key . '=.*' . '/m',
            $key . '=' . $value,
            $content
        );
    }

    /**
     * @param string $msg
     * @param int $max_char
     * @return string
     */
    private function cliInput(string $msg, $max_char = 100): string
    {
        $stdin = fopen('php://stdin', 'r');
        echo '# ' . $msg;
        $input = fread($stdin, $max_char);
        fclose($stdin);
        return str_replace(PHP_EOL, '', $input);
    }

    /**
     * @use Generator
     */
    public function generate()
    {
        $this->filename = $this->cliInput('请输入配置文件名: ');
        $this->template = file_get_contents($this->default_filename);
        foreach ($this->options as $index => $option) {
            $value = $this->cliInput("请输入{$option}: ");
            $this->template = $this->envReplace($option, $value, $this->template);
        }
        file_put_contents(__DIR__ . "\\$this->filename.conf", $this->template);
        echo "生成配置文件 $this->filename.conf 成功~";
    }

}

(new ConfigGenerator())->generate();