<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Core;

use Garden\Cli\Cli;
use Garden\Cli\Args;

class Command
{

    private $argv;

    /**
     * Command constructor.
     * @param $argv
     */
    public function __construct($argv)
    {
        $this->argv = $argv;
    }

    /**
     * @return \Garden\Cli\Args
     */
    public function run(): Args
    {
        $cli = new Cli();

        $cli->description('BHP命令行工具.')
            ->opt('script:s', '执行的Script模式.', false, 'bool');

        try {
            $args = $cli->parse($this->argv, true);
        } catch (\Exception $e) {
            die('解析命令行参数错误');
        }
        return $args;
    }

}




