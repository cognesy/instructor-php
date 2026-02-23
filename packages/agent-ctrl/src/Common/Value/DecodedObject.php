<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Common\Value;

final readonly class DecodedObject
{
    /**
     * @param array<string,mixed> $data
     */
    public function __construct(private array $data) {}

    /**
     * @return array<string,mixed>
     */
    public function data() : array {
        return $this->data;
    }

    public function has(string $key): bool {
        return array_key_exists($key, $this->data);
    }

    public function get(string $key, mixed $default = null): mixed {
        if ($this->has($key)) {
            return $this->data[$key];
        }
        return $default;
    }

    public function getString(string $key, ?string $default = null): ?string {
        $value = $this->get($key, $default);
        if (is_string($value)) {
            return $value;
        }
        return $default;
    }

    public function getNonEmptyString(string $key): ?string {
        $value = $this->getString($key);
        if ($value === null || $value === '') {
            return null;
        }
        return $value;
    }
}
