<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Script;

use Ahc\Cli\IO\Interactor;

class BaseTask
{
    public static $interactor = null;

    public static function init(): void
    {
        User::login();
    }

    /**
     * @use 选择
     * @param array $options
     * @param null $default
     * @return mixed
     */
    public static function choice(array $options, $default = null): mixed
    {
        $option = static::interactor()->choice('Select', $options, $default, true);
        static::interactor()->greenBold("You selected: $options[$option]", true);
        // return $options[$option];
        return $option;
    }

    /**
     * @use 确认
     * @param string $msg
     * @param string $default
     * @return bool
     */
    public static function confirm(string $msg, string $default = 'n'): bool
    {
        $confirm = static::interactor()->confirm($msg, $default); // Default: n (no)
        if (!$confirm) die();
//        if ($confirm) { // is a boolean
//            static::interactor()->greenBold('是 :)', true); // Output green bold text
//        } else {
//            static::interactor()->redBold('否 :(', true);     // Output red bold text
//            die();
//        }
        return true;
    }

    /**
     * @return Interactor
     */
    public static function interactor(): Interactor
    {
        return static::$interactor ?? static::$interactor = new Interactor;
    }
}