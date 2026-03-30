<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Tests\Support;

final class SymfonyTestServiceRegistry
{
    /** @var array<string, object> */
    private static array $services = [];

    public static function put(object $service): string
    {
        $id = bin2hex(random_bytes(12));
        self::$services[$id] = $service;

        return $id;
    }

    public static function get(string $id): object
    {
        return self::$services[$id]
            ?? throw new \RuntimeException("Symfony test service [{$id}] is not registered.");
    }

    public static function reset(): void
    {
        self::$services = [];
    }
}
