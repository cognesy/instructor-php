<?php declare(strict_types=1);

namespace Cognesy\Messages\Support;

use Cognesy\Messages\Content;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\ContentParts;
use Cognesy\Messages\Message;
use InvalidArgumentException;

final class ContentInput
{
    public static function fromAny(string|array|Content|ContentPart|ContentParts|null $content): Content {
        return match (true) {
            is_null($content) => new Content(),
            is_string($content) => new Content(ContentPart::text($content)),
            is_array($content) && Message::isMessage($content) => self::fromAny($content['content'] ?? ''),
            is_array($content) => Content::fromParts(
                ContentParts::fromArray($content),
            ),
            $content instanceof Content => Content::fromParts($content->partsList()),
            $content instanceof ContentPart => new Content($content),
            $content instanceof ContentParts => Content::fromParts($content),
            default => throw new InvalidArgumentException('Content must be a string, array, ContentPart, or ContentParts.'),
        };
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public static function normalizeFields(string $type, array $fields): array {
        return match ($type) {
            'image_url' => self::normalizeImageUrlFields($fields),
            'file' => self::normalizeFileFields($fields),
            'input_audio' => self::normalizeAudioFields($fields),
            default => $fields,
        };
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private static function normalizeImageUrlFields(array $fields): array {
        if (isset($fields['image_url'])) {
            $fields = self::mergeImageUrlField($fields);
            unset($fields['url']);
            return $fields;
        }
        if (array_key_exists('url', $fields)) {
            $fields['image_url'] = ['url' => $fields['url']];
            unset($fields['url']);
        }
        return $fields;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private static function mergeImageUrlField(array $fields): array {
        $imageUrl = $fields['image_url'];
        if (is_string($imageUrl)) {
            $fields['image_url'] = ['url' => $imageUrl];
            return $fields;
        }
        if (!is_array($imageUrl)) {
            return $fields;
        }
        if (!array_key_exists('url', $imageUrl) && array_key_exists('url', $fields)) {
            $imageUrl['url'] = $fields['url'];
        }
        $fields['image_url'] = $imageUrl;
        return $fields;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private static function normalizeFileFields(array $fields): array {
        if (isset($fields['file'])) {
            $fields['file'] = self::mergeFilePayload($fields['file'], $fields);
            return self::stripFileFieldAliases($fields);
        }
        $payload = self::mergeFilePayload([], $fields);
        if ($payload === []) {
            return $fields;
        }
        $fields['file'] = $payload;
        return self::stripFileFieldAliases($fields);
    }

    /**
     * @param array<string, mixed>|mixed $payload
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private static function mergeFilePayload(mixed $payload, array $fields): array {
        if (!is_array($payload)) {
            $payload = [];
        }
        $fileData = $payload['file_data'] ?? $fields['file_data'] ?? null;
        $fileId = $payload['file_id'] ?? $fields['file_id'] ?? null;
        $fileName = $payload['file_name']
            ?? $payload['filename']
            ?? $fields['file_name']
            ?? $fields['filename']
            ?? null;

        $result = [];
        if ($fileData !== null) {
            $result['file_data'] = $fileData;
        }
        if ($fileName !== null) {
            $result['file_name'] = $fileName;
        }
        if ($fileId !== null) {
            $result['file_id'] = $fileId;
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private static function stripFileFieldAliases(array $fields): array {
        unset(
            $fields['file_data'],
            $fields['file_name'],
            $fields['file_id'],
            $fields['filename']
        );
        return $fields;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private static function normalizeAudioFields(array $fields): array {
        if (isset($fields['input_audio'])) {
            $fields['input_audio'] = self::mergeAudioPayload($fields['input_audio'], $fields);
            unset($fields['data'], $fields['format']);
            return $fields;
        }
        if (!array_key_exists('data', $fields) && !array_key_exists('format', $fields)) {
            return $fields;
        }
        $fields['input_audio'] = self::mergeAudioPayload([], $fields);
        unset($fields['data'], $fields['format']);
        return $fields;
    }

    /**
     * @param array<string, mixed>|mixed $payload
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private static function mergeAudioPayload(mixed $payload, array $fields): array {
        if (!is_array($payload)) {
            $payload = [];
        }
        $data = $payload['data'] ?? $fields['data'] ?? null;
        $format = $payload['format'] ?? $fields['format'] ?? null;
        $result = [];
        if ($data !== null) {
            $result['data'] = $data;
        }
        if ($format !== null) {
            $result['format'] = $format;
        }
        return $result;
    }
}
