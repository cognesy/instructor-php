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
     * Digests an arbitrary value into a safe {hash, bytes, preview} shape, computed
     * over its JSON encoding. Falls back to a string cast when the value cannot be
     * JSON-encoded (e.g. it contains a resource or a NAN/INF float).
     *
     * @return array{hash: string, bytes: int, preview: string}
     */
    public function digest(mixed $value): array {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $encoded = is_string($encoded) ? $encoded : self::stringify($value);

        return [
            'hash' => 'sha256:' . hash('sha256', $encoded),
            'bytes' => strlen($encoded),
            'preview' => mb_strcut($encoded, 0, $this->previewBytes),
        ];
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

    private static function stringify(mixed $value): string {
        return match (true) {
            is_string($value) => $value,
            is_scalar($value) || $value === null => (string) $value,
            default => print_r($value, true),
        };
    }
}
