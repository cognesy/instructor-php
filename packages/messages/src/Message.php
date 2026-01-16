<?php declare(strict_types=1);

namespace Cognesy\Messages;

use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Messages\ContentParts;
use Cognesy\Messages\Support\MessageInput;
use Cognesy\Messages\Utils\Image;
use Cognesy\Utils\Metadata;

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
 * Each chat message is uniquely identified by an ID, which is generated
 * in the constructor.
 *
 */
final readonly class Message
{
    public const DEFAULT_ROLE = 'user';

    protected string $role;
    protected string $name;
    protected Content $content;
    protected Metadata $metadata;

    /**
     * @param string|MessageRole|null $role
     * @param string|array|Content|null $content
     * @param string $name
     * @param Metadata|array<string,mixed> $metadata
     */
    public function __construct(
        string|MessageRole|null $role = '',
        string|array|Content|null $content = null,
        string $name = '',
        Metadata|array $metadata = [],
    ) {
        $this->role = match (true) {
            $role instanceof MessageRole => $role->value,
            ($role === '') || is_null($role) => self::DEFAULT_ROLE,
            default => $role,
        };
        $this->name = $name;
        $this->content = Content::fromAny($content);
        $this->metadata = match(true) {
            $metadata instanceof Metadata => $metadata,
            is_array($metadata) => Metadata::fromArray($metadata),
            default => throw new \InvalidArgumentException('Metadata must be an array or Metadata instance.'),
        };
    }

    // CONSTRUCTORS ///////////////////////////////////////

    public static function empty() {
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

    public function clone(): self {
        return new static(
            role: $this->role,
            content: $this->content->clone(),
            name: $this->name,
            metadata: $this->metadata->toArray(),
        );
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

    // ///////////////////////////////////////

    public function name(): string {
        return $this->name ?? '';
    }

    public function content(): Content {
        return $this->content;
    }

    public function contentParts(): ContentParts {
        return $this->content->partsList();
    }

    public function isEmpty(): bool {
        return $this->content->isEmpty()
            && $this->metadata()->isEmpty();
    }

    public function isComposite(): bool {
        return $this->content->isComposite();
    }

    public function metadata(): Metadata {
        return $this->metadata;
    }

    public function withMetadata(string $key, mixed $value): self {
        return new self(
            role: $this->role,
            content: $this->content,
            name: $this->name,
            metadata: $this->metadata->withKeyValue($key, $value),
        );
    }

    // MUTATORS ///////////////////////////////////////

    public function withContent(Content $content): Message {
        return new Message(
            role: $this->role,
            content: $content,
            name: $this->name,
            metadata: $this->metadata,
        );
    }

    public function withName(string $name): Message {
        return new Message(
            role: $this->role,
            content: $this->content,
            name: $name,
            metadata: $this->metadata,
        );
    }

    public function withRole(string|MessageRole $role): Message {
        $role = match (true) {
            is_string($role) => $role,
            $role instanceof MessageRole => $role->value,
        };
        return new Message(
            role: $role,
            content: $this->content,
            name: $this->name,
            metadata: $this->metadata,
        );
    }

    public function addContentFrom(Message $source): Message {
        $newContent = $this->content->clone();
        foreach ($source->content()->partsList()->all() as $part) {
            $newContent = $newContent->addContentPart($part);
        }
        return $this->withContent($newContent);
    }

    public function addContentPart(string|array|ContentPart $part): Message {
        $newContent = $this->content->addContentPart(ContentPart::fromAny($part));
        return $this->withContent($newContent);
    }

    // CONVERSIONS / TRANSFORMATIONS ///////////////////////////////////////

    public function toArray(): array {
        return array_filter([
            'role' => $this->role,
            'name' => $this->name,
            'content' => match (true) {
                $this->content->isEmpty() => '',
                $this->content->isComposite() => $this->content->toArray(),
                default => $this->content->toString(),
            },
            '_metadata' => $this->metadata->toArray(),
        ]);
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
            isset($message['content']) || isset($message['_metadata'])
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
        return is_array($message['content']);
    }
}

