<?php

namespace Flintstone;

class Line
{
    /**
     * @var string
     */
    protected $line;

    /**
     * @var array
     */
    protected $pieces = [];

    public function __construct(string $line)
    {
        $this->line = $line;
        $this->pieces = explode('=', $line, 2);
    }

    public function getLine(): string
    {
        return $this->line;
    }

    public function getKey(): string
    {
        return $this->pieces[0];
    }

    public function getData(): string
    {
        return $this->pieces[1];
    }
}
