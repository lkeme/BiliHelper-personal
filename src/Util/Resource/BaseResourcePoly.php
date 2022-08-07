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

namespace Bhp\Util\Resource;

use Bhp\Util\Resource\Resource as EResource;

abstract class BaseResourcePoly
{
    /**
     * 配置对象变量
     * @var EResource
     */
    protected ?Resource $resource = null;

    /**
     * 配置文件名
     * @var string
     */
    protected string $filename = '';

    /**
     * 配置文件路径
     * @var string
     */
    protected string $filepath = '';

    /**
     * 最后访问文件时间
     * @var int
     */
    protected int $last_access = 0;

    /**
     * 解析器
     * @var string
     */
    protected string $parser = '';

    /**
     * 设置值
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        // 设置
        $this->resource = $this->resource->set($key, $value);
    }

    /**
     * 根据类型获取值
     * @param string $key
     * @param mixed|null $default
     * @param string $type
     * @return mixed
     */
    public function get(string $key, mixed $default = null, string $type = 'default'): mixed
    {
        // 判断是否被修改，否则重新加载文件
        if (fileatime($this->filepath) != $this->last_access) {
            // TODO 此处逻辑好像重复了
            $this->loadResource($this->filename, $this->parser);
        }
        return match ($type) {
            'int' => $this->resource->getInt($key, $default),
            'string' => $this->resource->getString($key, $default),
            'bool' => $this->resource->getBool($key, $default),
            'array' => $this->resource->getArray($key, $default),
            default => $this->resource->get($key, $default),
        };
    }

    /**
     * 加载资源文件
     * @param string $filename
     * @param string $parser
     * @return void
     */
    protected function loadResource(string $filename, string $parser): void
    {
        // 获取
        $filepath = $this->getFilePath($filename);
        // 验证
        $this->validateFile($filepath, $filename);
        // 加载
        $resource = (new Resource())->loadF($filepath, $parser);
        // 赋值
        $this->saveInfo($filename, $filepath, $parser, $resource);
    }

    /**
     * 检查文件是否存在
     * @param string $filepath
     * @param string $filename
     * @return void
     */
    protected function validateFile(string $filepath, string $filename): void
    {
        if (!is_file($filepath)) {
            die("资源文件 $filename 加载失败，请参照文档查看或添加资源文件！");
        }
    }

    /**
     * @param string $filename
     * @param string $filepath
     * @param string $parser
     * @param Resource $resource
     * @return void
     */
    protected function saveInfo(string $filename, string $filepath, string $parser, Resource $resource): void
    {
        $this->filename = $filename;
        // dirname($conf_filepath).DIRECTORY_SEPARATOR.$conf_filename;
        $this->filepath = $filepath;
        $this->resource = $resource;
        $this->parser = $parser;
        $this->updateLastAccess();
    }

    /**
     * 更新最新修改时间
     * @return void
     */
    protected function updateLastAccess(): void
    {
        $this->last_access = fileatime($this->filepath);
    }

    /**
     * 获取文件真实路径
     * @param string $filename
     * @return string
     */
    abstract protected function getFilePath(string $filename): string;

}