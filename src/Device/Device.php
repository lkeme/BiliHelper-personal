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

use Bhp\Util\Resource\BaseResource;

class Device extends BaseResource
{
    /**
     * @param string $filename
     * @return void
     */
    public function init(string $filename = 'device.yaml'): void
    {
        $this->loadResource($filename, 'yaml');
    }

    /**
     * 重写真实路径获取
     * @param string $filename
     * @return string
     */
    protected function getFilePath(string $filename): string
    {
        return str_replace("\\", "/", PROFILE_DEVICE_PATH . $filename);
    }

    /**
     * 重写真实路径
     * @param string $filename
     * @param string $default_filename
     * @return string
     */
//    protected function getRealFileName(string $filename, string $default_filename): string
//    {
//        $prefix = str_replace(strrchr($filename, "."), "", $filename) . '_';
//        $new_filename = $prefix . $default_filename;
//        // 自定义设备
//        if (is_file($this->getFilePath($new_filename))) {
//            Log::info('使用自定义设备参数' . $new_filename);
//            return $new_filename;
//        } else {
//            Log::info('使用默认设备参数' . $default_filename);
//            return $default_filename;
//        }
//    }
}
