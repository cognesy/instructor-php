<?php

namespace Cognesy\Utils\Xml;

class XmlElement
{
    private string $tag = '';
    private string $content = '';
    private array $attributes = [];
    private array $children = [];

    public function __construct(
        string $tag,
        string $content,
        array  $attributes,
        array  $children,
    ) {
        $this->tag = $tag;
        $this->content = $content;
        $this->attributes = $attributes;
        foreach ($children as $child) {
            $this->children[] = self::fromArray($child);
        }
    }

    public static function fromArray(array $data): self {
        return new self(
            tag: $data['tag'] ?? '',
            content: $data['content'] ?? '',
            attributes: $data['attributes'] ?? [],
            children: $data['children'] ?? [],
        );
    }

    public function tag(): string {
        return $this->tag;
    }

    public function content(): string {
        return $this->content;
    }

    public function attributes(): array {
        return $this->attributes;
    }

    /**
     * @return XmlElement[]
     */
    public function children(): array {
        return $this->children;
    }

    public function get(string $path) : XmlElement {
        $parts = explode('.', $path);
        $current = $this;
        foreach ($parts as $part) {
            $current = $current->children[$part];
        }
        return $current;
    }

    public function first(string $tag): ?XmlElement {
        foreach ($this->children as $child) {
            if ($child->tag() === $tag) {
                return $child;
            }
        }
        return null;
    }

    /**
     * @return XmlElement[]
     */
    public function all(string $tag): array {
        $result = [];
        foreach ($this->children as $child) {
            if ($child->tag() === $tag) {
                $result[] = $child;
            }
        }
        return $result;
    }

    public function attribute(string $name, mixed $default = null): ?string {
        return $this->attributes[$name] ?? $default;
    }

    public function toArray(): array {
        $children = [];
        foreach ($this->children as $child) {
            $children[] = $child->toArray();
        }
        return [
            'tag' => $this->tag,
            'content' => $this->content,
            'attributes' => $this->attributes,
            'children' => $children,
        ];
    }

    public function hasChildren() : bool {
        return count($this->children) > 0;
    }

    public function hasContent() : bool {
        return $this->content !== '';
    }
}