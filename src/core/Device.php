<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Core;

use BiliHelper\Util\Singleton;
use Consolidation\Config\Config;
use Consolidation\Config\Loader\YamlConfigLoader;
use Consolidation\Config\Loader\ConfigProcessor;

class Device
{
    use Singleton;

    private Config $device;
    private string $bili_file = 'bili.yaml';
    private string $device_file = 'device.yaml';

    /**
     * 加载配置
     */
    public function load(string $load_file)
    {
        // 提前处理 后缀
        $custom_file = str_replace(strrchr($load_file, "."), "", $load_file) . '_';
        // 自定义客户端
        if (is_file(APP_CONF_PATH . $custom_file . $this->bili_file)) {
            $this->bili_file = APP_CONF_PATH . $custom_file . $this->bili_file;
            Log::info('使用自定义Bili.yaml');
        }
        // 自定义设备
        if (is_file(APP_CONF_PATH . $custom_file . $this->device_file)) {
            $this->device_file = APP_CONF_PATH . $custom_file . $this->device_file;
            Log::info('使用自定义Device.yaml');
        }
        // 加载数据
        $this->device = new Config();
        $loader = new YamlConfigLoader();
        $processor = new ConfigProcessor();
        $files = [$this->bili_file, $this->device_file];
        // 循环加载
        foreach ($files as $file) {
            $processor->extend($loader->load(APP_CONF_PATH . $file));
        }
        $this->device->import($processor->export());
    }

    /**
     * @use 获取值
     * @param $key
     * @param null $defaultFallback
     * @return mixed
     */
    public function _get($key, $defaultFallback = null): mixed
    {
        return $this->device->get($key, $defaultFallback);
    }


}