<?php

use Flintstone\Config;
use Flintstone\Database;
use Flintstone\Line;

class DatabaseTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Database
     */
    private $db;

    protected function setUp(): void
    {
        $config = new Config([
            'dir' => __DIR__,
        ]);

        $this->db = new Database('test', $config);
    }

    protected function tearDown(): void
    {
        if (is_file($this->db->getPath())) {
            unlink($this->db->getPath());
        }
    }

    /**
     * @test
     */
    public function databaseHasInvalidName()
    {
        $this->expectException(\Flintstone\Exception::class);
        $config = new Config();
        new Database('test!123', $config);
    }

    /**
     * @test
     */
    public function canGetDatabaseAndConfig()
    {
        $this->assertEquals('test', $this->db->getName());
        $this->assertInstanceOf(Config::class, $this->db->getConfig());
        $this->assertEquals(__DIR__ . DIRECTORY_SEPARATOR . 'test.dat', $this->db->getPath());
    }

    /**
     * @test
     */
    public function canAppendToFile()
    {
        $this->db->appendToFile('foo=bar');
        $this->assertEquals('foo=bar', file_get_contents($this->db->getPath()));
    }

    /**
     * @test
     */
    public function canFlushFile()
    {
        $this->db->appendToFile('foo=bar');
        $this->db->flushFile();
        $this->assertEmpty(file_get_contents($this->db->getPath()));
    }

    /**
     * @test
     */
    public function canReadFromFile()
    {
        $this->db->appendToFile('foo=bar');
        $file = $this->db->readFromFile();

        foreach ($file as $line) {
            $this->assertInstanceOf(Line::class, $line);
            $this->assertEquals('foo', $line->getKey());
            $this->assertEquals('bar', $line->getData());
        }
    }

    /**
     * @test
     */
    public function canWriteTempToFile()
    {
        $tmpFile = new SplTempFileObject();
        $tmpFile->fwrite('foo=bar');
        $tmpFile->rewind();

        $this->db->writeTempToFile($tmpFile);
        $this->assertEquals('foo=bar', file_get_contents($this->db->getPath()));
    }
}
