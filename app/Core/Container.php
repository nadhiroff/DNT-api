<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Conteneur d'injection de dépendances : liaisons explicites (factories), singletons
 * et auto-câblage des classes concrètes par réflexion sur le constructeur.
 */
final class Container
{
    /** @var array<string, Closure(Container): mixed> */
    private array $factories = [];

    /** @var array<string, bool> */
    private array $shared = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, true> */
    private array $resolving = [];

    /**
     * @param Closure(Container): mixed $factory
     */
    public function bind(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        $this->shared[$id] = false;
        unset($this->instances[$id]);
    }

    /**
     * @param Closure(Container): mixed $factory
     */
    public function singleton(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        $this->shared[$id] = true;
        unset($this->instances[$id]);
    }

    public function instance(string $id, mixed $value): void
    {
        $this->instances[$id] = $value;
    }

    /**
     * Vrai si une instance a déjà été enregistrée ou construite (sans rien construire).
     */
    public function hasInstance(string $id): bool
    {
        return array_key_exists($id, $this->instances);
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]) || class_exists($id);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    public function get(string $id): object
    {
        $value = $this->resolve($id);
        if (!$value instanceof $id) {
            throw new ContainerException(sprintf('Le service « %s » ne retourne pas une instance attendue.', $id));
        }

        return $value;
    }

    public function resolve(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }
        if (isset($this->resolving[$id])) {
            throw new ContainerException('Dépendance circulaire détectée : ' . implode(' -> ', array_keys($this->resolving)) . ' -> ' . $id);
        }
        $this->resolving[$id] = true;
        try {
            if (isset($this->factories[$id])) {
                $value = ($this->factories[$id])($this);
                if ($this->shared[$id]) {
                    $this->instances[$id] = $value;
                }

                return $value;
            }
            // Les classes auto-câblées sont partagées : un seul service par requête.
            $value = $this->build($id);
            $this->instances[$id] = $value;

            return $value;
        } finally {
            unset($this->resolving[$id]);
        }
    }

    private function build(string $class): object
    {
        if (!class_exists($class)) {
            throw new ContainerException('Service inconnu : ' . $class);
        }
        $reflection = new ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            throw new ContainerException('Impossible d\'instancier ' . $class . ' (interface ou classe abstraite sans liaison).');
        }
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return $reflection->newInstance();
        }
        $args = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->resolve($type->getName());
                continue;
            }
            if ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
                continue;
            }
            throw new ContainerException(sprintf(
                'Impossible de résoudre le paramètre $%s de %s : ajoutez une liaison explicite.',
                $parameter->getName(),
                $class,
            ));
        }

        return $reflection->newInstanceArgs($args);
    }
}
