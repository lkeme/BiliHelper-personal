<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2023 ~ 2024
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Util\DesignPattern;

class SingleTon
{

    /**
     * 创建静态私有的变量保存该类对象
     * @var array|null
     */
    private static ?array $_instances = [];

    /**
     * SingleTon constructor. 私有的构造方法|防止使用new直接创建对象
     */
    private function __construct()
    {
    }

    /**
     * 创建__clone方法防止对象被复制克隆
     * @return void
     */
    private function __clone(): void
    {
    }

    /**
     * 放置反序列化
     * @return void
     */
    public function __wakeup(): void
    {
    }

    /**
     * @param string $className
     * @param bool $overwrite
     * @return void
     */
    protected static function addInstance(string $className, bool $overwrite = false): void
    {
        if (isset(self::$_instances[$className]) && !$overwrite) {
            throw new \InvalidArgumentException($className);
        }

        if (!class_exists($className)) {
            throw new \InvalidArgumentException($className);
        }

        $instance = new $className();

        self::$_instances[$className] = $instance;
    }


    /**
     * @param mixed ...$params
     * @return static
     */
    public static function getInstance(mixed...$params): self
    {
        $className = static::class;
        if (!isset(self::$_instances[$className])) {
            self::addInstance($className);
            // test
            if (is_callable([self::$_instances[$className], 'init'])) {
                self::$_instances[$className]->init(...$params);
            }
        }
        return self::$_instances[$className];
    }
}
