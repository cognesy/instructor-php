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
        return new self($type, $fields);
    }

    public static function text(string $text): static {
        return new self('text', ['text' => $text]);
    }

    public static function imageUrl(string $url): static {
        return new self('image_url', ['url' => $url]);
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

    // PUBLIC API ///////////////////////////////////////////

    public function type(): string {
        return $this->type;
    }

    /** @return array<string, mixed> */
    public function fields(): array {
        return $this->fields;
    }

    /** @param array<string, mixed> $fields */
    public function withFields(array $fields): self {
        return new self($this->type, $fields);
    }

    public function isTextPart(): bool {
        return $this->type === 'text';
    }

    public function hasText(): bool {
        return isset($this->fields['text']) && is_string($this->fields['text']);
    }

    public function withField(string $key, mixed $value): self {
        $fields = $this->fields;
        $fields[$key] = $value;
        return new self($this->type, $fields);
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

    private function shouldExport(string|int $key, mixed $value): bool {
        return !is_null($value)
            && ($value !== '')
            && ($value !== [])
            && (is_string($key) ? (str_starts_with($key, '_') === false) : true);
    }
}