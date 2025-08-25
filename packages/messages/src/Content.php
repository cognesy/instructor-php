<?php declare(strict_types=1);

namespace Cognesy\Messages;

use Cognesy\Utils\Arrays;
use InvalidArgumentException;

final readonly class Content
{
    /** @var ContentPart[] */
    protected array $parts;

    public function __construct(ContentPart ...$parts) {
        $this->parts = $parts;
    }

    public static function empty(): self {
        return new self();
    }

    public static function text(string $text): self {
        return new self(ContentPart::text($text));
    }

    public static function texts(string ...$texts): self {
        return new self(...array_map(fn($text) => ContentPart::text($text), $texts));
    }

    public static function fromAny(string|array|Content|ContentPart|null $content): self {
        return match (true) {
            is_null($content) => new self(),
            is_string($content) => new self(ContentPart::text($content)),
            is_array($content) && Message::isMessage($content) => new self(ContentPart::fromAny($content['content'] ?? '')),
            is_array($content) && Arrays::hasOnlyStrings($content) => new self(...array_map(fn($text) => ContentPart::text($text), $content)),
            is_array($content) => new self(...array_map(fn($item) => ContentPart::fromAny($item), $content)),
            $content instanceof Content => new self(...$content->parts()),
            $content instanceof ContentPart => new self(...[$content]),
            default => throw new InvalidArgumentException('Content must be a string, array, or ContentPart.'),
        };
    }

    public function addContentPart(ContentPart $part) : static {
        $parts = $this->parts;
        $parts[] = $part;
        return new self(...$parts);
    }

    public function isComposite(): bool {
        return match(true) {
            $this->isNull() => false,
            (count($this->parts) > 1) => true,
            (count($this->parts) === 1) && ($this->firstContentPart()?->isTextPart() ?? true) && ($this->firstContentPart()?->isSimple() ?? true) => false,
            default => true,
        };
    }

    public function isEmpty(): bool {
        return match(true) {
            $this->isNull() => true,
            array_reduce($this->parts, fn(bool $carry, ContentPart $part) => $carry && $part->isEmpty(), true) => true,
            default => false,
        };
    }

    public function isNull(): bool {
        return empty($this->parts);
    }

    /** @return ContentPart[] */
    public function parts(): array {
        return $this->parts;
    }

    public function toArray(): array {
        return match(true) {
            $this->isNull() => [],
            default => array_map(fn($part) => $part->toArray(), $this->parts)
        };
    }

    public function isSimple(): bool {
        return match(true) {
            $this->isNull() => true,
            (count($this->parts) === 1) && $this->firstContentPart()?->isSimple() ?? false => true,
            default => false,
        };
    }

    public function normalized() : string|array {
        return match(true) {
            $this->isNull() => '',
            $this->isSimple() => $this->firstContentPart()?->toString() ?? '',
            default => array_map(fn(ContentPart $part) => $part->toArray(), $this->parts),
        };
    }

    public function toString(): string {
        return match(true) {
            $this->isNull() => '',
            default => implode("\n", array_map(fn($part) => $part->toString(), array_filter($this->parts, fn($part) => !$part->isEmpty()))),
        };
    }

    public function clone() : self {
        return new self(...$this->parts);
    }

    public function firstContentPart(): ?ContentPart {
        return $this->parts[0] ?? null;
    }

    public function lastContentPart(): ContentPart {
        if (empty($this->parts)) {
            return new ContentPart('text', ['text' => '']);
        }
        return $this->parts[array_key_last($this->parts)];
    }

    public function appendContentFields(array $fields) : static {
        if (empty($fields)) {
            return $this;
        }

        if (empty($this->parts)) {
            return new self(new ContentPart(type: 'text', fields: ['text' => '', ...$fields]));
        }

        $lastPart = $this->lastContentPart();
        return new self(...[
            ...array_slice($this->parts, 0, -1),
            $lastPart->withFields([...$lastPart->fields(), ...$fields]),
        ]);
    }

    public function appendContentField(string $key, mixed $value): static {
        if (empty($this->parts)) {
            return new self(ContentPart::text('')->withField($key, $value));
        }

        $lastPart = $this->lastContentPart();
        return new self(...[
            ...array_slice($this->parts, 0, -1),
            $lastPart->withField($key, $value),
        ]);
    }
}