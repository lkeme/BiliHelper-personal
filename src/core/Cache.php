<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 *  Source: https://github.com/fzaninotto/Faker/
 */

namespace BiliHelper\Core;

use BiliHelper\Util\Singleton;
use Flintstone\Flintstone;
use Overtrue\Pinyin\Pinyin;
use Flintstone\Formatter\JsonFormatter;


class Cache
{
    use Singleton;

    private array $caches;
    private Flintstone $cache;

    // 文档
    // https://www.xeweb.net/flintstone/documentation/

    /**
     * @use 加载一个缓存
     * @param string $classname
     * @return Cache
     */
    private function load(string $classname): static
    {
        if (!isset($this->caches[$classname])) {
            $username = getConf('username', 'login.account');
            // 判断字符串中是否有中文
            if (preg_match("/[\x7f-\xff]/", $username)) {
                $pinyin = new Pinyin(); // 默认
                $username = $pinyin->permalink($username); // yong-hu-ming
            }
            // 如果不存在缓存 初始化  "BHP_username_APP.dat"
            $this->caches[$classname] = new Flintstone(
                $this->removeSpecStr('BHP_' . $username . '_' . $classname),
                [
                    'dir' => APP_CACHE_PATH,
                    'gzip' => true,
                    'formatter' => new JsonFormatter()
                ]
            );
        }
        $this->cache = $this->caches[$classname];
        return $this;
        // self::$instance->set('bob', ['email' => 'bob@site.com', 'password' => '123456']);
    }

    /**
     * @use 获取调用链类
     * @return mixed
     */
    private function backtraceClass(): mixed
    {
        // TODO 耦合度过高  需要解耦
        $backtraces = debug_backtrace();
        array_shift($backtraces);
        return pathinfo(basename($backtraces[2]['file']))['filename'];
    }

    /**
     * @use 获取调用类
     * @param string $classname
     * @return $this
     */
    private function getClassObj(string $classname): static
    {
        if ($classname == '') {
            $classname = $this->backtraceClass();
        }
        return $this->load($classname);
    }

    /**
     * @use 获取值
     * @param string $key
     * @param string $extra_name
     * @return mixed
     */
    public function _get(string $key, string $extra_name = ''): mixed
    {
        // Get a key
        // $user = $users->get('bob');
        // echo 'Bob, your email is ' . $user['email'];
        return $this->getClassObj($extra_name)->cache->get($key);
    }

    /**
     * @use 写入值
     * @param string $key
     * @param $data
     * @param string $extra_name
     */
    public function _set(string $key, $data, string $extra_name = '')
    {
        // Set a key
        // $users->set('bob', ['email' => 'bob@site.com', 'password' => '123456']);
        $this->getClassObj($extra_name)->cache->set($key, $data);
    }

    /**
     * @use 去除特殊符号
     * @param string $data
     * @return string
     */
    private function removeSpecStr(string $data): string
    {
        $specs = str_split("-.,:;'*?~`!@#$%^&+=)(<>{}]|\/、");
        foreach ($specs as $spec) {
            $data = str_replace($spec, '', $data);
        }
        return $data;
    }
}


