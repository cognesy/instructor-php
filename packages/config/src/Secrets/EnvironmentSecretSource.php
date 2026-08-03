<?php

declare(strict_types=1);

namespace Cognesy\Config\Secrets;

use Cognesy\Config\Contracts\CanResolveSecrets;

final readonly class EnvironmentSecretSource implements CanResolveSecrets
{
    public function __construct(public string $source = 'process-environment') {}

    public function resolve(string $name): ?ResolvedSecret
    {
        $environment = getenv($name);
        $value = match (true) {
            is_string($environment) && $environment !== '' => $environment,
            is_string($_ENV[$name] ?? null) && $_ENV[$name] !== '' => $_ENV[$name],
            is_string($_SERVER[$name] ?? null) && $_SERVER[$name] !== '' => $_SERVER[$name],
            default => null,
        };

        return match ($value) {
            null => null,
            default => new ResolvedSecret($name, $value, $this->source),
        };
    }
}
