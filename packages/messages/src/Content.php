<?php declare(strict_types=1);

namespace Cognesy\Messages;

use Cognesy\Messages\Support\ContentInput;

final readonly class Content
{
    protected ContentParts $parts;

    public function __construct(ContentPart ...$parts) {
        $this->parts = new ContentParts(...$parts);
    }

    // CONSTRUCTORS /////////////////////////////////////////////

    public static function empty(): self {
        return new self();
    }

    public static function text(string $text): self {
        return new self(ContentPart::text($text));
    }

    public static function texts(string ...$texts): self {
        return self::fromParts(ContentParts::fromArray($texts));
    }

    public static function fromAny(string|array|Content|ContentPart|ContentParts|null $content): self {
        return ContentInput::fromAny($content);
    }

    public static function fromParts(ContentParts $parts): self {
        return new self(...$parts->all());
    }

    // MUTATORS /////////////////////////////////////////////////

    public function addContentPart(ContentPart $part) : static {
        $parts = $this->parts->add($part);
        return new self(...$parts->all());
    }

    public function appendContentField(string $key, mixed $value): static {
        if ($this->parts->isEmpty()) {
            return new self(ContentPart::text('')->withField($key, $value));
        }

        $lastPart = $this->lastContentPart();
        $parts = $this->parts->replaceLast($lastPart->withField($key, $value));
        return new self(...$parts->all());
    }

    // ACCESSORS ////////////////////////////////////////////////

    public function isComposite(): bool {
        return match(true) {
            $this->isNull() => false,
            ($this->parts->count() > 1) => true,
            $this->hasSingleTextContentPart() => false,
            default => true,
        };
    }

    public function isEmpty(): bool {
        return match(true) {
            $this->isNull() => true,
            $this->parts->reduce(
                fn(bool $carry, ContentPart $part) => $carry && $part->isEmpty(),
                true,
            ) => true,
            default => false,
        };
    }

    /** @return ContentPart[] @deprecated Use partsList() for collection access. */
    public function parts(): array {
        return $this->parts->all();
    }

    public function partsList(): ContentParts {
        return $this->parts;
    }

    // TRANSFORMATIONS / CONVERSIONS ////////////////////////////

    public function toArray(): array {
        return match(true) {
            $this->isNull() => [],
            default => $this->parts->toArray()
        };
    }

    public function normalized() : string|array {
        return match(true) {
            $this->isNull() => '',
            $this->isSimple() => $this->firstContentPart()?->toString() ?? '',
            default => $this->parts->toArray(),
        };
    }

    public function toString(): string {
        return match(true) {
            $this->isNull() => '',
            default => $this->parts->toString(),
        };
    }

    public function clone() : self {
        return new self(...$this->parts->all());
    }

    // UTILS /////////////////////////////////////////////////

    private function isNull(): bool {
        return $this->parts->isEmpty();
    }

    private function isSimple(): bool {
        return match(true) {
            $this->isNull() => true,
            ($this->parts->count() === 1) && ($this->firstContentPart()?->isSimple() ?? false) => true,
            default => false,
        };
    }

    private function firstContentPart(): ?ContentPart {
        return $this->parts->first();
    }

    private function lastContentPart(): ContentPart {
        if ($this->parts->isEmpty()) {
            return new ContentPart('text', ['text' => '']);
        }
        return $this->parts->last() ?? new ContentPart('text', ['text' => '']);
    }

    private function hasSingleTextContentPart() : bool {
        return ($this->parts->count() === 1)
            && ($this->firstContentPart()?->isTextPart() ?? true)
            && ($this->firstContentPart()?->isSimple() ?? true);
    }
}
