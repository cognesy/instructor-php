<?php declare(strict_types=1);

namespace Cognesy\Messages;

use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Messages\Enums\MessageType;
use Cognesy\Messages\ContentParts;
use Cognesy\Messages\Support\MessageInput;
use Cognesy\Messages\Utils\Image;
use Cognesy\Utils\Metadata;
use DateTimeImmutable;

/**
 * Represents a message entity with role, content, and metadata properties.
 *
 * This class provides functionality for creating and managing a message,
 * where the role determines the message's purpose or origin, the content
 * holds the message data, and the metadata contains additional contextual
 * information.
 *
 * It supports complex message content fields like images or audio, and
 * multipart text content, so it can represent a wide range of language
 * model APIs across various LLM providers.
 *
 * Metadata can be used to store arbitrary values needed by an application,
 * such as sources, internal reasoning traces. They are not explicitly rendered
 * to a message content sent to a language model.
 *
 * Each chat message is uniquely identified by an immutable ID, which is generated
 * in the constructor. The ID and createdAt timestamp are preserved across all
 * mutations (with*() methods).
 *
 */
final readonly class Message
{
    public const DEFAULT_ROLE = 'user';

    public MessageId $id;
    public DateTimeImmutable $createdAt;

    protected string $role;
    protected string $name;
    protected ContentParts $parts;
    protected Content $content;
    protected ToolCalls $toolCalls;
    protected ?ToolResult $toolResult;
    protected string $reasoningContent;
    protected MessageType $type;
    protected bool $isEmpty;
    protected bool $isComposite;
    protected Metadata $metadata;
    protected ?MessageId $parentId;

    /**
     * @param string|MessageRole|null $role
     * @param string|array|Content|null $content
     * @param string $name
     * @param Metadata|array<string,mixed> $metadata
     * @param MessageId|null $parentId Parent message ID for branching support (Pi-Mono style)
     * @param MessageId|null $id For deserialization - if null, generates new UUID
     * @param DateTimeImmutable|null $createdAt For deserialization - if null, uses current time
     */
    public function __construct(
        string|MessageRole|null $role = '',
        string|array|Content|null $content = null,
        string $name = '',
        Metadata|array $metadata = [],
        ?MessageId $parentId = null,
        // Identity fields - for deserialization
        ?MessageId $id = null,
        ?DateTimeImmutable $createdAt = null,
        ?ContentParts $parts = null,
    ) {
        $this->id = $id ?? MessageId::generate();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();

        // Roles are validated here, at the construction site that supplied them. Storing
        // an unknown role and letting role() throw later surfaced the error far from its
        // cause, and survived toArray() so it round-tripped through storage undetected.
        $this->role = match (true) {
            $role instanceof MessageRole => $role->value,
            ($role === '') || is_null($role) => self::DEFAULT_ROLE,
            default => MessageRole::fromString($role)->value,
        };
        $this->name = $name;
        $this->parts = $parts ?? Content::fromAny($content)->partsList();
        $this->metadata = match(true) {
            $metadata instanceof Metadata => $metadata,
            is_array($metadata) => Metadata::fromArray($metadata),
            default => throw new \InvalidArgumentException('Metadata must be an array or Metadata instance.'),
        };
        $this->parentId = $parentId;

        $renderableParts = [];
        $reasoningParts = [];
        $toolCalls = [];
        $toolResult = null;
        $hasNonEmptyPart = false;
        foreach ($this->parts as $part) {
            $hasNonEmptyPart = $hasNonEmptyPart || !$part->isEmpty();
            if ($part->isRenderableContentPart()) {
                $renderableParts[] = $part;
            }
            if ($part->isReasoningPart()) {
                $reasoningParts[] = $part;
            }
            $toolCall = $part->toToolCall();
            if ($toolCall !== null) {
                $toolCalls[] = $toolCall;
            }
            $toolResult ??= $part->toToolResult();
        }

        $this->content = new Content(...$renderableParts);
        $this->toolCalls = new ToolCalls(...$toolCalls);
        $this->toolResult = $toolResult;
        $this->reasoningContent = (new ContentParts(...$reasoningParts))->toString();
        $this->type = match (true) {
            $this->role === MessageRole::Assistant->value && $this->toolCalls->hasAny()
                => MessageType::AssistantToolCalls,
            $this->role === MessageRole::Tool->value && $this->toolResult !== null
                => MessageType::ToolResult,
            default => MessageType::Text,
        };
        $this->isEmpty = !$hasNonEmptyPart && $this->metadata->isEmpty();
        $this->isComposite = $this->content->isComposite();
    }

    // CONSTRUCTORS ///////////////////////////////////////

    public static function empty(): self {
        return new self(
            role: self::DEFAULT_ROLE,
            content: Content::empty(),
        );
    }

    public static function make(string $role, string|array|Content $content, string $name = ''): Message {
        return new Message(role: $role, content: $content, name: $name);
    }

    public static function asUser(string|array|Message $message, string $name = ''): static {
        return MessageInput::fromAny($message, MessageRole::User, $name);
    }

    public static function asAssistant(string|array|Message $message, string $name = ''): static {
        return MessageInput::fromAny($message, MessageRole::Assistant, $name);
    }

    public static function asSystem(string|array|Message $message, string $name = ''): static {
        return MessageInput::fromAny($message, MessageRole::System, $name);
    }

    public static function asDeveloper(string|array|Message $message, string $name = ''): static {
        return MessageInput::fromAny($message, MessageRole::Developer, $name);
    }

    public static function asTool(string|array|Message $message, string $name = ''): static {
        return MessageInput::fromAny($message, MessageRole::Tool, $name);
    }

    public static function fromAny(
        string|array|Message|Messages|Content|ContentPart|ContentParts $message,
        string|MessageRole|null $role = null,
        string $name = '',
    ): static {
        return MessageInput::fromAny($message, $role, $name);
    }

    public static function fromString(
        string $content,
        string $role = self::DEFAULT_ROLE,
        string $name = '',
    ): static {
        return MessageInput::fromAny($content, $role, $name);
    }

    public static function fromArray(array $message): static {
        return MessageInput::fromArray($message);
    }

    public static function fromContent(Content $content, string|MessageRole|null $role = null): static {
        return MessageInput::fromAny($content, $role);
    }

    public static function fromContentPart(ContentPart $part, string|MessageRole|null $role = null): static {
        return MessageInput::fromAny($part, $role);
    }

    public static function fromInput(string|array|object $input, string $role = ''): static {
        return MessageInput::fromInput($input, $role);
    }

    public static function fromImage(Image $image, string $role = ''): static {
        return new static(role: $role, content: $image->toContent());
    }

    // ACCESSORS ///////////////////////////////////////

    public function role(): MessageRole {
        return MessageRole::fromString($this->role);
    }

    // ROLE CONVENIENCE HELPERS ///////////////////////////////////////

    public function isUser(): bool {
        return $this->role() === MessageRole::User;
    }

    public function isAssistant(): bool {
        return $this->role() === MessageRole::Assistant;
    }

    public function isTool(): bool {
        return $this->role() === MessageRole::Tool;
    }

    public function isSystem(): bool {
        return $this->role()->isSystem(); // Covers System and Developer
    }

    public function isDeveloper(): bool {
        return $this->role() === MessageRole::Developer;
    }

    public function hasRole(MessageRole ...$roles): bool {
        return $this->role()->oneOf(...$roles);
    }

    public function type(): MessageType {
        return $this->type;
    }

    // TOOL ACCESSORS ///////////////////////////////////////

    public function hasToolCalls(): bool {
        return $this->toolCalls->hasAny();
    }

    public function toolCalls(): ToolCalls {
        return $this->toolCalls;
    }

    public function hasToolResult(): bool {
        return $this->toolResult !== null;
    }

    public function toolResult(): ?ToolResult {
        return $this->toolResult;
    }

    // ///////////////////////////////////////

    public function name(): string {
        return $this->name;
    }

    public function content(): Content {
        return $this->content;
    }

    public function contentParts(): ContentParts {
        return $this->content->partsList();
    }

    public function parts(): ContentParts {
        return $this->parts;
    }

    public function reasoningContent(): string {
        return $this->reasoningContent;
    }

    public function isEmpty(): bool {
        return $this->isEmpty;
    }

    public function isComposite(): bool {
        return $this->isComposite;
    }

    public function metadata(): Metadata {
        return $this->metadata;
    }

    public function parentId(): ?MessageId {
        return $this->parentId;
    }

    public function id(): MessageId {
        return $this->id;
    }

    public function withMetadata(string $key, mixed $value): self {
        return new self(
            role: $this->role,
            content: null,
            name: $this->name,
            metadata: $this->metadata->withKeyValue($key, $value),
            parentId: $this->parentId,
            id: $this->id,
            createdAt: $this->createdAt,
            parts: $this->parts,
        );
    }

    // MUTATORS ///////////////////////////////////////

    public function withContent(Content $content): self {
        return $this->withParts($this->replaceParts(
            fn(ContentPart $part) => $part->isRenderableContentPart(),
            $content->partsList(),
            true,
        ));
    }

    public function withReasoningContent(string $reasoningContent): self {
        $replacement = match ($reasoningContent) {
            '' => ContentParts::empty(),
            default => new ContentParts(ContentPart::reasoning($reasoningContent)),
        };
        return $this->withParts($this->replaceParts(
            fn(ContentPart $part) => $part->isReasoningPart(),
            $replacement,
            true,
        ));
    }

    public function withParts(ContentParts $parts): self {
        return new self(
            role: $this->role,
            content: null,
            name: $this->name,
            metadata: $this->metadata,
            parentId: $this->parentId,
            id: $this->id,
            createdAt: $this->createdAt,
            parts: $parts,
        );
    }

    public function withName(string $name): self {
        return new self(
            role: $this->role,
            content: null,
            name: $name,
            metadata: $this->metadata,
            parentId: $this->parentId,
            id: $this->id,
            createdAt: $this->createdAt,
            parts: $this->parts,
        );
    }

    /** @throws \InvalidArgumentException when $role is not a known MessageRole */
    public function withRole(string|MessageRole $role): self {
        return new self(
            role: MessageRole::fromAny($role),
            content: null,
            name: $this->name,
            metadata: $this->metadata,
            parentId: $this->parentId,
            id: $this->id,
            createdAt: $this->createdAt,
            parts: $this->parts,
        );
    }

    public function withParentId(?MessageId $parentId): self {
        return new self(
            role: $this->role,
            content: null,
            name: $this->name,
            metadata: $this->metadata,
            parentId: $parentId,
            id: $this->id,
            createdAt: $this->createdAt,
            parts: $this->parts,
        );
    }

    public function withToolCalls(ToolCalls $toolCalls): self {
        return $this->withParts($this->replaceParts(
            fn(ContentPart $part) => $part->isToolCallPart(),
            new ContentParts(...array_map(ContentPart::toolCall(...), $toolCalls->all())),
        ));
    }

    public function withToolResult(ToolResult $toolResult): self {
        return $this->withParts($this->replaceParts(
            fn(ContentPart $part) => $part->isToolResultPart(),
            new ContentParts(ContentPart::toolResult($toolResult)),
        ));
    }

    public function addContentFrom(Message $source): self {
        $newContent = $this->content();
        foreach ($source->content()->partsList()->all() as $part) {
            $newContent = $newContent->addContentPart($part);
        }
        return $this->withContent($newContent);
    }

    /**
     * Folds $source into this message: content parts are appended, tool calls are
     * concatenated, and $source's metadata keys win on conflict. Identity (id, parentId,
     * createdAt), role and name stay with this message - it is being extended, not
     * replaced - so a merged message keeps its place in a stored parentId chain.
     *
     * Tool RESULTS are never merged: a tool result is bound to one tool call id, and
     * folding two together would silently drop that binding. Callers must keep such
     * messages separate; see Messages::toMergedPerRole().
     *
     * @throws \InvalidArgumentException if either message carries a tool result
     */
    public function withMergedFrom(Message $source): self {
        if ($this->hasToolResult() || $source->hasToolResult()) {
            throw new \InvalidArgumentException(
                'Cannot merge messages carrying a tool result - a tool result is bound to a single tool call id.'
            );
        }
        return new self(
            role: $this->role,
            content: null,
            name: $this->name,
            metadata: $this->metadata->withMergedData($source->metadata()->toArray()),
            parentId: $this->parentId,
            id: $this->id,
            createdAt: $this->createdAt,
            parts: $this->parts->append($source->parts()),
        );
    }

    public function addContentPart(string|array|ContentPart $part): self {
        return $this->withParts($this->parts->add(ContentPart::fromAny($part)));
    }

    /**
     * @param callable(ContentPart): bool $matches
     */
    private function replaceParts(
        callable $matches,
        ContentParts $replacement,
        bool $prependWhenMissing = false,
    ): ContentParts {
        $result = [];
        $inserted = false;
        foreach ($this->parts as $part) {
            if (!$matches($part)) {
                $result[] = $part;
                continue;
            }
            if (!$inserted) {
                $result = [...$result, ...$replacement->all()];
                $inserted = true;
            }
        }
        if (!$inserted) {
            $result = match ($prependWhenMissing) {
                true => [...$replacement->all(), ...$result],
                false => [...$result, ...$replacement->all()],
            };
        }
        return new ContentParts(...$result);
    }

    // CONVERSIONS / TRANSFORMATIONS ///////////////////////////////////////

    public function toArray(): array {
        $data = [
            'id' => $this->id->toString(),
            'createdAt' => $this->createdAt->format(DateTimeImmutable::ATOM),
            'parentId' => $this->parentId !== null ? (string) $this->parentId : null,
            'role' => $this->role,
            'name' => $this->name,
            'parts' => $this->parts->toArray(),
            '_metadata' => $this->metadata->toArray(),
        ];

        if ($data['parentId'] === null) {
            unset($data['parentId']);
        }
        if ($data['name'] === '') {
            unset($data['name']);
        }
        if ($data['_metadata'] === []) {
            unset($data['_metadata']);
        }

        return $data;
    }

    public function toString(): string {
        return $this->content->toString();
    }

    // UTILITIES ///////////////////////////////////////

    /**
     * Checks if given array is OpenAI formatted message
     *
     * @param array $message
     * @return bool
     */
    public static function isMessage(array $message): bool {
        return isset($message['role']) && (
            isset($message['content']) || isset($message['parts']) || isset($message['_metadata'])
        );
    }

    /**
     * Checks if given array is OpenAI array of formatted messages
     *
     * @param array $messages
     * @return bool
     */
    public static function isMessages(array $messages): bool {
        foreach ($messages as $message) {
            if (!self::isMessage($message)) {
                return false;
            }
        }
        return true;
    }

    public static function becomesComposite(array $message): bool {
        return is_array($message['content'] ?? null);
    }

}
