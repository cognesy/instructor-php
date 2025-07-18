<?php declare(strict_types=1);

namespace Cognesy\Messages\V2;

use Cognesy\Messages\Enums\ContentType;
use Stringable;

final readonly class ContentPart implements Stringable
{
    public function __construct(
        public ContentType $type,
        public array $data
    ) {}

    // FACTORY METHODS ///////////////////////////////////

    public static function empty(): self {
        return new self(ContentType::Text, ['text' => '']);
    }

    public static function text(string $text): self {
        return new self(ContentType::Text, ['text' => $text]);
    }

    public static function image(string $url): self {
        return new self(ContentType::Image, ['url' => $url]);
    }

    public static function file(string $path, string $mimeType = null): self {
        return new self(ContentType::File, array_filter([
            'file_path' => $path,
            'mime_type' => $mimeType,
        ]));
    }

    public static function fromArray(array $data): self {
        $type = ContentType::from($data['type'] ?? 'text');
        unset($data['type']);
        return new self($type, $data);
    }

    // ACCESS METHODS ////////////////////////////////////

    public function isText(): bool {
        return $this->type === ContentType::Text;
    }

    public function isImage(): bool {
        return $this->type === ContentType::Image;
    }

    public function isFile(): bool {
        return $this->type === ContentType::File;
    }

    public function isEmpty(): bool {
        return empty($this->data) || ($this->isText() && empty($this->data['text']));
    }

    public function getText(): string {
        return $this->isText() ? ($this->data['text'] ?? '') : '';
    }

    public function getImageUrl(): string {
        return $this->isImage() ? ($this->data['url'] ?? '') : '';
    }

    public function getFilePath(): string {
        return $this->isFile() ? ($this->data['file_path'] ?? '') : '';
    }

    public function toArray(): array {
        return ['type' => $this->type->value, ...$this->data];
    }

    public function __toString(): string {
        return $this->getText();
    }
}