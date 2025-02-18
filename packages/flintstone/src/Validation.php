<?php

namespace Flintstone;

class Validation
{
    /**
     * Validate the key.
     *
     * @param string $key
     *
     * @throws Exception
     */
    public static function validateKey(string $key)
    {
        if (empty($key) || !preg_match('/^[\w-]+$/', $key)) {
            throw new Exception('Invalid characters in key');
        }
    }

    /**
     * Check the database name is valid.
     *
     * @param string $name
     *
     * @throws Exception
     */
    public static function validateDatabaseName(string $name)
    {
        if (empty($name) || !preg_match('/^[\w-]+$/', $name)) {
            throw new Exception('Invalid characters in database name');
        }
    }
}
