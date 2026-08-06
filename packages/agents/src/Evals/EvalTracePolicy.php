<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

/**
 * Controls how much of a target's tool payloads land in a serialized eval trace.
 *
 * `safe()` is the default everywhere and digests tool arguments and results into a
 * hash/byte-length/bounded-preview shape, because those payloads are where customer
 * records, file contents, and credentials live. `full()` serializes them verbatim and
 * is reachable only by explicit construction - it is never a default.
 */
final readonly class EvalTracePolicy
{
    private const int DEFAULT_PREVIEW_BYTES = 120;

    /**
     * Recursion depth cap for shape rendering in `digest()`'s preview. A map
     * nested deeper than this stops expanding and collapses to `<object:N>`,
     * so a pathologically deep payload cannot blow the stack or the preview.
     * Lists never expand regardless of depth - they always render as `<array:N>`.
     */
    private const int MAX_PREVIEW_DEPTH = 6;

    private function __construct(
        private bool $full,
        private int $previewBytes,
    ) {}

    // CONSTRUCTORS ////////////////////////////////////////////

    public static function safe(): self {
        return new self(full: false, previewBytes: self::DEFAULT_PREVIEW_BYTES);
    }

    public static function full(): self {
        return new self(full: true, previewBytes: self::DEFAULT_PREVIEW_BYTES);
    }

    // MUTATORS ////////////////////////////////////////////////

    public function withPreviewBytes(int $bytes): self {
        return new self(full: $this->full, previewBytes: max(0, $bytes));
    }

    // ACCESSORS ///////////////////////////////////////////////

    public function isFull(): bool {
        return $this->full;
    }

    public function previewBytes(): int {
        return $this->previewBytes;
    }

    // DIGESTING ///////////////////////////////////////////////

    /**
     * Digests an arbitrary value into a safe {hash, bytes, preview} shape. `hash`
     * and `bytes` are computed over the value's real JSON encoding, unchanged from
     * before. `preview` never contains a payload value: it renders the value's
     * SHAPE (types and, for maps, keys) as a bounded JSON string, then falls back
     * to a string cast when the value cannot be JSON-encoded (e.g. it contains a
     * resource or a NAN/INF float).
     *
     * @return array{hash: string, bytes: int, preview: string}
     */
    public function digest(mixed $value): array {
        $jsonEncoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $encoded = is_string($jsonEncoded) ? $jsonEncoded : self::stringify($value);

        $shape = is_string($jsonEncoded)
            ? self::shapeOf(json_decode($jsonEncoded, true), depth: 0)
            : self::shapeOf($encoded, depth: 0);
        $previewJson = json_encode($shape, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $preview = is_string($previewJson) ? $previewJson : self::stringify($shape);

        return [
            'hash' => 'sha256:' . hash('sha256', $encoded),
            'bytes' => strlen($encoded),
            'preview' => mb_strcut($preview, 0, $this->previewBytes),
        ];
    }

    /**
     * True when `$value` is already in the shape `digest()` produces: an array
     * with exactly the keys `hash`, `bytes`, `preview`, where `hash` starts with
     * `sha256:`. Used to make re-serialization idempotent - a value that has
     * already been digested (e.g. hydrated from a previously serialized trace,
     * or forwarded verbatim from a remote target that already digested it) must
     * never be digested a second time.
     */
    public static function isDigest(mixed $value): bool {
        if (!is_array($value)) {
            return false;
        }
        $keys = array_keys($value);
        sort($keys);
        if ($keys !== ['bytes', 'hash', 'preview']) {
            return false;
        }
        return is_string($value['hash']) && str_starts_with($value['hash'], 'sha256:');
    }

    // SERIALIZATION ///////////////////////////////////////////

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'mode' => $this->full ? 'full' : 'safe',
            'previewBytes' => $this->previewBytes,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self {
        $previewBytes = is_int($data['previewBytes'] ?? null) ? $data['previewBytes'] : self::DEFAULT_PREVIEW_BYTES;
        return new self(
            full: ($data['mode'] ?? 'safe') === 'full',
            previewBytes: max(0, $previewBytes),
        );
    }

    // INTERNAL ////////////////////////////////////////////////

    /**
     * Renders a value's shape: scalars become type placeholders that never carry
     * the value (`<string:N>` with N the byte length, `<int>`, `<float>`, `<bool>`,
     * `<null>`); maps (associative arrays / JSON objects) expand recursively with
     * keys preserved and each value elided per this same rule, up to
     * `MAX_PREVIEW_DEPTH`; lists (sequential integer keys) always collapse to
     * `<array:N>` with N the element count, regardless of depth.
     */
    private static function shapeOf(mixed $value, int $depth): mixed {
        return match (true) {
            is_string($value) => sprintf('<string:%d>', strlen($value)),
            is_int($value) => '<int>',
            is_float($value) => '<float>',
            is_bool($value) => '<bool>',
            $value === null => '<null>',
            is_array($value) => self::shapeOfArray($value, $depth),
            default => '<unknown>',
        };
    }

    /** @param array<array-key, mixed> $value */
    private static function shapeOfArray(array $value, int $depth): mixed {
        if (array_is_list($value)) {
            return sprintf('<array:%d>', count($value));
        }
        if ($depth >= self::MAX_PREVIEW_DEPTH) {
            return sprintf('<object:%d>', count($value));
        }

        $shaped = [];
        foreach ($value as $key => $item) {
            $shaped[$key] = self::shapeOf($item, $depth + 1);
        }
        return $shaped;
    }

    private static function stringify(mixed $value): string {
        return match (true) {
            is_string($value) => $value,
            is_scalar($value) || $value === null => (string) $value,
            default => print_r($value, true),
        };
    }
}
