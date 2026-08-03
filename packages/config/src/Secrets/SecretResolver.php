<?php

declare(strict_types=1);

namespace Cognesy\Config\Secrets;

use Cognesy\Config\Contracts\CanResolveSecrets;

final readonly class SecretResolver implements CanResolveSecrets
{
    /** @var list<CanResolveSecrets> */
    private array $sources;

    public function __construct(CanResolveSecrets ...$sources)
    {
        $this->sources = array_values($sources);
    }

    public function resolve(string $name): ?ResolvedSecret
    {
        foreach ($this->sources as $source) {
            $resolved = $source->resolve($name);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }
}
