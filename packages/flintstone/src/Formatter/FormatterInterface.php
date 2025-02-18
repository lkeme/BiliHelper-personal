<?php

namespace Flintstone\Formatter;

interface FormatterInterface
{
    /**
     * Encode data into a string.
     *
     * @param mixed $data
     *
     * @return string
     */
    public function encode($data): string;

    /**
     * Decode a string into data.
     *
     * @param string $data
     *
     * @return mixed
     */
    public function decode(string $data);
}
