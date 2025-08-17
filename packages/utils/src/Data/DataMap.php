<?php declare(strict_types=1);

namespace Cognesy\Utils\Data;

use Adbar\Dot;
use Aimeos\Map;
use InvalidArgumentException;
use JsonSerializable;

/**
 * DataMap provides a simple way to work with nested data structures.
 * @template TKey of array-key
 * @template TValue
 */
class DataMap implements JsonSerializable
{
    /**
     * Dot object
     */
    private Dot $dot;

    /**
     * Constructor.
     *
     * @param array<TKey, TValue|self> $data
     */
    public function __construct(array $data = []) {
        $this->dot = new Dot($data);
    }

    /**
     * Get a value by key using dot notation.
     *
     * @param string $key
     * @param TValue|null $default
     * @return TValue|null|self
     */
    public function get(string $key, mixed $default = null): mixed {
        $value = $this->dot->get($key, $default);

        if (is_array($value)) {
            return new self($value);
        }

        return $value;
    }

    /**
     * Set a value by key using dot notation.
     *
     * @param string $key
     * @param TValue $value
     * @return self
     */
    public function set(string $key, mixed $value): self {
        if ($value instanceof self) {
            $this->dot->set($key, $value->toArray());
        } else {
            $this->dot->set($key, $value);
        }

        return $this;
    }

    /**
     * Check if a key exists using dot notation.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool {
        return $this->dot->has($key);
    }

    /**
     * Get the type of the value associated with the given key.
     *
     * @param string $key
     * @return string
     *
     * @throws InvalidArgumentException If the key does not exist.
     */
    public function getType(string $key): string {
        if (!$this->has($key)) {
            throw new InvalidArgumentException("Key '{$key}' does not exist.");
        }
        $value = $this->dot->get($key);
        return gettype($value);
    }

    /**
     * Magic getter.
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name): mixed {
        return $this->get($name);
    }

    /**
     * Magic setter.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function __set(string $name, mixed $value): void {
        $this->set($name, $value);
    }

    /**
     * Magic isset.
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool {
        return $this->has($name);
    }

    /**
     * Convert the DataMap to JSON.
     *
     * @param int $options
     * @return string
     */
    public function toJson(int $options = 0): string {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Create a DataMap from JSON.
     *
     * @param string $json
     * @return self
     *
     * @throws InvalidArgumentException If the JSON is invalid.
     */
    public static function fromJson(string $json): self {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON provided: ' . json_last_error_msg());
        }

        return self::fromArray($data);
    }

    /**
     * Convert the DataMap to an array.
     *
     * @return array<TKey, mixed>
     */
    public function toArray(): array {
        return $this->dot->all();
    }

    /**
     * Create a DataMap from an array.
     *
     * @param array<TKey, mixed> $array
     * @return self
     */
    public static function fromArray(array $array): self {
        return new self($array);
    }

    /**
     * List all first-level fields.
     *
     * @return array<TKey>
     */
    public function fields(): array {
        return array_keys($this->toArray());
    }

    /**
     * Merge data into the DataMap.
     *
     * @param array<TKey, mixed> $data
     * @return self
     */
    public function merge(array $data): self {
        $this->dot->merge($data);
        return $this;
    }

    /**
     * Perform operations on the DataMap using Aimeos\Map.
     *
     * @param string|null $path Optional dot notation path to a subset of data. Supports wildcards (*).
     * @return Map
     *
     * @throws InvalidArgumentException If the path does not lead to an array or DataMap, or if wildcards are used incorrectly.
     */
    public function toMap(?string $path = null): Map {
        if ($path === null) {
            // No path provided, return Map for the entire data
            $data = $this->toArray();
            return new Map($data);
        }

        // Check if the path contains any wildcards
        if (strpos($path, '*') === false) {
            return $this->collectValues($path);
        }

        // Wildcard path handling
        $collectedValues = $this->collectWildcardValues($path);
        return new Map($collectedValues);
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed {
        return $this->toArray();
    }

    /**
     * Get a subset of the DataMap.
     *
     * @param string ...$keys
     * @return self
     */
    public function except(string ...$keys): self {
        $data = $this->toArray();
        foreach ($keys as $key) {
            unset($data[$key]);
        }
        return new self($data);
    }

    /**
     * Get a subset of the DataMap.
     *
     * @param string ...$keys
     * @return self
     */
    public function only(string ...$keys): self {
        $data = $this->toArray();
        return new self(array_intersect_key($data, array_flip($keys)));
    }

    public function with(array $values): self {
        $data = $this->toArray();
        foreach ($values as $key => $value) {
            if ($value instanceof self) {
                $data[$key] = $value->toArray();
            } else {
                $data[$key] = $value;
            }
        }
        return new self($data);
    }

    public function clone(): self {
        return clone $this;
    }

    public function __clone() {
        $data = $this->toArray();
        foreach ($data as $key => $value) {
            if ($value instanceof self) {
                $data[$key] = clone $value;
            }
        }
        $this->dot = new Dot($data);
    }

    // INTERNAL /////////////////////////////////////////////////

    /**
     * Collect values from the DataMap based on a path.
     *
     * @param string $path Dot notation path.
     * @return array<mixed> A single flattened array of all matching values.
     */
    private function collectValues(string $path): Map {
        // No wildcard in path, behave as before
        $subset = $this->get($path, null);
        if ($subset === null) {
            throw new InvalidArgumentException("Path '{$path}' does not exist.");
        }

        if ($subset instanceof self) {
            $data = $subset->toArray();
        } elseif (is_array($subset)) {
            $data = $subset;
        } else {
            throw new InvalidArgumentException("Path '{$path}' does not lead to an array or DataMap.");
        }

        return new Map($data);
    }

    /**
     * Collect values from the DataMap based on a wildcard path.
     *
     * @param string $path Dot notation path which may contain wildcards (*).
     * @return array<mixed> A single flattened array of all matching values.
     *
     * @throws InvalidArgumentException If wildcards are used on non-array/object paths.
     */
    private function collectWildcardValues(string $path): array {
        $pathParts = explode('.', $path);
        return $this->traverseWithWildcards($this->toArray(), $pathParts);
    }

    /**
     * Recursively traverse the data with wildcards and collect matching values.
     *
     * @param mixed $currentData Current level of data.
     * @param array<string> $pathParts Remaining parts of the path.
     * @return array<mixed> Collected values.
     *
     * @throws InvalidArgumentException If wildcards are used on non-array/object paths.
     */
    private function traverseWithWildcards(mixed $currentData, array $pathParts): array {
        if (empty($pathParts)) {
            return [$currentData]; // Always return an array of collected items
        }

        $currentPart = array_shift($pathParts);
        $collected = [];

        if ($currentPart === '*') {
            if (is_array($currentData) || is_object($currentData)) {
                foreach ($currentData as $value) {
                    $results = $this->traverseWithWildcards($value, $pathParts);
                    $collected = array_merge($collected, $results);
                }
            }
        } else {
            if (is_object($currentData)) {
                if (property_exists($currentData, $currentPart)) {
                    $value = $currentData->{$currentPart};
                    $results = $this->traverseWithWildcards($value, $pathParts);
                    $collected = array_merge($collected, $results);
                }
            } elseif (is_array($currentData)) {
                if (array_key_exists($currentPart, $currentData)) {
                    $value = $currentData[$currentPart];
                    $results = $this->traverseWithWildcards($value, $pathParts);
                    $collected = array_merge($collected, $results);
                }
            }
        }

        return $collected;
    }
}
