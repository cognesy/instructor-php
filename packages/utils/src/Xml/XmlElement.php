<?php declare(strict_types=1);

namespace Cognesy\Utils\Xml;

/**
 * Class XmlElement
 *
 * Represents an XML element
 */
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

    /**
     * Create an XmlElement from an array
     *
     * @param array $data
     * @return XmlElement
     */
    public static function fromArray(array $data): self {
        return new self(
            tag: $data['tag'] ?? '',
            content: $data['content'] ?? '',
            attributes: $data['attributes'] ?? [],
            children: $data['children'] ?? [],
        );
    }

    /**
     * Create an XmlElement from a SimpleXMLElement
     *
     * @param \SimpleXMLElement $element
     * @return XmlElement
     */
    public function tag(): string {
        return $this->tag;
    }

    /**
     * Create an XmlElement from a SimpleXMLElement
     *
     * @param \SimpleXMLElement $element
     * @return XmlElement
     */
    public function content(): string {
        return $this->content;
    }

    /**
     * Return the attributes of the element
     * @return string
     */
    public function attributes(): array {
        return $this->attributes;
    }

    /**
     * Return the children of the element
     * @return XmlElement[]
     */
    public function children(): array {
        return $this->children;
    }

    /**
     * Return the child with the given path
     * @param string $path
     * @return XmlElement
     */
    public function get(string $path) : XmlElement {
        $parts = explode('.', $path);
        $current = $this;
        foreach ($parts as $part) {
            $current = $current->children[$part];
        }
        return $current;
    }

    /**
     * Return the first child with the given tag
     * @param string $tag
     * @return XmlElement
     */
    public function first(string $tag): ?XmlElement {
        foreach ($this->children as $child) {
            if ($child->tag() === $tag) {
                return $child;
            }
        }
        return null;
    }

    /**
     * Return all the children with the given tag
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

    /**
     * Return the attribute with the given name
     * @param string $name
     * @param mixed $default
     * @return string
     */
    public function attribute(string $name, mixed $default = null): ?string {
        return $this->attributes[$name] ?? $default;
    }

    /**
     * Return the content of the element
     * @return string
     */
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

    /**
     * Return the content of the element
     * @return string
     */
    public function hasChildren() : bool {
        return count($this->children) > 0;
    }

    /**
     * Return the content of the element
     * @return string
     */
    public function hasContent() : bool {
        return $this->content !== '';
    }
}