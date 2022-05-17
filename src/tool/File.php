<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 *  Source: https://github.com/anhao/bv2av/
 */

namespace BiliHelper\Tool;

use JetBrains\PhpStorm\Pure;

class File
{

    /**
     * @use 创建文件
     * @param string $filename
     * @return bool
     */
    public static function create(string $filename): bool
    {
        if (file_exists($filename)) {
            return false;
        }
        // 判断文件类型是否为目录, 如果不存在则创建
        if (!file_exists(dirname($filename))) {
            mkdir(dirname($filename), 0777, true);
        }
        if (touch($filename)) {
            return true;
        }
        return false;
    }


    /**
     * @use 删除文件
     * @param string $filename
     * @return bool
     */
    public static function del(string $filename): bool
    {
        // 如果文件不存在或者权限不够, 则返回false
        if (!file_exists($filename) || !is_writeable($filename)) {
            return false;
        }
        if (unlink($filename)) {
            return true;
        }
        return false;
    }


    /**
     * @use 拷贝文件
     * @param string $filename
     * @param string $dest
     * @return bool
     */
    public static function copy(string $filename, string $dest): bool
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0777, true);
        }
        // DIRECTORY_SEPARATOR '/'分割符
        $destName = $dest . DIRECTORY_SEPARATOR . basename($filename);
        if (copy($filename, $destName)) {
            return true;
        }
        return false;
    }


    /**
     * @use 文件重命名
     * @param string $oldname
     * @param string $newname
     * @return bool
     */
    public static function rename(string $oldname, string $newname): bool
    {

        if (!is_file($oldname)) {

            return false;

        }

        $path = dirname($oldname);

        $destName = $path . DIRECTORY_SEPARATOR . $newname;

        if (!is_file($destName)) {

            return rename($oldname, $newname);

        }

        return false;

    }


    /**
     * @use 剪切文件
     * @param $filename
     * @param $dest
     * @return bool
     */
    public static function cut($filename, $dest): bool
    {
        if (!file_exists($filename)) {
            return false;
        }
        // 检测文件目录是否存在, 如果不存在则创建
        if (!is_dir($dest)) {
            mkdir($dest, 0777, true);
        }
        // 检测文件夹是否包含同名文件
        $destName = $dest . DIRECTORY_SEPARATOR . basename($filename);
        // 如果是一个文档则returnfalse 如果不是文档则剪切文档
        if (!is_file($destName)) {
            return rename($filename, $destName);
        }
        return false;
    }


    /**
     * @use 获取文件详细信息
     * @param string $filename
     * @return array|bool
     */
    public static function getInfo(string $filename): array|bool
    {
        // 如果不是文件 或者 不可读返回false
        if (!is_file($filename) || !is_readable($filename)) {
            return false;
        }
        // 否则直接返回文件信息, 定义一个关联数组
        return [
            "文件名称" => basename($filename),
            "文件类型" => filetype($filename),
            "文件大小" => static::transByte(filesize($filename)),
            "创建时间" => date('Y-m-d H:i:s', filectime($filename)),
            "修改时间" => date('Y-m-d H:i:s', filemtime($filename)),
            "上一次访问时间" => date('Y-m-d H:i:s', fileatime($filename)),
        ];
    }


    /**
     * @use 转换字节大小
     * @param int $byte 字节大小
     * @param int $precision 小数点保留位数
     * @return string 转换后的单位
     */
    public static function transByte(int $byte, int $precision = 2): string
    {
        $kb = 1024;
        $mb = 1024 * $kb;
        $gb = 1024 * $mb;
        $tb = 1024 * $gb;

        if ($byte < $kb) {
            return $byte . 'B';
        }

        if ($byte < $mb) {
            // 默认四舍五入, 保留两位小数
            return round($byte / $kb, $precision) . ' KB';
        }

        if ($byte < $gb) {
            return round($byte / $mb, $precision) . ' MB';
        }

        if ($byte < $tb) {
            return round($byte / $tb, $precision) . ' GB';
        }
        return '';
    }


    /**
     * @use 以字符串形式读取内容
     * @param string $filename
     * @return false|string
     */
    public static function readString(string $filename): bool|string
    {
        if (is_file($filename) && is_readable($filename)) {
            return file_get_contents($filename);
        }
        return false;
    }


    /**
     * @use 以数组形式读取内容
     * @param string $filename
     * @param bool $skip_empty_lines
     * @return array|false
     */
    public static function readArray(string $filename, bool $skip_empty_lines = false): bool|array
    {
        if (is_file($filename) && is_readable($filename)) {
            if ($skip_empty_lines) {
                // 忽略空行读取
                return file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            } else {
                // 以数组形式直接读取, 不忽略空行
                return file($filename);
            }
        }
        return false;
    }


    /**
     * @use 增加文件内容升级版
     * @param string $filename 路径名称
     * @param mixed $data 需要写入的数据
     * @param boolean $clear_content 是否清空原始内容再写入
     * @return bool                 true|false
     */
    public static function write(string $filename, mixed $data, bool $clear_content = false): bool
    {
        $srcData = '';
        $dirname = dirname($filename);
        // 检测目标路径是否存在
        if (!file_exists($dirname)) {
            mkdir($dirname, 0777, true);
        }
        // 文件存在并且不清空原始文件
        if (is_file($filename) && !$clear_content) {
            $srcData = file_get_contents($filename);
        }

        // 检测数据是否为数组或者对象
        if (is_array($data) || is_object($data)) {
            // 序列化数据
            $data = serialize($data);
        }
        // 拼装数据
        $data = $srcData . $data;
        // 写入数据
        if (file_put_contents($filename, $data) !== false) {
            return true;
        }
        return false;
    }

    /**
     * @use 截断文本
     * @param string $filename 文件名称
     * @param int $length 截断文本长度
     * @return boolean           true|false
     */
    public static function truncate(string $filename, int $length): bool
    {
        // 判断文件是否存在并且是可写的
        if (is_file($filename) && is_writeable($filename)) {
            // 创建文件句柄, 以读写方式打开
            $handler = fopen($filename, 'rb+');
            $length = max($length, 0);
            ftruncate($handler, $length);
            fclose($handler);
        }
        return false;
    }

}
