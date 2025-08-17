<?php declare(strict_types=1);

namespace Cognesy\Utils\Data;

readonly class ImmutableDataMap {
    private DataMap $dataMap;

    public function __construct(array $data = []) {
        $this->dataMap = new DataMap($data);
    }

//    public static function fromDataMap(DataMap $dataMap): self {
//        $instance = new self();
//        $instance->dataMap = $dataMap;
//        return $instance;
//    }
//
//    public function with(array $data): self {
//        return new self(array_merge($this->dataMap->toArray(), $data));
//    }
//
//    public function set(string $key, mixed $value): self {
//        return $this->with([$key => $value]);
//    }
//
    public function get(string $key, mixed $default = null): mixed {
        return $this->dataMap->get($key, $default);
    }

    public function has(string $key): bool {
        return $this->dataMap->has($key);
    }

    public function toArray(): array {
        return $this->dataMap->toArray();
    }

    public function toJson(int $options = 0): string {
        return $this->dataMap->toJson($options);
    }

    public function jsonSerialize(): mixed {
        return $this->dataMap->jsonSerialize();
    }
}