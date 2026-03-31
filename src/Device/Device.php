<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2018 ~ 2026
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Device;

use Bhp\Util\Resource\Resource;
use Bhp\Util\Resource\BaseResource;
use Symfony\Component\Yaml\Yaml;

class Device extends BaseResource
{
    /**
     * @param string $filename
     * @return void
     */
    public function init(string $filename = 'default.yaml'): void
    {
        $filename = $filename === 'device.yaml' ? 'default.yaml' : $filename;
        $defaultPath = $this->getFilePath($filename);
        $this->validateFile($defaultPath, $filename);

        $data = $this->readYamlFile($defaultPath);
        $activePath = $defaultPath;

        $replaceOverride = $this->profileOverridePath('device.override.yaml');
        $mergeOverride = $this->profileOverridePath('device.override+.yaml');

        if (is_file($replaceOverride)) {
            $data = $this->readYamlFile($replaceOverride);
            $activePath = $replaceOverride;
        } elseif (is_file($mergeOverride)) {
            $data = array_replace_recursive($data, $this->readYamlFile($mergeOverride));
            $activePath = $mergeOverride;
        }

        $resource = new Resource();
        $resource->loadData($data);
        $this->saveInfo($filename, $activePath, 'yaml', $resource);
    }

    /**
     * 重写真实路径获取
     * @param string $filename
     * @return string
     */
    protected function getFilePath(string $filename): string
    {
        return str_replace("\\", "/", APP_RESOURCES_PATH . 'device/' . $filename);
    }

    protected function profileOverridePath(string $filename): string
    {
        return str_replace("\\", "/", PROFILE_CONFIG_PATH . $filename);
    }

    /**
     * @return array<string, mixed>
     */
    protected function readYamlFile(string $path): array
    {
        $data = Yaml::parseFile($path);

        return is_array($data) ? $data : [];
    }
}
