<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Secrets\Standard;

use Cognesy\Tell\Core\Paths\TellPaths;

use Cognesy\Config\Secrets\DotenvFileSecretSource;
use Cognesy\Config\Secrets\EnvironmentSecretSource;
use Cognesy\Config\Secrets\ResolvedSecret;
use Cognesy\Config\Secrets\SecretResolver;
use Cognesy\Tell\Core\Contract\Secrets\CanResolveTellSecrets;

/** Resolves values on demand and deliberately exposes no enumerable secret map. */
final readonly class StandardTellSecretResolver implements CanResolveTellSecrets
{
    private SecretResolver $resolver;

    public function __construct(TellPaths $paths, string $directory) {
        $this->resolver = new SecretResolver(
            new EnvironmentSecretSource(),
            DotenvFileSecretSource::optional(
                self::workspaceEnvironment($paths, $directory),
                'workspace-env',
            ),
            (new TellCredentialStore($paths))->source(),
        );
    }

    #[\Override]
    public function resolve(string $name): ?ResolvedSecret {
        return $this->resolver->resolve($name);
    }

    private static function workspaceEnvironment(TellPaths $paths, string $directory): string {
        $resolved = realpath($directory);
        $current = is_string($resolved) ? $resolved : rtrim($directory, '/\\');
        $fallback = $current . DIRECTORY_SEPARATOR . '.tell' . DIRECTORY_SEPARATOR . '.env';
        $userEnvironment = realpath($paths->credentials);

        while (true) {
            $candidate = $current . DIRECTORY_SEPARATOR . '.tell' . DIRECTORY_SEPARATOR . '.env';
            $resolvedCandidate = realpath($candidate);
            $isUserEnvironment = $candidate === $paths->credentials
                || ($resolvedCandidate !== false && $resolvedCandidate === $userEnvironment);
            if (!$isUserEnvironment && is_file($candidate)) {
                return $candidate;
            }
            $parent = dirname($current);
            if ($parent === $current) {
                return $fallback;
            }
            $current = $parent;
        }
    }
}
