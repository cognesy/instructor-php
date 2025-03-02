<?php

namespace Cognesy\Utils\Messages\Traits\Message;

use Cognesy\Utils\Messages\Enums\MessageRole;

trait HandlesAccess
{
    public static function becomesComposite(array $message) : bool {
        return is_array($message['content']);
    }

    public static function hasRoleAndContent(array $message) : bool {
        return isset($message['role']) && (
            isset($message['content']) || isset($message['_metadata'])
        );
    }

    public function id() : string {
        return $this->id;
    }

    public function role() : MessageRole {
        return MessageRole::fromString($this->role);
    }

    public function name() : string {
        return $this->name;
    }

    public function content() : string|array {
        return $this->content;
    }

    public function isEmpty() : bool {
        return empty($this->content) && !$this->hasMeta();
    }

    public function isNull() : bool {
        return ($this->role === '' && $this->content === '');
    }

    public function isComposite() : bool {
        return is_array($this->content);
    }

    public function hasMeta(string $key = null) : bool {
        return match(true) {
            $key === null => !empty($this->metadata),
            default => isset($this->metadata[$key]),
        };
    }

    public function meta(string $key = null) : mixed {
        return match(true) {
            $key === null => $this->metadata,
            default => $this->metadata[$key] ?? null,
        };
    }

    public function metaKeys() : array {
        return array_keys($this->metadata);
    }

    public function withMeta(array $metadata) : self {
        $this->metadata = $metadata;
        return $this;
    }

    public function withMetaValue(string $key, mixed $value) : self {
        $this->metadata[$key] = $value;
        return $this;
    }
}