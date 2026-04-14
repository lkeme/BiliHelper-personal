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

use Bhp\Profile\ProfileContext;
use Bhp\Util\Resource\Resource;
use Bhp\Util\Resource\BaseResource;
use Symfony\Component\Yaml\Yaml;

class Device extends BaseResource
{
    /**
     * 初始化 Device
     * @param ProfileContext $profileContext
     * @param string $filename
     */
    public function __construct(
        private readonly ProfileContext $profileContext,
        string $filename = 'default.yaml',
    )
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
        return str_replace("\\", "/", $this->profileContext->resourcesPath() . 'device/' . $filename);
    }

    /**
     * 处理画像OverridePath
     * @param string $filename
     * @return string
     */
    protected function profileOverridePath(string $filename): string
    {
        return str_replace("\\", "/", $this->profileContext->configPath() . $filename);
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
