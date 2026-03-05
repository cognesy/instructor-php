<?php declare(strict_types=1);

namespace Cognesy\Config;

use InvalidArgumentException;
use RuntimeException;

final class EnvTemplate
{
    private const PLACEHOLDER_PATTERN = '/\$\{([A-Za-z_][A-Za-z0-9_]*)(?:(:-|:\?|[?])([^}]*)?)?}/';

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    public function resolveData(array $data): array {
        $resolved = [];
        foreach ($data as $key => $value) {
            $resolved[$key] = $this->resolveValue($value);
        }

        return $resolved;
    }

    public function resolveValue(mixed $value): mixed {
        return match (true) {
            is_array($value) => $this->resolveData($value),
            is_string($value) => $this->resolveString($value),
            default => $value,
        };
    }

    public function resolveString(string $value): string {
        if (!str_contains($value, '${')) {
            return $value;
        }

        $resolved = preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            /** @param array{0: string, 1: non-empty-string, 2?: ':-'|':?'|'?', 3?: string} $matches */
            function (array $matches): string {
                $name = $matches[1];
                $operator = $matches[2] ?? '';
                $operand = $matches[3] ?? '';
                $env = $this->readEnv($name);

                if ($operator === '') {
                    return $env ?? '';
                }

                if ($operator === ':-') {
                    return ($env === null || $env === '') ? $operand : $env;
                }

                if ($operator === '?') {
                    return $this->requiredValue($name, $env, null);
                }

                return $this->requiredValue($name, $env, $operand);
            },
            $value,
        );

        if (!is_string($resolved)) {
            throw new RuntimeException('Template interpolation failed for non-string value');
        }

        return $resolved;
    }

    private function requiredValue(string $name, ?string $env, ?string $customMessage): string {
        if ($env !== null && $env !== '') {
            return $env;
        }

        $message = match (true) {
            $customMessage !== null && $customMessage !== '' => $customMessage,
            default => "Required environment variable '{$name}' is missing or empty",
        };

        throw new InvalidArgumentException($message);
    }

    private function readEnv(string $name): ?string {
        $value = Env::get($name, null);
        return is_string($value) ? $value : null;
    }
}
