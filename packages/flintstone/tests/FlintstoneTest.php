<?php

use Flintstone\Config;
use Flintstone\Database;
use Flintstone\Flintstone;
use Flintstone\Formatter\JsonFormatter;

class FlintstoneTest extends \PHPUnit\Framework\TestCase
{
    public function testGetDatabaseAndConfig()
    {
        $db = new Flintstone('test', [
            'dir' => __DIR__,
            'cache' => false,
        ]);

        $this->assertInstanceOf(Database::class, $db->getDatabase());
        $this->assertInstanceOf(Config::class, $db->getConfig());
    }

    /**
     * @test
     */
    public function keyHasInvalidName()
    {
        $this->expectException(\Flintstone\Exception::class);
        $db = new Flintstone('test', []);
        $db->get('test!123');
    }

    /**
     * @test
     */
    public function canRunAllOperations()
    {
        $this->runOperationsTests([
            'dir' => __DIR__,
            'cache' => false,
            'gzip' => false,
        ]);

        $this->runOperationsTests([
            'dir' => __DIR__,
            'cache' => true,
            'gzip' => true,
        ]);

        $this->runOperationsTests([
            'dir' => __DIR__,
            'cache' => false,
            'gzip' => false,
            'formatter' => new JsonFormatter(),
        ]);
    }

    private function runOperationsTests(array $config)
    {
        $db = new Flintstone('test', $config);
        $arr = ['foo' => "new\nline"];

        $this->assertFalse($db->get('foo'));

        $db->set('foo', 1);
        $db->set('name', 'john');
        $db->set('arr', $arr);
        $this->assertEquals(1, $db->get('foo'));
        $this->assertEquals('john', $db->get('name'));
        $this->assertEquals($arr, $db->get('arr'));

        $db->set('foo', 2);
        $this->assertEquals(2, $db->get('foo'));
        $this->assertEquals('john', $db->get('name'));
        $this->assertEquals($arr, $db->get('arr'));

        $db->delete('name');
        $this->assertFalse($db->get('name'));
        $this->assertEquals($arr, $db->get('arr'));

        $keys = $db->getKeys();
        $this->assertEquals(2, count($keys));
        $this->assertEquals('foo', $keys[0]);
        $this->assertEquals('arr', $keys[1]);

        $data = $db->getAll();
        $this->assertEquals(2, count($data));
        $this->assertEquals(2, $data['foo']);
        $this->assertEquals($arr, $data['arr']);

        $db->flush();
        $this->assertFalse($db->get('foo'));
        $this->assertFalse($db->get('arr'));
        $this->assertEquals(0, count($db->getKeys()));
        $this->assertEquals(0, count($db->getAll()));

        unlink($db->getDatabase()->getPath());
    }
}
