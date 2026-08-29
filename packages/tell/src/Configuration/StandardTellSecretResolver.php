<?php

declare(strict_types=1);

namespace Cognesy\Tell\Configuration;

use Cognesy\Config\Secrets\DotenvFileSecretSource;
use Cognesy\Config\Secrets\EnvironmentSecretSource;
use Cognesy\Config\Secrets\ResolvedSecret;
use Cognesy\Config\Secrets\SecretResolver;
use Cognesy\Tell\Contracts\CanResolveTellSecrets;

/** Resolves values on demand and deliberately exposes no enumerable secret map. */
final readonly class StandardTellSecretResolver implements CanResolveTellSecrets
{
    private SecretResolver $resolver;

    public function __construct(TellPaths $paths, string $directory) {
        $this->resolver = new SecretResolver(
            new EnvironmentSecretSource(),
            DotenvFileSecretSource::optional(
                rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . '.env',
                'workspace-env',
            ),
            (new TellCredentialStore($paths))->source(),
        );
    }

    public function resolve(string $name): ?ResolvedSecret {
        return $this->resolver->resolve($name);
    }
}
