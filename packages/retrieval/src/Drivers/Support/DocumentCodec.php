<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Drivers\Support;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class DocumentCodec
{
    public static function key(int|string $id): string
    {
        return 'r_'.hash('sha256', self::idType($id)."\0".(string) $id);
    }

    public static function uuid(int|string $id): string
    {
        $hash = substr(self::key($id), 2);

        return implode('-', [
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        ]);
    }

    /** @return array{original_id: string, id_type: 'int'|'string'} */
    public static function identity(int|string $id): array
    {
        return ['original_id' => (string) $id, 'id_type' => self::idType($id)];
    }

    /** @param array<string, mixed> $data */
    public static function restoreId(array $data): int|string
    {
        $id = $data['original_id'] ?? null;
        if (! is_string($id)) {
            throw new RuntimeException('Stored document is missing its original id');
        }

        return ($data['id_type'] ?? 'string') === 'int' ? (int) $id : $id;
    }

    /** @param array<string, mixed> $metadata */
    public static function encodeMetadata(array $metadata): string
    {
        if ($metadata === []) {
            return '{}';
        }

        return self::encode($metadata, 'Metadata must be JSON serializable');
    }

    /** @return array<string, mixed> */
    public static function decodeMetadata(string $metadata): array
    {
        try {
            $decoded = json_decode($metadata, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Stored metadata is not valid JSON', 0, $error);
        }
        if (! is_array($decoded) || ! str_starts_with(ltrim($metadata), '{')) {
            throw new RuntimeException('Stored metadata must be a JSON object');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    public static function metadataToken(string $field, mixed $value): string
    {
        $json = self::encode([$field, $value], 'Metadata filter must be JSON serializable');

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return list<string>
     */
    public static function metadataTokens(array $metadata): array
    {
        $tokens = [];
        foreach ($metadata as $field => $value) {
            $tokens[] = self::metadataToken($field, $value);
        }
        sort($tokens, SORT_STRING);

        return $tokens;
    }

    /** @return 'int'|'string' */
    private static function idType(int|string $id): string
    {
        return is_int($id) ? 'int' : 'string';
    }

    private static function encode(mixed $value, string $message): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException $error) {
            throw new InvalidArgumentException($message, 0, $error);
        }
    }
}
