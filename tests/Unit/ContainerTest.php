<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Container;
use App\Core\ContainerException;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase
{
    public function testAutowiresConcreteDependencies(): void
    {
        $container = new Container();
        $service = $container->get(FixtureService::class);
        self::assertInstanceOf(FixtureDependency::class, $service->dependency);
        self::assertSame($service, $container->get(FixtureService::class));
    }

    public function testBindCreatesNewInstanceEachTime(): void
    {
        $container = new Container();
        $container->bind(FixtureDependency::class, static fn (): FixtureDependency => new FixtureDependency());
        self::assertNotSame($container->get(FixtureDependency::class), $container->get(FixtureDependency::class));
    }

    public function testSingletonIsShared(): void
    {
        $container = new Container();
        $container->singleton(FixtureDependency::class, static fn (): FixtureDependency => new FixtureDependency());
        self::assertSame($container->get(FixtureDependency::class), $container->get(FixtureDependency::class));
    }

    public function testUnresolvableScalarThrows(): void
    {
        $this->expectException(ContainerException::class);
        (new Container())->get(FixtureNeedsScalar::class);
    }

    public function testCircularDependencyIsDetected(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('circulaire');
        (new Container())->get(FixtureCycleA::class);
    }
}

final class FixtureDependency
{
}

final class FixtureService
{
    public function __construct(public readonly FixtureDependency $dependency)
    {
    }
}

final class FixtureNeedsScalar
{
    public function __construct(public readonly string $value)
    {
    }
}

final class FixtureCycleA
{
    public function __construct(public readonly FixtureCycleB $b)
    {
    }
}

final class FixtureCycleB
{
    public function __construct(public readonly FixtureCycleA $a)
    {
    }
}
