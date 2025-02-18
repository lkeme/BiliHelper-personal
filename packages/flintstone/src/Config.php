<?php

namespace Flintstone;

use Flintstone\Cache\ArrayCache;
use Flintstone\Cache\CacheInterface;
use Flintstone\Formatter\FormatterInterface;
use Flintstone\Formatter\SerializeFormatter;

class Config
{
    /**
     * Config.
     *
     * @var array
     */
    protected $config = [];

    /**
     * Constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $config = $this->normalizeConfig($config);
        $this->setDir($config['dir']);
        $this->setExt($config['ext']);
        $this->setGzip($config['gzip']);
        $this->setCache($config['cache']);
        $this->setFormatter($config['formatter']);
        $this->setSwapMemoryLimit($config['swap_memory_limit']);
    }

    /**
     * Normalize the user supplied config.
     *
     * @param array $config
     *
     * @return array
     */
    protected function normalizeConfig(array $config): array
    {
        $defaultConfig = [
            'dir' => getcwd(),
            'ext' => '.dat',
            'gzip' => false,
            'cache' => true,
            'formatter' => null,
            'swap_memory_limit' => 2097152, // 2MB
        ];

        return array_replace($defaultConfig, $config);
    }

    /**
     * Get the dir.
     *
     * @return string
     */
    public function getDir(): string
    {
        return $this->config['dir'];
    }

    /**
     * Set the dir.
     *
     * @param string $dir
     *
     * @throws Exception
     */
    public function setDir(string $dir)
    {
        if (!is_dir($dir)) {
            throw new Exception('Directory does not exist: ' . $dir);
        }

        $this->config['dir'] = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Get the ext.
     *
     * @return string
     */
    public function getExt(): string
    {
        if ($this->useGzip()) {
            return $this->config['ext'] . '.gz';
        }

        return $this->config['ext'];
    }

    /**
     * Set the ext.
     *
     * @param string $ext
     */
    public function setExt(string $ext)
    {
        if (substr($ext, 0, 1) !== '.') {
            $ext = '.' . $ext;
        }

        $this->config['ext'] = $ext;
    }

    /**
     * Use gzip?
     *
     * @return bool
     */
    public function useGzip(): bool
    {
        return $this->config['gzip'];
    }

    /**
     * Set gzip.
     *
     * @param bool $gzip
     */
    public function setGzip(bool $gzip)
    {
        $this->config['gzip'] = $gzip;
    }

    /**
     * Get the cache.
     *
     * @return CacheInterface|false
     */
    public function getCache()
    {
        return $this->config['cache'];
    }

    /**
     * Set the cache.
     *
     * @param mixed $cache
     *
     * @throws Exception
     */
    public function setCache($cache)
    {
        if (!is_bool($cache) && !$cache instanceof CacheInterface) {
            throw new Exception('Cache must be a boolean or an instance of Flintstone\Cache\CacheInterface');
        }

        if ($cache === true) {
            $cache = new ArrayCache();
        }

        $this->config['cache'] = $cache;
    }

    /**
     * Get the formatter.
     *
     * @return FormatterInterface
     */
    public function getFormatter(): FormatterInterface
    {
        return $this->config['formatter'];
    }

    /**
     * Set the formatter.
     *
     * @param FormatterInterface|null $formatter
     *
     * @throws Exception
     */
    public function setFormatter($formatter)
    {
        if ($formatter === null) {
            $formatter = new SerializeFormatter();
        }

        if (!$formatter instanceof FormatterInterface) {
            throw new Exception('Formatter must be an instance of Flintstone\Formatter\FormatterInterface');
        }

        $this->config['formatter'] = $formatter;
    }

    /**
     * Get the swap memory limit.
     *
     * @return int
     */
    public function getSwapMemoryLimit(): int
    {
        return $this->config['swap_memory_limit'];
    }

    /**
     * Set the swap memory limit.
     *
     * @param int $limit
     */
    public function setSwapMemoryLimit(int $limit)
    {
        $this->config['swap_memory_limit'] = $limit;
    }
}
