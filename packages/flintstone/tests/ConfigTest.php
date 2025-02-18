<?php

use Flintstone\Cache\ArrayCache;
use Flintstone\Config;
use Flintstone\Formatter\JsonFormatter;
use Flintstone\Formatter\SerializeFormatter;

class ConfigTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @test
     */
    public function defaultConfigIsSet()
    {
        $config = new Config();
        $this->assertEquals(getcwd().DIRECTORY_SEPARATOR, $config->getDir());
        $this->assertEquals('.dat', $config->getExt());
        $this->assertFalse($config->useGzip());
        $this->assertInstanceOf(ArrayCache::class, $config->getCache());
        $this->assertInstanceOf(SerializeFormatter::class, $config->getFormatter());
        $this->assertEquals(2097152, $config->getSwapMemoryLimit());
    }

    /**
     * @test
     */
    public function constructorConfigOverride()
    {
        $config = new Config([
            'dir' => __DIR__,
            'ext' => 'test',
            'gzip' => true,
            'cache' => false,
            'formatter' => null,
            'swap_memory_limit' => 100,
        ]);

        $this->assertEquals(__DIR__.DIRECTORY_SEPARATOR, $config->getDir());
        $this->assertEquals('.test.gz', $config->getExt());
        $this->assertTrue($config->useGzip());
        $this->assertFalse($config->getCache());
        $this->assertInstanceOf(SerializeFormatter::class, $config->getFormatter());
        $this->assertEquals(100, $config->getSwapMemoryLimit());
    }

    /**
     * @test
     */
    public function setValidFormatter()
    {
        $config = new Config();
        $config->setFormatter(new JsonFormatter());
        $this->assertInstanceOf(JsonFormatter::class, $config->getFormatter());
    }

    /**
     * @test
     */
    public function setInvalidFormatter()
    {
        $this->expectException(\Flintstone\Exception::class);
        $config = new Config();
        $config->setFormatter(new self());
    }

    /**
     * @test
     */
    public function invalidDirSet()
    {
        $this->expectException(\Flintstone\Exception::class);
        $config = new Config();
        $config->setDir('/x/y/z/foo');
    }

    /**
     * @test
     */
    public function invalidCacheSet()
    {
        $this->expectException(\Flintstone\Exception::class);
        $config = new Config();
        $config->setCache(new self());
    }
}
