<?php

use Flintstone\Line;

class LineTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Line
     */
    private $line;

    protected function setUp(): void
    {
        $this->line = new Line('foo=bar');
    }

    /**
     * @test
     */
    public function canGetLine()
    {
        $this->assertEquals('foo=bar', $this->line->getLine());
    }

    /**
     * @test
     */
    public function canGetKey()
    {
        $this->assertEquals('foo', $this->line->getKey());
    }

    /**
     * @test
     */
    public function canGetData()
    {
        $this->assertEquals('bar', $this->line->getData());
    }

    /**
     * @test
     */
    public function canGetKeyAndDataWithMultipleEquals()
    {
        $line = new Line('foo=bar=baz');
        $this->assertEquals('foo', $line->getKey());
        $this->assertEquals('bar=baz', $line->getData());
    }
}
