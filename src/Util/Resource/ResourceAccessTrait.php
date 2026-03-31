<?php declare(strict_types=1);

namespace Bhp\Util\Resource;

use Bhp\Util\Exceptions\BootstrapException;

trait ResourceAccessTrait
{
    /**
     * 配置对象变量
     * @var Resource|null
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
     * 最后修改时间
     * @var int
     */
    protected int $lastModifiedTime = 0;

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
        $this->reloadWhenFileChanged();

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
        $filepath = $this->getFilePath($filename);
        $this->validateFile($filepath, $filename);
        $resource = (new Resource())->loadF($filepath, $parser);
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
            throw new BootstrapException("资源文件 {$filename} 加载失败，请参照文档查看或添加资源文件！");
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
        $this->filepath = $filepath;
        $this->resource = $resource;
        $this->parser = $parser;
        $this->updateLastModifiedTime();
    }

    /**
     * @return void
     */
    protected function reloadWhenFileChanged(): void
    {
        if ($this->filepath === '' || $this->filename === '' || $this->parser === '') {
            return;
        }

        $currentModifiedTime = $this->getCurrentFileMTime($this->filepath);
        if ($currentModifiedTime === null || $currentModifiedTime === $this->lastModifiedTime) {
            return;
        }

        $this->loadResource($this->filename, $this->parser);
    }

    /**
     * 更新最新修改时间
     * @return void
     */
    protected function updateLastModifiedTime(): void
    {
        $this->lastModifiedTime = $this->getCurrentFileMTime($this->filepath) ?? 0;
    }

    /**
     * @param string $filepath
     * @return int|null
     */
    protected function getCurrentFileMTime(string $filepath): ?int
    {
        if (!is_file($filepath)) {
            return null;
        }

        $mtime = filemtime($filepath);

        return $mtime === false ? null : $mtime;
    }

    /**
     * 获取文件真实路径
     * @param string $filename
     * @return string
     */
    abstract protected function getFilePath(string $filename): string;
}
