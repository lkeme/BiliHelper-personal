<?php

namespace Flintstone;

use SplFileObject;
use SplTempFileObject;

class Database
{
    /**
     * File read flag.
     *
     * @var int
     */
    const FILE_READ = 1;

    /**
     * File write flag.
     *
     * @var int
     */
    const FILE_WRITE = 2;

    /**
     * File append flag.
     *
     * @var int
     */
    const FILE_APPEND = 3;

    /**
     * File access mode.
     *
     * @var array
     */
    protected $fileAccessMode = [
        self::FILE_READ => [
            'mode' => 'rb',
            'operation' => LOCK_SH,
        ],
        self::FILE_WRITE => [
            'mode' => 'wb',
            'operation' => LOCK_EX,
        ],
        self::FILE_APPEND => [
            'mode' => 'ab',
            'operation' => LOCK_EX,
        ],
    ];

    /**
     * Database name.
     *
     * @var string
     */
    protected $name;

    /**
     * Config class.
     *
     * @var Config
     */
    protected $config;

    /**
     * Constructor.
     *
     * @param string $name
     * @param Config|null $config
     */
    public function __construct(string $name, Config|null $config = null)
    {
        $this->setName($name);

        if ($config) {
            $this->setConfig($config);
        }
    }

    /**
     * Get the database name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the database name.
     *
     * @param string $name
     *
     * @throws Exception
     */
    public function setName(string $name)
    {
        Validation::validateDatabaseName($name);
        $this->name = $name;
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
    }

    /**
     * Get the path to the database file.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->config->getDir() . $this->getName() . $this->config->getExt();
    }

    /**
     * Open the database file.
     *
     * @param int $mode
     *
     * @throws Exception
     *
     * @return SplFileObject
     */
    protected function openFile(int $mode): SplFileObject
    {
        $path = $this->getPath();

        if (!is_file($path) && !@touch($path)) {
            throw new Exception('Could not create file: ' . $path);
        }

        if (!is_readable($path) || !is_writable($path)) {
            throw new Exception('File does not have permission for read and write: ' . $path);
        }

        if ($this->getConfig()->useGzip()) {
            $path = 'compress.zlib://' . $path;
        }

        $res = $this->fileAccessMode[$mode];
        $file = new SplFileObject($path, $res['mode']);

        if ($mode === self::FILE_READ) {
            $file->setFlags(SplFileObject::DROP_NEW_LINE | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
        }

        if (!$this->getConfig()->useGzip() && !$file->flock($res['operation'])) {
            $file = null;
            throw new Exception('Could not lock file: ' . $path);
        }

        return $file;
    }

    /**
     * Open a temporary file.
     *
     * @return SplTempFileObject
     */
    public function openTempFile(): SplTempFileObject
    {
        return new SplTempFileObject($this->getConfig()->getSwapMemoryLimit());
    }

    /**
     * Close the database file.
     *
     * @param SplFileObject $file
     *
     * @throws Exception
     */
    protected function closeFile(SplFileObject &$file)
    {
        if (!$this->getConfig()->useGzip() && !$file->flock(LOCK_UN)) {
            $file = null;
            throw new Exception('Could not unlock file');
        }

        $file = null;
    }

    /**
     * Read lines from the database file.
     *
     * @return \Generator
     */
    public function readFromFile(): \Generator
    {
        $file = $this->openFile(static::FILE_READ);

        try {
            foreach ($file as $line) {
                yield new Line($line);
            }
        } finally {
            $this->closeFile($file);
        }
    }

    /**
     * Append a line to the database file.
     *
     * @param string $line
     */
    public function appendToFile(string $line)
    {
        $file = $this->openFile(static::FILE_APPEND);
        $file->fwrite($line);
        $this->closeFile($file);
    }

    /**
     * Flush the database file.
     */
    public function flushFile()
    {
        $file = $this->openFile(static::FILE_WRITE);
        $this->closeFile($file);
    }

    /**
     * Write temporary file contents to database file.
     *
     * @param SplTempFileObject $tmpFile
     */
    public function writeTempToFile(SplTempFileObject &$tmpFile)
    {
        $file = $this->openFile(static::FILE_WRITE);

        foreach ($tmpFile as $line) {
            $file->fwrite($line);
        }

        $this->closeFile($file);
        $tmpFile = null;
    }
}
