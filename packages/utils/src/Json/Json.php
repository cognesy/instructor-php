<?php declare(strict_types=1);

namespace Cognesy\Utils\Json;

use InvalidArgumentException;
use JsonException;

/**
 * Immutable JSON value object with lazy bidirectional conversion.
 *
 * Holds either a JSON string, a PHP array, or both. Conversions between
 * representations happen lazily on first access — if you create from a
 * string and only call toString(), no decoding ever happens.
 */
class Json
{
    private ?string $json;
    private ?array $array;
    private bool $isPartial;

    private function __construct(?string $json, ?array $array, bool $isPartial = false)
    {
        $this->json = $json;
        $this->array = $array;
        $this->isPartial = $isPartial;
    }

    // ── Factory methods ──────────────────────────────────────────────

    /**
     * Create an empty Json value.
     */
    public static function none(): self
    {
        return new self('', [], false);
    }

    /**
     * Create from a JSON string. Expects valid JSON.
     * Decoding is deferred until toArray() is called.
     */
    public static function fromString(string $json): self
    {
        $trimmed = trim($json);
        if ($trimmed === '') {
            return self::none();
        }
        return new self($trimmed, null, false);
    }

    /**
     * Create from a partial/broken JSON string.
     * Uses JsonDecoder for resilient parsing when toArray() is called.
     */
    public static function fromPartial(string $text): self
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return self::none();
        }
        return new self($trimmed, null, true);
    }

    /**
     * Create from a PHP array. Encoding is deferred until toString() is called.
     */
    public static function fromArray(array $array): self
    {
        return new self(null, $array, false);
    }

    // ── Instance methods ─────────────────────────────────────────────

    public function isEmpty(): bool
    {
        if ($this->json !== null) {
            return $this->json === '';
        }
        return $this->array === [];
    }

    /**
     * Get the JSON string representation.
     * If created from an array, encodes lazily on first call.
     */
    public function toString(): string
    {
        if ($this->json !== null) {
            return $this->json;
        }

        $this->json = self::encodeValue($this->array);
        return $this->json;
    }

    /**
     * Get the PHP array representation.
     * If created from a string, decodes lazily on first call.
     */
    public function toArray(): array
    {
        if ($this->array !== null) {
            return $this->array;
        }

        if ($this->json === null || $this->json === '') {
            $this->array = [];
            return $this->array;
        }

        if ($this->isPartial) {
            $decoded = JsonDecoder::decode($this->json);
            $this->array = is_array($decoded) ? $decoded : [];
        } else {
            try {
                $decoded = json_decode($this->json, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new InvalidArgumentException("Invalid JSON provided: {$e->getMessage()}", 0, $e);
            }

            if (!is_array($decoded)) {
                $type = gettype($decoded);
                throw new InvalidArgumentException("Json::toArray expects JSON object or array as root type, got {$type}.");
            }
            $this->array = $decoded;
        }

        return $this->array;
    }

    /**
     * Format the JSON string with options (e.g. JSON_PRETTY_PRINT).
     */
    public function format(int $options = 0, ?int $depth = null): string
    {
        if (is_null($depth)) {
            return self::encodeValue($this->toArray(), $options);
        }
        $safeDepth = max(1, $depth);
        /** @var int<1, 2147483647> $safeDepth */
        return self::encodeValue($this->toArray(), $options, $safeDepth);
    }

    // ── Static helpers ───────────────────────────────────────────────

    /**
     * Decode a JSON string to a PHP value with optional default on failure.
     */
    public static function decode(string $text, mixed $default = null): mixed
    {
        if ($text === '') {
            return $default;
        }
        try {
            return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            if ($default === null) {
                throw $e;
            }
            return $default;
        }
    }

    /**
     * Encode a PHP value to a JSON string.
     */
    public static function encode(mixed $json, int $options = 0): string
    {
        return self::encodeValue($json, $options);
    }

    /** @param int<1, 2147483647> $depth */
    private static function encodeValue(mixed $value, int $options = 0, int $depth = 512): string
    {
        try {
            return json_encode($value, $options | JSON_THROW_ON_ERROR, $depth);
        } catch (JsonException $e) {
            throw new InvalidArgumentException("Failed to encode JSON: {$e->getMessage()}", 0, $e);
        }
    }
}
