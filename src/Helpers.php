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

use Bhp\Cache\Cache;
use Bhp\Config\Config;
use Bhp\Device\Device;
use Bhp\Env\Env;
use Bhp\Log\Log;
use JetBrains\PhpStorm\NoReturn;

// --------------------------------------------------------------------------------------------------------------------
// --------------------------------------------------------------------------------------------------------------------
// --------------------------------------------------------------------------------------------------------------------

/**
 * 用户配置读取
 * @param string $key
 * @param mixed|null $default
 * @param string $type
 * @return mixed
 */
function getConf(string $key, mixed $default = null, string $type = 'default'): mixed
{
    return Config::getInstance()->get($key, $default, $type);
}

/**用户
 * 配置写入
 * @param string $key
 * @param mixed $value
 * @return void
 */
function setConf(string $key, mixed $value): void
{
    Config::getInstance()->set($key, $value);
}

/**
 * 配置开关获取(大量调用独立抽取)
 * @param string $key
 * @param bool $default
 * @return bool
 */
function getEnable(string $key, bool $default = false): bool
{
    return getConf($key . '.enable', $default, 'bool');
}

/**
 * 获取用户相关信息(Login)
 * @param string $key
 * @return mixed
 */
function getU(string $key): mixed
{
    $value = '';
    $fillable = ['username', 'password', 'uid', 'csrf', 'cookie', 'access_token', 'refresh_token', 'sid'];
    if (in_array($key, $fillable)) {
        $value = Cache::get('auth_' . $key, 'Login');
    }
    return $value ?: '';
}

/**
 * 设置用户相关信息(Login)
 * @param string $key
 * @param mixed $value
 * @return void
 */
function setU(string $key, mixed $value): void
{
    $fillable = ['username', 'password', 'uid', 'csrf', 'cookie', 'access_token', 'refresh_token', 'sid'];
    if (in_array($key, $fillable)) {
        Cache::set('auth_' . $key, $value, 'Login');
    }
}

// --------------------------------------------------------------------------------------------------------------------
// --------------------------------------------------------------------------------------------------------------------
// --------------------------------------------------------------------------------------------------------------------

/**
 * 获取APP名称
 * @return string
 */
function getAppName(): string
{
    return Env::getInstance()->app_name;
}

/**
 * 获取APP版本
 * @return string
 */
function getAppVersion(): string
{
    return Env::getInstance()->app_version;
}

/**
 * 获取APP主页
 * @return string
 */
function getAppHomePage(): string
{
    return Env::getInstance()->app_source;
}

// --------------------------------------------------------------------------------------------------------------------
// --------------------------------------------------------------------------------------------------------------------
// --------------------------------------------------------------------------------------------------------------------

/**
 * 错误退出
 * @param $message
 * @param array $context
 * @param int $delay
 * @return void
 */
#[NoReturn] function failExit($message, array $context = [], int $delay = 60 * 2): void
{
    Log::error($message, $context);
    // 如果在docker环境中，延迟退出，方便查看错误
    if (Env::isDocker()) {
        // 暂停两分钟后自动退出
        sleep($delay);
    }
    die();
}

// --------------------------------------------------------------------------------------------------------------------
// --------------------------------------------------------------------------------------------------------------------
// --------------------------------------------------------------------------------------------------------------------

/**
 * 获取设备信息
 * @param string $key
 * @param mixed|null $default
 * @param string $type
 * @return mixed
 */
function getDevice(string $key, mixed $default = null, string $type = 'default'): mixed
{
    return Device::getInstance()->get($key, $default, $type);
}

///**
// * 缓存读取
// * @param string $key
// * @param string $extra_name
// * @return mixed
// */
//function getCache(string $key, string $extra_name = ''): mixed
//{
//    return Cache::getInstance()->_get($key, $extra_name);
//}
//
///**
// * 缓存写入
// * @param string $key
// * @param $data
// * @param string $extra_name
// */
//function setCache(string $key, $data, string $extra_name = ''): void
//{
//    Cache::getInstance()->_set($key, $data, $extra_name);
//}
