<?php

declare(strict_types=1);

namespace App\Core;

use Psr\Container\ContainerInterface;
use App\Exception\NotFoundException;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/**
 * Standard Dependency Injection Container (PSR-11)
 *
 * Manages service instantiation and lifecycle in the Flash ERP System application.
 * Supports auto-wiring for classes whose constructor parameters are all
 * resolvable from the container.
 */
class Container implements ContainerInterface
{
    /** @var Container|null Singleton instance for legacy/global accessor */
    private static ?Container $instance = null;

    /** @var array<string, mixed> Cached service instances */
    private array $instances = [];

    /** @var array<string, callable> Service definitions */
    private array $definitions = [];

    /** @var array<string, bool> Classes that allow auto-wiring */
    private array $autowireable = [];

    /**
     * Get the global singleton container instance
     */
    public static function getInstance(): Container
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Set the global singleton container instance
     */
    public static function setInstance(?Container $container): void
    {
        self::$instance = $container;
    }

    /**
     * Bind a service instance directly
     */
    public function set(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
    }

    /**
     * Register a lazy service factory definition
     */
    public function register(string $id, callable $definition): void
    {
        $this->definitions[$id] = $definition;
        unset($this->instances[$id]);
    }

    /**
     * Register a class for auto-wiring. On first access, the container will
     * inspect the constructor and recursively resolve all dependencies.
     */
    public function autowire(string $class): void
    {
        $this->autowireable[$class] = true;
        unset($this->instances[$class]);
        unset($this->definitions[$class]);
    }

    /**
     * Check if a class has been registered for auto-wiring.
     */
    public function isAutowireable(string $class): bool
    {
        return isset($this->autowireable[$class]);
    }

    /**
     * Resolve a class via auto-wiring: inspect constructor, recursively
     * resolve each parameter from the container, then instantiate.
     *
     * @throws RuntimeException when a parameter cannot be resolved
     */
    public function resolveAutowire(string $class): object
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Container: Cannot autowire '{$class}' — class does not exist.");
        }

        $refClass = new ReflectionClass($class);
        $constructor = $refClass->getConstructor();

        if ($constructor === null) {
            $instance = $refClass->newInstance();
            $this->instances[$class] = $instance;
            return $instance;
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $param) {
            $type = $param->getType();

            if ($type === null || !($type instanceof ReflectionNamedType) || $type->isBuiltin()) {
                if ($param->isDefaultValueAvailable()) {
                    $dependencies[] = $param->getDefaultValue();
                    continue;
                }
                throw new RuntimeException(
                    "Container: Cannot autowire '{$class}' — parameter \${$param->getName()} has no type hint or default value."
                );
            }

            $paramClass = $type->getName();
            $dependencies[] = $this->get($paramClass);
        }

        $instance = $refClass->newInstanceArgs($dependencies);
        $this->instances[$class] = $instance;
        return $instance;
    }

    /**
     * Get a service by ID. Falls back to auto-wiring if the ID is a
     * registered autowireable class or a resolvable class name.
     *
     * @throws RuntimeException if service cannot be resolved
     */
    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->definitions[$id])) {
            try {
                $service = $this->definitions[$id]($this);
                $this->instances[$id] = $service;
                return $service;
            } catch (\Throwable $e) {
                throw new RuntimeException(
                    "Container: Failed to resolve service '{$id}': " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        if (isset($this->autowireable[$id]) || (class_exists($id) && $this->shouldAttemptAutowire($id))) {
            return $this->resolveAutowire($id);
        }

        throw new class ("Service '{$id}' not found in the container.") extends RuntimeException implements \Psr\Container\NotFoundExceptionInterface {
        };
    }

    /**
     * Check if a class should be auto-wired on demand (not previously
     * registered but a valid, instantiable class).
     */
    private function shouldAttemptAutowire(string $class): bool
    {
        if (!class_exists($class)) {
            return false;
        }
        $refClass = new ReflectionClass($class);
        return !$refClass->isAbstract() && !$refClass->isInterface();
    }

    /**
     * Check if service is registered, bound, or autowireable
     */
    public function has(string $id): bool
    {
        if (isset($this->instances[$id]) || isset($this->definitions[$id])) {
            return true;
        }
        if (isset($this->autowireable[$id])) {
            return true;
        }
        return class_exists($id) && $this->shouldAttemptAutowire($id);
    }
}
