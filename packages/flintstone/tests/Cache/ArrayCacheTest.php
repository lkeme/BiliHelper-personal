<?php

use Flintstone\Cache\ArrayCache;

class ArrayCacheTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ArrayCache
     */
    private $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayCache();
    }

    /**
     * @test
     */
    public function canGetAndSet()
    {
        $this->cache->set('foo', 'bar');
        $this->assertTrue($this->cache->contains('foo'));
        $this->assertEquals('bar', $this->cache->get('foo'));
    }

    /**
     * @test
     */
    public function canDelete()
    {
        $this->cache->set('foo', 'bar');
        $this->cache->delete('foo');
        $this->assertFalse($this->cache->contains('foo'));
    }

    /**
     * @test
     */
    public function canFlush()
    {
        $this->cache->set('foo', 'bar');
        $this->cache->flush();
        $this->assertFalse($this->cache->contains('foo'));
    }
}
