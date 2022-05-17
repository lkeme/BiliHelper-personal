<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

use BiliHelper\Core\Cache;
use BiliHelper\Core\Config;
use BiliHelper\Core\Device;
use Jelix\IniFile\IniException;

/**
 * @use 配置读取
 * @param $name
 * @param int|string $section
 * @param null $key
 * @return mixed
 */
function getConf($name, int|string $section = 0, $key = null): mixed
{
    return Config::getInstance()->_get($name, $section, $key);
}

/**
 * @use 配置写入
 * @param $name
 * @param $value
 * @param int|string $section
 * @param null $key
 * @throws IniException
 */
function setConf($name, $value, int|string $section = 0, $key = null): void
{
    Config::getInstance()->_set($name, $value, $section, $key);
}

/**
 * @use 开关
 * @param string $plugin
 * @param bool $default
 * @return bool
 */
function getEnable(string $plugin, bool $default = false): bool
{
    return getConf('enable', $plugin) ?: $default;
}

/**
 * @use 获取ACCESS_TOKEN
 * @return string
 */
function getAccessToken(): string
{
    return getConf('access_token', 'login.auth');
}

/**
 * @use 获取REFRESH_TOKEN
 * @return string
 */
function getRefreshToken(): string
{
    return getConf('refresh_token', 'login.auth');
}

/**
 * @use 获取COOKIE
 * @return string
 */
function getCookie(): string
{
    return getConf('cookie', 'login.auth');
}

/**
 * @use 获取UID
 * @return int
 */
function getUid(): int
{
    return getConf('uid', 'login.auth');
}

/**
 * @use 获取CSRF
 * @return string
 */
function getCsrf(): string
{
    return getConf('csrf', 'login.auth');
}

/**
 * @use 获取设备信息
 * @param $key
 * @param null $defaultFallback
 * @return mixed
 */
function getDevice($key, $defaultFallback = null): mixed
{
    return Device::getInstance()->_get($key, $defaultFallback);
}

/**
 * @use 缓存读取
 * @param string $key
 * @param string $extra_name
 * @return mixed
 */
function getCache(string $key, string $extra_name = ''): mixed
{
    return Cache::getInstance()->_get($key, $extra_name);
}

/**
 * @use 缓存写入
 * @param string $key
 * @param $data
 * @param string $extra_name
 */
function setCache(string $key, $data, string $extra_name = ''): void
{
    Cache::getInstance()->_set($key, $data, $extra_name);
}
