<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2022 ~ 2023
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Cache;

use Bhp\Util\DesignPattern\SingleTon;
use Flintstone\Flintstone;
use Flintstone\Formatter\JsonFormatter;
use Overtrue\Pinyin\Pinyin;

class Cache extends SingleTon
{
    // 文档
    //matomo-org/component-cache
    // https://www.xeweb.net/flintstone/documentation/

    /**
     * @var array|null
     */
    protected array $caches = [];

    /**
     * @var Flintstone
     */
    protected Flintstone $cache;

    /**
     * @return void
     */
    public function init(): void
    {
    }

    /**
     * @param string|null $classname
     * @return void
     */
    public static function initCache(?string $classname = null): void
    {
        $class_name = $classname ?? self::getInstance()->getCallClassName();
        //
        if (!array_key_exists($class_name, self::getInstance()->caches)) {
            // 判断字符串中是否有中文
            if (preg_match("/[\x7f-\xff]/", $class_name)) {
                $pinyin = new Pinyin(); // 默认
                $class_name = $pinyin->permalink($class_name); // yong-hu-ming
            }
            // 如果不存在缓存 初始化
            $database = self::getInstance()->removeSpecStr('cache_' . $class_name);
            $options = [
                'dir' => PROFILE_CACHE_PATH,
                'gzip' => true,
                'formatter' => new JsonFormatter()
            ];
            self::getInstance()->caches[$class_name] = new Flintstone($database, $options);
            // ->set('bob', ['email' => 'bob@site.com', 'password' => '123456']);
        }
        print_r(array_keys(self::getInstance()->caches));

    }

    /**
     * @use 写入值
     * @param string $key
     * @param mixed $value
     * @param string|null $classname
     * @return void
     */
    public static function set(string $key, mixed $value, ?string $classname = null): void
    {
        // Set a key
        // $users->set('bob', ['email' => 'bob@site.com', 'password' => '123456']);
        self::getInstance()->getCache($classname)->set($key, $value);
    }

    /**
     * @use 获取值
     * @param string $key
     * @param string|null $classname
     * @return false|mixed
     */
    public static function get(string $key, ?string $classname = null): mixed
    {
        // Get a key
        // $user = $users->get('bob');
        // echo 'Bob, your email is ' . $user['email'];
        return self::getInstance()->getCache($classname)->get($key);
    }

    /**
     * @use 强转一下类型
     * @param string|null $classname
     * @return Flintstone
     */
    protected function getCache(?string $classname = null): Flintstone
    {
        $class_name = $classname ?? $this->getCallClassName();
        if (!array_key_exists($class_name, $this->caches)) {
            failExit("当前类 $class_name 并未初始化缓存");
        }
        return $this->caches[$class_name];
    }

    /**
     * @use 获取调用者类名
     * @return string
     */
    protected function getCallClassName(): string
    {
        // basename(str_replace('\\', '/', __CLASS__));
        $backtraces = debug_backtrace();
        $temp = pathinfo(basename($backtraces[1]['file']))['filename'];
        //
        if ($temp == basename(str_replace('\\', '/', __CLASS__))) {
            return pathinfo(basename($backtraces[2]['file']))['filename'];
        } else {
            return $temp;
        }
    }

    /**
     * @use 去除特殊符号
     * @param string $str
     * @return string
     */
    protected function removeSpecStr(string $str): string
    {
        $specs = str_split("-.,:;'*?~`!@#$%^&+=)(<>{}]|\/、");
        foreach ($specs as $spec) {
            $str = str_replace($spec, '', $str);
        }
        return $str;
    }

    /**
     * @use 获取调用链类
     * @return mixed
     */
    protected function backtraceClass(): mixed
    {
        // TODO 耦合度过高  需要解耦
        $backtraces = debug_backtrace();
        array_shift($backtraces);
        return pathinfo(basename($backtraces[2]['file']))['filename'];
    }
}