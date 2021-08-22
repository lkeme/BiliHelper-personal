<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */
declare(strict_types=1);

namespace BiliHelper\Util;

use BiliHelper\Exceptions\SingletonException;

/**
 * Singleton Trait
 *
 * @author Alexander Smyslov <smyslov@selby.su>
 * @package Smysloff\Traits
 */
trait Singleton
{
    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Creates an instance of Singleton
     * and always returns same instance
     *
     * @return Singleton
     */
    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initializes the singleton
     */
    protected function init(): void
    {
    }

    /**
     * Singleton constructor.
     * Singleton constructor needs to be private
     * 不允许从外部调用以防止创建多个实例
     * 要使用单例，必须通过 Singleton::getInstance() 方法获取实例
     */
    final public function __construct()
    {
        $this->init();
    }


    /**
     * Singleton can't be cloned
     * 防止实例被克隆（这会创建实例的副本）
     */
    final public function __clone()
    {
        throw new SingletonException("Singleton can't be cloned");
    }

    /**
     * Singleton can't be serialized
     */
    final public function __sleep()
    {
        throw new SingletonException("Singleton can't be serialized");
    }

    /**
     * Singleton can't be deserialized
     * 防止反序列化（这将创建它的副本）
     */
    final public function __wakeup()
    {
        throw new SingletonException("Singleton can't be deserialized");
    }

    /**
     * 其他方法自动调用
     * @param $method
     * @param $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        return call_user_func_array([static::$instance, $method], $args);
    }

    /**
     * 静态调用
     * @param $method
     * @param $args
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        return call_user_func_array([static::$instance, $method], $args);
    }


}

