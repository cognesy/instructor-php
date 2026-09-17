<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Data;

use InvalidArgumentException;
use JsonException;
use stdClass;

final readonly class JsonContent
{
    private const int JSON_FLAGS = JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION;

    private function __construct(
        private string $json,
        private string $kind,
    ) {}

    public static function text(string $value): self
    {
        return self::encode($value, 'text');
    }

    public static function object(array|stdClass $value): self
    {
        $object = $value instanceof stdClass ? $value : self::arrayToObject($value);

        return self::encode($object, 'object');
    }

    /** @param list<mixed> $value */
    public static function list(array $value): self
    {
        if (! array_is_list($value)) {
            throw new InvalidArgumentException('JsonContent list must use sequential integer keys.');
        }

        return self::encode($value, 'list');
    }

    public static function from(string|array|stdClass $value): self
    {
        return match (true) {
            is_string($value) => self::text($value),
            $value instanceof stdClass => self::object($value),
            array_is_list($value) => self::list($value),
            default => self::object($value),
        };
    }

    public static function fromJson(string $json): self
    {
        try {
            $value = json_decode($json, associative: false, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Invalid JSON content.', previous: $exception);
        }

        if (! is_string($value) && ! is_array($value) && ! $value instanceof stdClass) {
            throw new InvalidArgumentException('Top-level JSON content must be text, an object, or a list.');
        }

        return self::from($value);
    }

    public function value(): string|array|stdClass
    {
        /** @var string|array|stdClass */
        return json_decode($this->json, associative: false, flags: JSON_THROW_ON_ERROR);
    }

    public function json(): string
    {
        return $this->json;
    }

    public function isText(): bool
    {
        return $this->kind === 'text';
    }

    public function isObject(): bool
    {
        return $this->kind === 'object';
    }

    public function isList(): bool
    {
        return $this->kind === 'list';
    }

    private static function encode(string|array|stdClass $value, string $kind): self
    {
        self::assertJsonValue($value, '$');

        try {
            return new self(json_encode($value, self::JSON_FLAGS), $kind);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('JSON content cannot be encoded.', previous: $exception);
        }
    }

    private static function assertJsonValue(mixed $value, string $path): void
    {
        if (is_float($value) && ! is_finite($value)) {
            throw new InvalidArgumentException("JSON content at {$path} must be finite.");
        }
        if ($value === null || is_scalar($value)) {
            return;
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                self::assertJsonValue($item, "{$path}.{$key}");
            }

            return;
        }
        if ($value instanceof stdClass) {
            foreach (get_object_vars($value) as $key => $item) {
                self::assertJsonValue($item, "{$path}.{$key}");
            }

            return;
        }

        throw new InvalidArgumentException("Unsupported JSON content at {$path}: ".get_debug_type($value));
    }

    private static function arrayToObject(array $value): stdClass
    {
        $object = new stdClass;
        foreach ($value as $key => $item) {
            $object->{(string) $key} = $item;
        }

        return $object;
    }
}
