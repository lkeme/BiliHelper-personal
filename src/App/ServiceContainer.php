<?php declare(strict_types=1);

namespace Bhp\App;

use InvalidArgumentException;

final class ServiceContainer
{
    /**
     * @var array<string, callable(self): mixed>
     */
    private array $factories = [];

    /**
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * 处理设置
     * @param string $id
     * @param callable $factory
     * @return void
     */
    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    /**
     * 设置Instance
     * @param string $id
     * @param mixed $instance
     * @return void
     */
    public function setInstance(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
        unset($this->factories[$id]);
    }

    /**
     * 处理has
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->instances) || array_key_exists($id, $this->factories);
    }

    /**
     * 处理get
     * @param string $id
     * @return mixed
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!array_key_exists($id, $this->factories)) {
            throw new InvalidArgumentException(sprintf('Service "%s" is not registered.', $id));
        }

        return $this->instances[$id] = ($this->factories[$id])($this);
    }
}
