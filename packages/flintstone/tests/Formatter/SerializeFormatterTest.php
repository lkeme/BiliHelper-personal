<?php

use Flintstone\Formatter\SerializeFormatter;

class SerializeFormatterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var SerializeFormatter
     */
    private $formatter;

    protected function setUp(): void
    {
        $this->formatter = new SerializeFormatter();
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

    public function validData(): array
    {
        return [
            [null, 'N;'],
            [1, 'i:1;'],
            ['foo', 's:3:"foo";'],
            [["test", "new\nline"], 'a:2:{i:0;s:4:"test";i:1;s:9:"new\nline";}'],
        ];
    }
}
