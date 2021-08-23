<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Plugin;

use Noodlehaus\Config;
use Noodlehaus\Parser\Json;
use BiliHelper\Core\Log;
use BiliHelper\Core\Curl;
use BiliHelper\Util\TimeLock;

class CheckUpdate
{
    use TimeLock;

    private static object $current_conf;
    private static object $latest_conf;
    private static string $repository = APP_DATA_PATH . 'latest_version.json';


    public static function run()
    {
        if (self::getLock() > time()) {
            return;
        }
        self::check();
        self::setLock(8 * 60 * 60);
    }

    /**
     * @use 检查
     */
    private static function check()
    {
        Log::info('开始检查项目更新');
        self::loadJsonData();
        Log::info('拉取线上最新配置');
        self::fetchLatest();
        if (!self::compareVersion()) {
            Log::info('项目已是最新版本');
        } else {
            Log::notice('项目有更新');
            // Todo 完善提示信息
            $time = self::$latest_conf->get('time');
            $version = self::$latest_conf->get('version');
            $des = self::$latest_conf->get('des');
            $info = "最新版本-$version, $des";
            Notice::push('update', $info);
        }
    }

    /**
     * @use 拉取最新
     */
    private static function fetchLatest()
    {
        $url = self::$current_conf->get('raw_url');
        $payload = [];
        $raw = Curl::get('other', $url, $payload);
        self::$latest_conf = Config::load($raw, new Json, true);
    }

    /**
     * @use 加载本地JSON DATA
     */
    private static function loadJsonData()
    {
        self::$current_conf = Config::load(self::$repository);
    }

    /**
     * @use 比较版本号
     * @return bool
     */
    private static function compareVersion(): bool
    {
        $current_version = self::$current_conf->get('version');
        $latest_version = self::$latest_conf->get('version');
        // true 有更新  false 无更新
        return !($current_version == $latest_version);
    }

}