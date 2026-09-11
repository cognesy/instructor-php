<?php declare(strict_types=1);

namespace Cognesy\Messages;

use Cognesy\Messages\Enums\ContentType;
use Cognesy\Messages\Support\ContentInput;
use Cognesy\Messages\Utils\Audio;
use Cognesy\Messages\Utils\File;
use Cognesy\Messages\Utils\Image;
use InvalidArgumentException;

final readonly class ContentPart
{
    protected string $type;
    /** @var array<string, mixed> */
    protected array $fields;

    public function __construct(
        string $type,
        array $fields = [],
    ) {
        $this->type = self::normalizeType($type);
        $this->fields = array_filter(
            self::normalizeFields($this->type, $fields),
            fn($value, $key) => !is_null($value) && ($value !== []),
            ARRAY_FILTER_USE_BOTH
        );
    }

    // FACTORY METHODS //////////////////////////////////////

    public static function fromArray(array $content): static {
        $type = $content['type'] ?? ContentType::Text->value;
        $fields = $content;
        unset($fields['type']);
        $fields = ContentInput::normalizeFields($type, $fields);
        return new self($type, $fields);
    }

    public static function text(string $text): static {
        return new self(ContentType::Text->value, ['text' => $text]);
    }

    public static function reasoning(string $text): static {
        return new self(ContentType::Reasoning->value, ['text' => $text]);
    }

    public static function toolCall(ToolCall $toolCall): static {
        return new self(ContentType::ToolCall->value, ['tool_call' => $toolCall]);
    }

    public static function toolResult(ToolResult $toolResult): static {
        return new self(ContentType::ToolResult->value, ['tool_result' => $toolResult]);
    }

    public static function imageUrl(string $url): static {
        return new self(ContentType::Image->value, ['image_url' => ['url' => $url]]);
    }

    public static function image(Image $image): static {
        return new self(ContentType::Image->value, $image->toContentPart()->fields());
    }

    public static function file(File $file): static {
        return new self(ContentType::File->value, $file->toContentPart()->fields());
    }

    public static function audio(Audio $audio): static {
        return new self(ContentType::Audio->value, $audio->toContentPart()->fields());
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
        return array_map(self::exportValue(...), $this->fields);
    }

    public function isTextPart(): bool {
        return $this->type === ContentType::Text->value;
    }

    public function isReasoningPart(): bool {
        return $this->type === ContentType::Reasoning->value;
    }

    public function isToolCallPart(): bool {
        return $this->type === ContentType::ToolCall->value;
    }

    public function isToolResultPart(): bool {
        return $this->type === ContentType::ToolResult->value;
    }

    public function isRenderableContentPart(): bool {
        return !$this->isReasoningPart()
            && !$this->isToolCallPart()
            && !$this->isToolResultPart();
    }

    public function reasoningText(): string {
        return $this->isReasoningPart() && $this->hasText() ? $this->fields['text'] : '';
    }

    public function toToolCall(): ?ToolCall {
        $toolCall = $this->fields['tool_call'] ?? null;
        return $this->isToolCallPart() && $toolCall instanceof ToolCall ? $toolCall : null;
    }

    public function toToolResult(): ?ToolResult {
        $toolResult = $this->fields['tool_result'] ?? null;
        return $this->isToolResultPart() && $toolResult instanceof ToolResult ? $toolResult : null;
    }

    public function hasText(): bool {
        return isset($this->fields['text']) && is_string($this->fields['text']);
    }

    public function get(string $key, mixed $default = null): mixed {
        return array_key_exists($key, $this->fields)
            ? self::exportValue($this->fields[$key])
            : $default;
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
            $data[$key] = self::exportValue($value);
        }
        return $data;
    }

    public function toString(): string {
        // Non-string 'text' collapses to '' rather than throwing: this runs on provider
        // serialization paths, where a TypeError is worse than an empty part, and
        // isEmpty()/withoutEmpty() already prune empties downstream. hasText() is the
        // single source of truth for "has usable text".
        return $this->hasText() ? $this->fields['text'] : "";
    }

    // INTERNAL /////////////////////////////////////////////

    private function shouldExport(string|int $key, mixed $value): bool {
        return !is_null($value)
            && ($value !== '')
            && ($value !== [])
            && (is_string($key) ? (str_starts_with($key, '_') === false) : true);
    }

    private static function normalizeType(string $type): string {
        $contentType = ContentType::tryFrom($type);
        return match ($contentType) {
            null => $type,
            default => $contentType->value,
        };
    }

    /** @param array<string, mixed> $fields */
    private static function normalizeFields(string $type, array $fields): array {
        return match (true) {
            $type === ContentType::ToolCall->value && array_key_exists('tool_call', $fields)
                => self::withToolCallPayload($fields),
            $type === ContentType::ToolResult->value && array_key_exists('tool_result', $fields)
                => self::withToolResultPayload($fields),
            default => $fields,
        };
    }

    /** @param array<string, mixed> $fields */
    private static function withToolCallPayload(array $fields): array {
        $fields['tool_call'] = match (true) {
            $fields['tool_call'] instanceof ToolCall => $fields['tool_call'],
            is_array($fields['tool_call']) => ToolCall::fromArray($fields['tool_call']),
            default => throw new InvalidArgumentException('tool_call content must contain a ToolCall or array.'),
        };
        return $fields;
    }

    /** @param array<string, mixed> $fields */
    private static function withToolResultPayload(array $fields): array {
        $fields['tool_result'] = match (true) {
            $fields['tool_result'] instanceof ToolResult => $fields['tool_result'],
            is_array($fields['tool_result']) => ToolResult::fromArray($fields['tool_result']),
            default => throw new InvalidArgumentException('tool_result content must contain a ToolResult or array.'),
        };
        return $fields;
    }

    private static function exportValue(mixed $value): mixed {
        return match (true) {
            $value instanceof ToolCall => $value->toArray(),
            $value instanceof ToolResult => $value->toArray(),
            default => $value,
        };
    }
}
