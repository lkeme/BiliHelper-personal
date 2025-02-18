<?php

namespace Flintstone\Cache;

class ArrayCache implements CacheInterface
{
    /**
     * Cache data.
     *
     * @var array
     */
    protected $cache = [];

    /**
     * {@inheritdoc}
     */
    public function contains($key)
    {
        return array_key_exists($key, $this->cache);
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        return $this->cache[$key];
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $data)
    {
        $this->cache[$key] = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        unset($this->cache[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $this->cache = [];
    }
}
