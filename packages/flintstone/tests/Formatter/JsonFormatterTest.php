<?php

use Flintstone\Formatter\JsonFormatter;

class JsonFormatterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var JsonFormatter
     */
    private $formatter;

    protected function setUp(): void
    {
        $this->formatter = new JsonFormatter();
    }

    /**
     * @test
     * @dataProvider validData
     */
    public function encodesValidData($originalValue, $encodedValue)
    {
        $this->assertSame($encodedValue, $this->formatter->encode($originalValue));
    }

    /**
     * @test
     * @dataProvider validData
     */
    public function decodesValidData($originalValue, $encodedValue)
    {
        $this->assertSame($originalValue, $this->formatter->decode($encodedValue));
    }

    /**
     * @test
     */
    public function decodesAnObject()
    {
        $originalValue = (object)['foo' => 'bar'];
        $formatter = new JsonFormatter(false);
        $encodedValue = $formatter->encode($originalValue);
        $this->assertEquals($originalValue, $formatter->decode($encodedValue));
    }

    /**
     * @test
     */
    public function encodingInvalidDataThrowsException()
    {
        $this->expectException(\Flintstone\Exception::class);
        $this->formatter->encode(chr(241));
    }

    /**
     * @test
     */
    public function decodingInvalidDataThrowsException()
    {
        $this->expectException(\Flintstone\Exception::class);
        $this->formatter->decode('{');
    }

    public function validData(): array
    {
        return [
            [null, 'null'],
            [1, '1'],
            ['foo', '"foo"'],
            [["test", "new\nline"], '["test","new\nline"]'],
        ];
    }
}
