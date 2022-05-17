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
     * @use 真实路径
     * @param string $file
     * @return string
     */
    private function fileRealPath(string $file): string
    {
        return APP_CONF_PATH . $file;
    }

    /**
     * @use 加载配置
     */
    public function load(string $load_file): void
    {
        // 提前处理 后缀
        $custom_file = str_replace(strrchr($load_file, "."), "", $load_file) . '_';
        // 自定义客户端
        if (is_file($this->fileRealPath($custom_file . $this->bili_file))) {
            $this->bili_file = $custom_file . $this->bili_file;
            Log::info('使用自定义' . $this->bili_file);
        }
        // 自定义设备
        if (is_file($this->fileRealPath($custom_file . $this->device_file))) {
            $this->device_file = $custom_file . $this->device_file;
            Log::info('使用自定义' . $this->device_file);
        }
        // 加载数据
        $this->device = new Config();
        $loader = new YamlConfigLoader();
        $processor = new ConfigProcessor();
        $files = [$this->bili_file, $this->device_file];
        // 循环加载
        foreach ($files as $file) {
            $processor->extend($loader->load($this->fileRealPath($file)));
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