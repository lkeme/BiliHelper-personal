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

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function setInstance(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
        unset($this->factories[$id]);
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->instances) || array_key_exists($id, $this->factories);
    }

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
