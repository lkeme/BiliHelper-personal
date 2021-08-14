<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 *  Source: https://github.com/fzaninotto/Faker/
 */

namespace BiliHelper\Tool;

use Flintstone\Flintstone;
use Flintstone\Formatter\JsonFormatter;


class Cache
{
    private static $instance;


    private static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::configureInstance();
        }
        return self::$instance;
    }

    private static function configureInstance()
    {
        self::$instance = new Flintstone(
            'BHP', [
            'dir' => APP_CACHE_PATH,
            // 'gzip' => true,
            'formatter' => new JsonFormatter()
        ]);
        // self::$instance->set('bob', ['email' => 'bob@site.com', 'password' => '123456']);
    }

    public static function get()
    {
        // Get a key
        // $user = $users->get('bob');
        // echo 'Bob, your email is ' . $user['email'];
        $args = func_get_args();
        return self::getInstance()->get(...$args);
    }

    public static function set()
    {
        // Set a key
        // $users->set('bob', ['email' => 'bob@site.com', 'password' => '123456']);
        $args = func_get_args();
        self::getInstance()->set(...$args);
    }


}