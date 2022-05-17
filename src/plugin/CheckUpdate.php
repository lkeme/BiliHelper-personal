<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Log;
use BiliHelper\Core\Curl;
use BiliHelper\Util\TimeLock;
use function JBZoo\Data\json;

class CheckUpdate
{
    use TimeLock;

    private static object $current_conf;
    private static object $latest_conf;
    private static string $repository = APP_DATA_PATH . 'latest_version.json';

    /**
     * @use run
     */
    public static function run(): void
    {
        if (self::getLock() > time()) {
            return;
        }
        self::check();
        self::setLock(24 * 60 * 60);
    }

    /**
     * @use 检查
     */
    private static function check(): void
    {
        Log::info('开始检查项目更新');
        self::loadJsonData();
        Log::info('拉取线上最新配置');
        self::fetchLatest();
        // 检测项目版本
        if (!self::compareVersion()) {
            Log::info('程序已是最新版本');
        } else {
            // Todo 完善提示信息
            $time = self::$latest_conf->get('time');
            $version = self::$latest_conf->get('version');
            $des = self::$latest_conf->get('des');
            $info = "请注意程序有版本更新变动哦~\n\n版本号: $version\n\n更新日志: $des\n\n更新时间: $time\n\n";
            Log::notice($info);
            Notice::push('update', $info);
        }

        // 检测配置版本
        if (!self::compareINIVersion()) {
            Log::info('配置已是最新版本');
        } else {
            $time = self::$latest_conf->get('ini_time');
            $version = self::$latest_conf->get('ini_version');
            $des = self::$latest_conf->get('ini_des');
            $info = "请注意配置有版本变动更新哦~\n\n版本号: $version\n\n更新日志: $des\n\n更新时间: $time\n\n";
            Log::notice($info);
            Notice::push('update', $info);
        }
    }

    /**
     * @use 拉取最新
     */
    private static function fetchLatest(): void
    {
        $url = self::$current_conf->get('raw_url');
        $payload = [];
        $raw = Curl::get('other', $url, $payload);
        self::$latest_conf = json(json_decode($raw, true));
    }

    /**
     * @use 加载本地JSON DATA
     */
    private static function loadJsonData(): void
    {
        self::$current_conf = json(self::$repository);
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

    /**
     * @use 比较INI版本号
     * @return bool
     */
    private static function compareINIVersion(): bool
    {
        $current_version = self::$current_conf->get('ini_version');
        $latest_version = self::$latest_conf->get('ini_version');
        // true 有更新  false 无更新
        return !($current_version == $latest_version);
    }

}