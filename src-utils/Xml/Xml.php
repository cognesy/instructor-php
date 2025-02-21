<?php
declare(strict_types=1);

namespace Cognesy\Utils\Xml;

/**
 * Class Xml
 *
 * Convert XML to array
 *
 * @package Cognesy\Utils
 */
class Xml
{
    private string $xmlString;
    private array $parsedTags = [];
    private ?array $parsedData = null;

    private function __construct(
        string $xmlString,
        array $parsedTags = [],
    ) {
        $this->xmlString = $xmlString;
    }

    /**
     * Create a new instance from XML string
     * @param string $xmlString
     * @return self
     */
    public static function from(string $xmlString): self {
        return new self($xmlString);
    }

    public function withTags(array $parsedTags): self {
        $this->parsedTags = $parsedTags;
        return $this;
    }

    public function wrapped(string $root = 'root'): self {
        $this->xmlString = "<$root>{$this->xmlString}</$root>";
        return $this;
    }

    /**
     * Return the array representation of the XML
     * @return array
     */
    public function toArray(): array {
        return $this->parsedData();
    }

    public function toXmlElement(): XmlElement {
        return XmlElement::fromArray($this->parsedData());
    }

    // INTERNAL ///////////////////////////////////////////////////

    private function parsedData(): array {
        if ($this->parsedData === null) {
            $array = match(true) {
                ($this->xmlString === '') => [],
                default => (new SelectiveXmlParser($this->parsedTags))->parse($this->xmlString),
            };
            $this->parsedData = $array[0] ?? self::empty();
        }
        return $this->parsedData;
    }

    private static function empty(): array {
        return [
            'tag' => '',
            'content' => '',
            'attributes' => [],
            'children' => [],
        ];
    }
}
