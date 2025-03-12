<?php

namespace Cognesy\Polyglot\LLM\Data;

use Cognesy\Polyglot\LLM\Enums\LLMContentType;
use Cognesy\Utils\Uuid;

use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class ContentBlock
{
    private $thinkingTag = 'thinking';
    private $citationTag = 'citation';

    public function __construct(
        public string $id = '',
        public string $content = '',
        public LLMContentType $type = LLMContentType::Text,
        public array $data = [],
    ) {
        $this->id = $id ?: Uuid::uuid4();
    }

    public function withId(string $id) : self {
        $this->id = $id;
        return $this;
    }

    public function withContent(string $content) : self {
        $this->content = $content;
        return $this;
    }

    public function withType(LLMContentType $type) : self {
        $this->type = $type;
        return $this;
    }

    public function withData(array $data) : self {
        $this->data = $data;
        return $this;
    }

    public function content() : string {
        return match($this->type) {
            LLMContentType::Text => $this->content,
            default => ''
        };
    }

    public function toString() : string {
        return match($this->type) {
            LLMContentType::Text => $this->content,
            LLMContentType::Thinking => $this->wrap($this->content, $this->thinkingTag),
            LLMContentType::Citation => $this->wrap($this->content, $this->citationTag),
            default => ''
        };
    }

    public function toArray() : array {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'type' => $this->type,
            'data' => $this->data,
        ];
    }

    private function wrap(string $content, string $tag) : string {
        return "<$tag>" . $content . "</$tag>";
    }
}