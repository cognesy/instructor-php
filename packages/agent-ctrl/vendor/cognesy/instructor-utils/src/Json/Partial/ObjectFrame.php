<?php declare(strict_types=1);

namespace Cognesy\Utils\Json\Partial;

final class ObjectFrame implements ParseFrame
{
    private const DEFAULT_INCOMPLETE_VALUE = '';

    /** @var array<string,mixed> */
    private array $value = [];
    private ?string $pendingKey = null;

    public function hasPendingKey(): bool {
        return $this->pendingKey !== null;
    }

    public function setPendingKey(string $key): void {
        $this->pendingKey = $key;
    }

    public function addValue(mixed $val): void {
        if ($this->pendingKey === null) {
            // If a value arrives without an explicit key, synthesize empty key
            $this->pendingKey = '';
        }
        $this->value[$this->pendingKey] = $val;
        $this->pendingKey = null;
    }

    /** Close at EOF if key present but no value */
    public function closeIfPending(): void {
        if ($this->pendingKey !== null) {
            $this->value[$this->pendingKey] = self::DEFAULT_INCOMPLETE_VALUE;
            $this->pendingKey = null;
        }
    }

    /** @return array<string,mixed> */
    #[\Override]
    public function getValue(): array {
        return $this->value;
    }
}