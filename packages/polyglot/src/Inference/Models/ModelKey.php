<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Models;

final readonly class ModelKey
{
    public function __construct(
        public string $driver,
        public string $model,
    ) {}

    public function matches(string $driver, string $model): bool
    {
        return $this->driver === $driver && $this->model === $model;
    }

    public function lookupKey(): string
    {
        return $this->driver . "\0" . $this->model;
    }

    public function toString(): string
    {
        return $this->driver . '/' . $this->model;
    }

    /** @return array{driver: string, model: string} */
    public function toArray(): array
    {
        return [
            'driver' => $this->driver,
            'model' => $this->model,
        ];
    }
}
