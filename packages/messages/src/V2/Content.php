<?php declare(strict_types=1);

namespace Cognesy\Messages\V2;

use Stringable;

final readonly class Content implements Stringable
{
    /** @var string|ContentPart[] */
    private string|array $value;

    private function __construct(string|array $value) {
        $this->value = $value;
    }

    // CREATION METHODS ///////////////////////////////////

    public static function empty(): self {
        return new self('');
    }

    public static function text(string $text): self {
        return new self($text);
    }

    public static function parts(ContentPart ...$parts): self {
        return new self($parts);
    }

    public static function fromArray(array $data): self {
        if (isset($data['type'])) {
            return self::parts(ContentPart::fromArray($data));
        }
        if (is_string($data[0] ?? null)) {
            return self::text(implode("\n", $data));
        }
        return self::parts(...array_map(fn($item) => ContentPart::fromArray($item), $data));
    }

    public static function create(string|array|ContentPart $content): self {
        return match (true) {
            is_string($content) => self::text($content),
            $content instanceof ContentPart => self::parts($content),
            is_array($content) => self::fromArray($content),
            default => throw new \InvalidArgumentException('Unsupported content type: ' . gettype($content)),
        };
    }

    // ACCESS METHODS ////////////////////////////////////

    public function isSimple(): bool {
        return is_string($this->value);
    }

    public function isMultipart(): bool {
        return is_array($this->value);
    }

    public function isEmpty(): bool {
        if ($this->isSimple()) {
            return $this->value === '';
        }
        return empty($this->value) || count(array_filter($this->value, fn($part) => !$part->isEmpty())) === 0;
    }

    public function getText(): string {
        if ($this->isSimple()) {
            return $this->value;
        }
        return implode("\n", array_map(
            fn($part) => $part->isText() ? $part->getText() : '',
            $this->value
        ));
    }

    public function getParts(): array {
        return $this->isSimple() ? [] : $this->value;
    }

    public function getTextParts(): array {
        if ($this->isSimple()) {
            return $this->value ? [ContentPart::text($this->value)] : [];
        }
        return array_filter($this->value, fn($part) => $part->isText());
    }

    public function getImageParts(): array {
        if ($this->isSimple()) {
            return [];
        }
        return array_filter($this->value, fn($part) => $part->isImage());
    }

    public function getFileParts(): array {
        if ($this->isSimple()) {
            return [];
        }
        return array_filter($this->value, fn($part) => $part->isFile());
    }

    public function hasText(): bool {
        return !empty($this->getText());
    }

    public function hasImages(): bool {
        return !empty($this->getImageParts());
    }

    public function hasFiles(): bool {
        return !empty($this->getFileParts());
    }

    public function toArray(): array|string {
        if ($this->isSimple()) {
            return $this->value;
        }
        return array_map(fn($part) => $part->toArray(), $this->value);
    }

    public function __toString(): string {
        return $this->getText();
    }

    // MUTATION METHODS //////////////////////////////////

    public function addPart(ContentPart ...$parts): self {
        $currentParts = $this->isSimple() ? [] : $this->value;
        return new self([...$currentParts, ...$parts]);
    }

    public function addText(string $text): self {
        return $this->addPart(ContentPart::text($text));
    }

    public function addImage(string $url): self {
        return $this->addPart(ContentPart::image($url));
    }

    public function addFile(string $path): self {
        return $this->addPart(ContentPart::file($path));
    }
}