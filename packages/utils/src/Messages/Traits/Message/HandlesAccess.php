<?php declare(strict_types=1);

namespace Cognesy\Utils\Messages\Traits\Message;

use Cognesy\Utils\Messages\Content;
use Cognesy\Utils\Messages\ContentPart;
use Cognesy\Utils\Messages\Enums\MessageRole;

trait HandlesAccess
{
    // STATIC ///////////////////////////////////////

    public static function becomesComposite(array $message) : bool {
        return is_array($message['content']);
    }

    public static function hasRoleAndContent(array $message) : bool {
        return isset($message['role']) && (
            isset($message['content']) || isset($message['_metadata'])
        );
    }

    // PUBLIC ///////////////////////////////////////

    public function role() : MessageRole {
        return MessageRole::fromString($this->role);
    }

    public function name() : string {
        return $this->name ?? '';
    }

    public function content() : Content {
        return $this->content;
    }

    /**
     * @return ContentPart[]
     */
    public function contentParts() : array {
        return $this->content->parts();
    }

    public function lastContentPart() : ?ContentPart {
        return $this->content->lastContentPart();
    }

    public function firstContentPart() : ?ContentPart {
        return $this->content->firstContentPart();
    }

    public function isEmpty() : bool {
        return $this->content->isEmpty()
            && !$this->hasMeta();
    }

    public function isNull() : bool {
        return $this->role === ''
            && $this->content->isEmpty()
            && empty($this->metadata);
    }

    public function isComposite() : bool {
        return $this->content->isComposite();
    }

    public function hasMeta(?string $key = null) : bool {
        return match(true) {
            ($key === null) => !empty($this->metadata),
            default => isset($this->metadata[$key]),
        };
    }

    public function meta(?string $key = null) : mixed {
        return match(true) {
            $key === null => $this->metadata,
            default => $this->metadata[$key] ?? null,
        };
    }

    public function metadata(?string $key = null) : mixed {
        return $this->meta($key);
    }

    public function metaKeys() : array {
        return array_keys($this->metadata);
    }

    public function withMeta(string $key, mixed $value) : self {
        $this->metadata[$key] = $value;
        return $this;
    }

    public function withMetadata(string $key, mixed $value) : self {
        $this->withMeta($key, $value);
        return $this;
    }
}