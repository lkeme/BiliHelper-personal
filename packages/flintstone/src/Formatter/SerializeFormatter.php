<?php

namespace Flintstone\Formatter;

class SerializeFormatter implements FormatterInterface
{
    /**
     * {@inheritdoc}
     */
    public function encode($data): string
    {
        return serialize($this->preserveLines($data, false));
    }

    /**
     * {@inheritdoc}
     */
    public function decode(string $data)
    {
        return $this->preserveLines(unserialize($data), true);
    }

    /**
     * Preserve new lines, recursive function.
     *
     * @param mixed $data
     * @param bool $reverse
     *
     * @return mixed
     */
    protected function preserveLines($data, bool $reverse)
    {
        $search = ["\n", "\r"];
        $replace = ['\\n', '\\r'];

        if ($reverse) {
            $search = ['\\n', '\\r'];
            $replace = ["\n", "\r"];
        }

        if (is_string($data)) {
            $data = str_replace($search, $replace, $data);
        } elseif (is_array($data)) {
            foreach ($data as &$value) {
                $value = $this->preserveLines($value, $reverse);
            }
            unset($value);
        }

        return $data;
    }
}
