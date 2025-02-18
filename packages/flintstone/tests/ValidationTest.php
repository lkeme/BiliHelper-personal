<?php

use Flintstone\Validation;

class ValidationTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @test
     */
    public function validateKey()
    {
        $this->expectException(\Flintstone\Exception::class);
        Validation::validateKey('test!123');
    }

    /**
     * @test
     */
    public function validateDatabaseName()
    {
        $this->expectException(\Flintstone\Exception::class);
        Validation::validateDatabaseName('test!123');
    }
}
