<?php declare(strict_types=1);

namespace Cognesy\Messages;

class Content
{
    /** @var ContentPart[] */
    protected array $parts = [];

    /**
     * @param string|ContentPart[]|ContentPart $content
     * If a string is provided, it will be treated as a single text part.
     * If an array is provided, it should contain ContentPart instances or strings.
     * If a ContentPart is provided, it will be used directly.
     */
    public function __construct(
        string|array|ContentPart|null $content = null,
    ) {
        if (is_string($content)) {
            $this->parts[] = ContentPart::text($content);
        } elseif ($content instanceof ContentPart) {
            $this->parts[] = $content;
        } elseif (is_array($content)) {
            foreach ($content as $item) {
                $this->parts[] = ContentPart::fromAny($item);
            }
        }
    }

    public static function fromAny(string|array|Content|ContentPart|null $content): self {
        return match (true) {
            is_null($content) => new self(),
            is_string($content) => new self($content),
            is_array($content) && Message::isMessage($content) => new self(content: $content['content'] ?? ''),
            is_array($content) && array_reduce($content, fn(bool $carry, $item) => $carry && is_string($item), true) => new self(
                array_map(fn($text) => ContentPart::text($text), $content)
            ),
            is_array($content) => new self(array_map(fn($item) => ContentPart::fromAny($item), $content)),
            $content instanceof Content => new self($content->parts()),
            $content instanceof ContentPart => new self([$content]),
            default => throw new \InvalidArgumentException('Content must be a string, array, or ContentPart.'),
        };
    }

    public function addContentPart(ContentPart $part) : static {
        $this->parts[] = $part;
        return $this;
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
        $cloned = new self();
        foreach ($this->parts as $part) {
            $cloned->parts[] = $part->clone();
        }
        return $cloned;
    }

    public function firstContentPart(): ?ContentPart {
        return $this->parts[0] ?? null;
    }

    public function lastContentPart(): ?ContentPart {
        return end($this->parts) ?: null;
    }

    public function appendContentFields(array $fields) : static {
        if (empty($this->parts)) {
            return $this;
        }

        $lastPart = $this->lastContentPart();
        if ($lastPart && !$lastPart->isEmpty()) {
            foreach ($fields as $key => $value) {
                $lastPart->set($key, $value);
            }
        }
        return $this;
    }

    public function appendContentField(string $key, mixed $value): static {
        if (empty($this->parts)) {
            return $this;
        }

        $lastPart = $this->lastContentPart();
        if ($lastPart && !$lastPart->isEmpty()) {
            $lastPart->set($key, $value);
        }
        return $this;
    }
}