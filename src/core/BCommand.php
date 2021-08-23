<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Core;

use Ahc\Cli\Input\Command;
use Exception;

class BCommand
{

    private array $argv;

    /**
     * Command constructor.
     * @param $argv
     */
    public function __construct($argv)
    {
        $this->argv = $argv;
    }

    public function run()
    {
        $cli = new Command('BHP-S', 'BHP命令行工具.');
        $cli->version('0.0.1-dev')
            ->option('-s --script', '执行的Script模式.', null, false)
            ->option('-r --restore', '任务排程复位(暂定).', null, false);
        try {
            $args = $cli->parse($this->argv);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            die('解析命令行参数错误');
        }
        return $args;
    }

}
