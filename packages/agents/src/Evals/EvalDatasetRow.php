<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use InvalidArgumentException;

final readonly class EvalDatasetRow
{
    /** @param array<string, mixed> $data */
    public function __construct(private array $data) {}

    public function value(string $key): mixed {
        return $this->data[$key] ?? throw new InvalidArgumentException("Dataset row has no key '{$key}'.");
    }

    public function string(string $key): string {
        $value = $this->value($key);
        return is_string($value) ? $value : throw new InvalidArgumentException("Dataset key '{$key}' is not a string.");
    }

    /** @return array<string, mixed> */
    public function toArray(): array {
        return $this->data;
    }
}
