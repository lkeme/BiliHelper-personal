<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

use BiliHelper\Core\Config;

/**
 * @use 读取
 * @param $name
 * @param int $section
 * @param null $key
 * @return mixed
 */
function getConf($name, $section = 0, $key = null)
{
    return Config::_get($name, $section, $key);
}

/**
 * @use 写入
 * @param $name
 * @param $value
 * @param int $section
 * @param null $key
 */
function setConf($name, $value, $section = 0, $key = null)
{
    Config::_set($name, $value, $section, $key);
}

/**
 * @use 更新
 */
function putConf()
{

}

/**
 * @use 删除
 */
function delConf()
{

}

/**
 * @use 开关
 * @param string $plugin
 * @return bool
 */
function getEnable(string $plugin): bool
{
    return getConf('enable', $plugin);
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