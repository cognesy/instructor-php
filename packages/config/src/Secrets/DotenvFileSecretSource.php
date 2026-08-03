<?php

declare(strict_types=1);

namespace Cognesy\Config\Secrets;

use Cognesy\Config\Contracts\CanResolveSecrets;
use Dotenv\Dotenv;
use RuntimeException;
use SensitiveParameter;
use Throwable;

final readonly class DotenvFileSecretSource implements CanResolveSecrets
{
    /** @param array<string, string> $values */
    private function __construct(
        public string $source,
        #[SensitiveParameter]
        private array $values,
    ) {}

    public static function optional(string $path, string $source): self
    {
        return match (is_file($path)) {
            true => self::fromFile($path, $source),
            false => new self($source, []),
        };
    }

    public static function fromFile(string $path, string $source): self
    {
        $contents = @file_get_contents($path);
        if (! is_string($contents)) {
            throw new RuntimeException("Failed to read secret source: {$source}");
        }
        try {
            $parsed = Dotenv::parse($contents);
        } catch (Throwable) {
            throw new RuntimeException("Invalid dotenv secret source: {$source}");
        }
        $values = [];
        foreach ($parsed as $name => $value) {
            if (is_string($value)) {
                $values[$name] = $value;
            }
        }

        return new self($source, $values);
    }

    public function resolve(string $name): ?ResolvedSecret
    {
        $value = $this->values[$name] ?? null;

        return match (true) {
            ! is_string($value), $value === '' => null,
            default => new ResolvedSecret($name, $value, $this->source),
        };
    }

    /** @return array{source: string, configuredCount: int} */
    public function __debugInfo(): array
    {
        return [
            'source' => $this->source,
            'configuredCount' => count($this->values),
        ];
    }
}
