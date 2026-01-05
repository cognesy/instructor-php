<?php declare(strict_types=1);

namespace Cognesy\Messages;

use Cognesy\Messages\Utils\Audio;
use Cognesy\Messages\Utils\File;
use Cognesy\Messages\Utils\Image;

final readonly class ContentPart
{
    protected string $type;
    /** @var array<string, mixed> */
    protected array $fields;

    public function __construct(
        string $type,
        array $fields = [],
    ) {
        $this->type = $type;
        $this->fields = array_filter(
            $fields, 
            fn($value, $key) => !is_null($value) && ($value !== []),
            ARRAY_FILTER_USE_BOTH
        );
    }

    // FACTORY METHODS //////////////////////////////////////

    public static function fromArray(array $content): static {
        $type = $content['type'] ?? 'text';
        $fields = $content;
        unset($fields['type']);
        $fields = self::normalizeFields($type, $fields);
        return new self($type, $fields);
    }

    public static function text(string $text): static {
        return new self('text', ['text' => $text]);
    }

    public static function imageUrl(string $url): static {
        return new self('image_url', ['image_url' => ['url' => $url]]);
    }

    public static function image(Image $image): static {
        return new self('image_url', $image->toContentPart()->fields());
    }

    public static function file(File $file): static {
        return new self('file', $file->toContentPart()->fields());
    }

    public static function audio(Audio $audio): static {
        return new self('input_audio', $audio->toContentPart()->fields());
    }

    public static function fromAny(mixed $item): static {
        return match (true) {
            is_string($item) => self::text($item),
            is_array($item) => self::fromArray($item),
            is_object($item) && $item instanceof self => $item,
            is_object($item) && $item instanceof Image => self::image($item),
            is_object($item) && $item instanceof File => self::file($item),
            is_object($item) && $item instanceof Audio => self::audio($item),
            default => throw new \InvalidArgumentException('Unsupported content type: ' . gettype($item)),
        };
    }

    // MUTATORS /////////////////////////////////////////////

    /** @param array<string, mixed> $fields */
    public function withFields(array $fields): self {
        return new self($this->type, $fields);
    }

    public function withField(string $key, mixed $value): self {
        $fields = $this->fields;
        $fields[$key] = $value;
        return new self($this->type, $fields);
    }

    // ACCESSORS ////////////////////////////////////////////

    public function type(): string {
        return $this->type;
    }

    /** @return array<string, mixed> */
    public function fields(): array {
        return $this->fields;
    }

    public function isTextPart(): bool {
        return $this->type === 'text';
    }

    public function hasText(): bool {
        return isset($this->fields['text']) && is_string($this->fields['text']);
    }

    public function get(string $key, mixed $default = null): mixed {
        return $this->fields[$key] ?? $default;
    }

    public function has(string $key): bool {
        return array_key_exists($key, $this->fields);
    }

    public function isEmpty(): bool {
        foreach ($this->fields as $value) {
            if ($value !== null && $value !== '' && $value !== []) {
                return false;
            }
        }
        return true;
    }

    public function isSimple(): bool {
        // is equivalent to [role = '', content = '']
        return count($this->fields) === 1 && isset($this->fields['text']);
    }

    // CONVERSIONS and TRANSFORMERS /////////////////////////

    public function toArray(): array {
        $data = [
            'type' => $this->type,
        ];
        foreach ($this->fields as $key => $value) {
            if (!$this->shouldExport($key, $value)) {
                continue;
            }
            $data[$key] = $value;
        }
        return $data;
    }

    public function toString(): string {
        return $this->fields['text'] ?? "";
    }

    public function clone(): self {
        return new self($this->type, $this->fields);
    }

    // INTERNAL /////////////////////////////////////////////

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private static function normalizeFields(string $type, array $fields): array {
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

    private function shouldExport(string|int $key, mixed $value): bool {
        return !is_null($value)
            && ($value !== '')
            && ($value !== [])
            && (is_string($key) ? (str_starts_with($key, '_') === false) : true);
    }
}
