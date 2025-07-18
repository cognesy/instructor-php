<?php declare(strict_types=1);

namespace Cognesy\Messages\V2;

use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;
use Stringable;

final readonly class Message implements Stringable
{
    public string $id;
    public MessageRole $role;
    public Content $content;
    public array $metadata;
    public ?DateTimeImmutable $createdAt;
    public ?string $parentId;
    public ?array $toolCallId;

    public function __construct(
        string $id = null,
        MessageRole $role = null,
        Content $content = null,
        array $metadata = [],
        ?DateTimeImmutable $createdAt = null,
        ?string $parentId = null,
        ?array $toolCallId = null
    ) {
        $this->id = $id ?? self::generateId();
        $this->role = $role ?? MessageRole::User;
        $this->content = Content::create($content);
        $this->metadata = $metadata;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->parentId = $parentId;
        $this->toolCallId = $toolCallId ?? null;
    }

    // CREATION METHODS ///////////////////////////////////

    public static function empty(): self {
        return new self(
            id: self::generateId(),
            role: MessageRole::User,
            content: Content::empty(),
            createdAt: new DateTimeImmutable()
        );
    }

    public static function system(string|Content|array $content, ?string $id = null): self {
        return self::create(MessageRole::System, $content, $id);
    }

    public static function developer(string|Content|array $content, ?string $id = null): self {
        return self::create(MessageRole::Developer, $content, $id);
    }

    public static function user(string|Content|array $content, ?string $id = null): self {
        return self::create(MessageRole::User, $content, $id);
    }

    public static function assistant(string|Content|array $content, ?string $id = null): self {
        return self::create(MessageRole::Assistant, $content, $id);
    }

    public static function tool(string|Content|array $content, ?string $id = null): self {
        return self::create(MessageRole::Tool, $content, $id);
    }

    public static function create(MessageRole $role, string|Content|array $content, ?string $id = null): self {
        return new self(
            id: $id ?? self::generateId(),
            role: $role,
            content: Content::create($content),
            createdAt: new DateTimeImmutable()
        );
    }

    public static function as(MessageRole $role, string|Content $content): self {
        return self::create($role, $content);
    }

    // MULTIPART CREATION ////////////////////////////////

    public static function multipart(MessageRole $role, ContentPart ...$parts): self {
        return new self(
            id: self::generateId(),
            role: $role,
            content: Content::parts(...$parts),
            createdAt: new DateTimeImmutable()
        );
    }

    public static function withImage(MessageRole $role, string $text, string $imageUrl): self {
        return self::multipart(
            $role,
            ContentPart::text($text),
            ContentPart::image($imageUrl)
        );
    }

    public static function withFile(MessageRole $role, string $text, string $filePath): self {
        return self::multipart(
            $role,
            ContentPart::text($text),
            ContentPart::file($filePath)
        );
    }

    // ACCESS METHODS ////////////////////////////////////

    public function isSystem(): bool {
        return $this->role === MessageRole::System;
    }

    public function isDeveloper(): bool {
        return $this->role === MessageRole::Developer;
    }

    public function isUser(): bool {
        return $this->role === MessageRole::User;
    }

    public function isAssistant(): bool {
        return $this->role === MessageRole::Assistant;
    }

    public function isTool(): bool {
        return $this->role === MessageRole::Tool;
    }

    public function hasMetadata(string $key): bool {
        return isset($this->metadata[$key]);
    }

    public function getMetadata(string $key, mixed $default = null): mixed {
        return $this->metadata[$key] ?? $default;
    }

    public function toArray(): array {
        $array = [
            'id' => $this->id,
            'role' => $this->role->value,
            'content' => $this->content->toArray(),
        ];
        if (!empty($this->metadata)) {
            $array['metadata'] = $this->metadata;
        }
        if ($this->createdAt) {
            $array['createdAt'] = $this->createdAt->format('c');
        }
        if ($this->parentId) {
            $array['parentId'] = $this->parentId;
        }
        if ($this->toolCallId) {
            $array['toolCallId'] = $this->toolCallId;
        }
        return $array;
    }

    public function __toString(): string {
        return $this->content->__toString();
    }

    // MUTATION METHODS //////////////////////////////////

    public function withId(string $id): self {
        return new self(
            id: $id,
            role: $this->role,
            content: $this->content,
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            parentId: $this->parentId,
            toolCallId: $this->toolCallId
        );
    }

    public function withRole(MessageRole $role): self {
        return new self(
            id: $this->id,
            role: $role,
            content: $this->content,
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            parentId: $this->parentId,
            toolCallId: $this->toolCallId
        );
    }

    public function withContent(string|Content|array $content): self {
        return new self(
            id: $this->id,
            role: $this->role,
            content: Content::create($content),
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            parentId: $this->parentId,
            toolCallId: $this->toolCallId
        );
    }

    public function addContentPart(ContentPart ...$parts): self {
        return new self(
            id: $this->id,
            role: $this->role,
            content: $this->content->addPart(...$parts),
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            parentId: $this->parentId,
            toolCallId: $this->toolCallId
        );
    }

    public function addText(string $text): self {
        return $this->addContentPart(ContentPart::text($text));
    }

    public function addImage(string $url): self {
        return $this->addContentPart(ContentPart::image($url));
    }

    public function addFile(string $path): self {
        return $this->addContentPart(ContentPart::file($path));
    }

    public function withMetadata(string $key, mixed $value): self {
        return new self(
            id: $this->id,
            role: $this->role,
            content: $this->content,
            metadata: [...$this->metadata, $key => $value],
            createdAt: $this->createdAt,
            parentId: $this->parentId,
            toolCallId: $this->toolCallId
        );
    }

    public function withoutMetadata(string $key): self {
        $metadata = $this->metadata;
        unset($metadata[$key]);
        return new self(
            id: $this->id,
            role: $this->role,
            content: $this->content,
            metadata: $metadata,
            createdAt: $this->createdAt,
            parentId: $this->parentId,
            toolCallId: $this->toolCallId
        );
    }

    public function withParent(string $parentId): self {
        return new self(
            id: $this->id,
            role: $this->role,
            content: $this->content,
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            parentId: $parentId,
            toolCallId: $this->toolCallId
        );
    }

    public function withToolCallId(string $toolCallId): self {
        return new self(
            id: $this->id,
            role: $this->role,
            content: $this->content,
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            parentId: $this->parentId,
            toolCallId: [$toolCallId]
        );
    }

    // INTERNAL /////////////////////////////////////////

    private static function generateId(): string {
        return 'msg_' . Uuid::uuid4()->toString();
    }
}