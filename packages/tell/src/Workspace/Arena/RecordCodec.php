<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Arena;

use Cognesy\Tell\Workspace\Arena\Record\ConversationRoot;
use Cognesy\Tell\Workspace\Arena\Record\StoredRecord;
use Cognesy\Tell\Workspace\Arena\Record\Turn;
use JsonException;
use stdClass;

/**
 * Arena records use canonical compact JSON:
 *
 * - every object has lexicographically sorted UTF-8 keys;
 * - lists retain their supplied order;
 * - values are null, bool, integer, UTF-8 string, list, or object (floats are rejected);
 * - optional lineage values are omitted rather than written as null or empty lists;
 * - bytes use unescaped Unicode and slashes, then SHA-256 is computed over those exact bytes.
 *
 * Provider envelopes, credentials, headers, usage/timing, events, rendered output, and
 * absolute tool filesystem paths are not representable by the closed record vocabulary.
 */
final class RecordCodec
{
    public function encode(StoredRecord $record): string {
        try {
            return json_encode(
                $this->normalize($record->toArray()),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $exception) {
            throw new RecordException('Unable to encode Arena record.', previous: $exception);
        }
    }

    public function hash(StoredRecord $record): ObjectHash {
        return ObjectHash::fromBytes($this->encode($record));
    }

    public function decode(string $bytes, ?ObjectHash $expectedHash = null): StoredRecord {
        if ($expectedHash !== null && !$expectedHash->equals(ObjectHash::fromBytes($bytes))) {
            throw new RecordException('Arena record bytes do not match the expected SHA-256 hash.');
        }
        try {
            $data = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException $exception) {
            throw new RecordException('Arena record bytes are not valid JSON.', previous: $exception);
        }
        if (!is_array($data) || array_is_list($data) || !is_string($data['kind'] ?? null)) {
            throw new RecordException('Arena record bytes must contain an object with a kind.');
        }

        $record = match ($data['kind']) {
            'conversation' => ConversationRoot::fromArray($data),
            'turn' => Turn::fromArray($data),
            default => throw new RecordException('Unsupported Arena record kind.'),
        };
        if (!hash_equals($bytes, $this->encode($record))) {
            throw new RecordException('Arena record bytes are not in the required stable representation.');
        }

        return $record;
    }

    private function normalize(mixed $value): mixed {
        if ($value instanceof stdClass) {
            return $this->normalizeMap(get_object_vars($value));
        }
        if ($value === null || is_bool($value) || is_int($value)) {
            return $value;
        }
        if (is_string($value)) {
            if (preg_match('//u', $value) !== 1) {
                throw new RecordException('Arena record bytes require valid UTF-8 strings.');
            }

            return $value;
        }
        if (is_float($value)) {
            throw new RecordException('Arena record bytes do not permit floating-point values.');
        }
        if (!is_array($value)) {
            throw new RecordException('Arena record bytes contain an unsupported value.');
        }
        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
        }

        return $this->normalizeMap($value);
    }

    /** @param array<string, mixed> $value */
    private function normalizeMap(array $value): stdClass {
        $keys = array_keys($value);
        foreach ($keys as $key) {
            if (!is_string($key) || preg_match('//u', $key) !== 1) {
                throw new RecordException('Arena record object keys must be UTF-8 strings.');
            }
        }
        sort($keys, SORT_STRING);
        $normalized = new stdClass();
        foreach ($keys as $key) {
            $normalized->{$key} = $this->normalize($value[$key]);
        }

        return $normalized;
    }
}
