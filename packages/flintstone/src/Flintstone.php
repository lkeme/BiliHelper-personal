<?php

namespace Flintstone;

class Flintstone
{
    /**
     * Flintstone version.
     *
     * @var string
     */
    const VERSION = '2.3';

    /**
     * Database class.
     *
     * @var Database
     */
    protected $database;

    /**
     * Config class.
     *
     * @var Config
     */
    protected $config;

    /**
     * Constructor.
     *
     * @param Database|string $database
     * @param Config|array $config
     */
    public function __construct($database, $config)
    {
        if (is_string($database)) {
            $database = new Database($database);
        }

        if (is_array($config)) {
            $config = new Config($config);
        }

        $this->setDatabase($database);
        $this->setConfig($config);
    }

    /**
     * Get the database.
     *
     * @return Database
     */
    public function getDatabase(): Database
    {
        return $this->database;
    }

    /**
     * Set the database.
     *
     * @param Database $database
     */
    public function setDatabase(Database $database)
    {
        $this->database = $database;
    }

    /**
     * Get the config.
     *
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Set the config.
     *
     * @param Config $config
     */
    public function setConfig(Config $config)
    {
        $this->config = $config;
        $this->getDatabase()->setConfig($config);
    }

    /**
     * Get a key from the database.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function get(string $key)
    {
        Validation::validateKey($key);

        // Fetch the key from cache
        if ($cache = $this->getConfig()->getCache()) {
            if ($cache->contains($key)) {
                return $cache->get($key);
            }
        }

        // Fetch the key from database
        $file = $this->getDatabase()->readFromFile();
        $data = false;

        foreach ($file as $line) {
            /** @var Line $line */
            if ($line->getKey() == $key) {
                $data = $this->decodeData($line->getData());
                break;
            }
        }

        // Save the data to cache
        if ($cache && $data !== false) {
            $cache->set($key, $data);
        }

        return $data;
    }

    /**
     * Set a key in the database.
     *
     * @param string $key
     * @param mixed $data
     */
    public function set(string $key, $data)
    {
        Validation::validateKey($key);

        // If the key already exists we need to replace it
        if ($this->get($key) !== false) {
            $this->replace($key, $data);
            return;
        }

        // Write the key to the database
        $this->getDatabase()->appendToFile($this->getLineString($key, $data));

        // Delete the key from cache
        if ($cache = $this->getConfig()->getCache()) {
            $cache->delete($key);
        }
    }

    /**
     * Delete a key from the database.
     *
     * @param string $key
     */
    public function delete(string $key)
    {
        Validation::validateKey($key);

        if ($this->get($key) !== false) {
            $this->replace($key, false);
        }
    }

    /**
     * Flush the database.
     */
    public function flush()
    {
        $this->getDatabase()->flushFile();

        // Flush the cache
        if ($cache = $this->getConfig()->getCache()) {
            $cache->flush();
        }
    }

    /**
     * Get all keys from the database.
     *
     * @return array
     */
    public function getKeys(): array
    {
        $keys = [];
        $file = $this->getDatabase()->readFromFile();

        foreach ($file as $line) {
            /** @var Line $line */
            $keys[] = $line->getKey();
        }

        return $keys;
    }

    /**
     * Get all data from the database.
     *
     * @return array
     */
    public function getAll(): array
    {
        $data = [];
        $file = $this->getDatabase()->readFromFile();

        foreach ($file as $line) {
            /** @var Line $line */
            $data[$line->getKey()] = $this->decodeData($line->getData());
        }

        return $data;
    }

    /**
     * Replace a key in the database.
     *
     * @param string $key
     * @param mixed $data
     */
    protected function replace(string $key, $data)
    {
        // Write a new database to a temporary file
        $tmpFile = $this->getDatabase()->openTempFile();
        $file = $this->getDatabase()->readFromFile();

        foreach ($file as $line) {
            /** @var Line $line */
            if ($line->getKey() == $key) {
                if ($data !== false) {
                    $tmpFile->fwrite($this->getLineString($key, $data));
                }
            } else {
                $tmpFile->fwrite($line->getLine() . "\n");
            }
        }

        $tmpFile->rewind();

        // Overwrite the database with the temporary file
        $this->getDatabase()->writeTempToFile($tmpFile);

        // Delete the key from cache
        if ($cache = $this->getConfig()->getCache()) {
            $cache->delete($key);
        }
    }

    /**
     * Get the line string to write.
     *
     * @param string $key
     * @param mixed $data
     *
     * @return string
     */
    protected function getLineString(string $key, $data): string
    {
        return $key . '=' . $this->encodeData($data) . "\n";
    }

    /**
     * Decode a string into data.
     *
     * @param string $data
     *
     * @return mixed
     */
    protected function decodeData(string $data)
    {
        return $this->getConfig()->getFormatter()->decode($data);
    }

    /**
     * Encode data into a string.
     *
     * @param mixed $data
     *
     * @return string
     */
    protected function encodeData($data): string
    {
        return $this->getConfig()->getFormatter()->encode($data);
    }
}
