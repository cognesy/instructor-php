<?php declare(strict_types=1);

namespace Cognesy\Utils\Xml;

use Cognesy\Utils\Str;
use SimpleXMLElement;

/**
 * Class SimpleXmlParser
 * Supports reading XML string and convert it to array
 */
class SimpleXmlParser
{
    private ?array $parsedData = null;

    public function __construct(
        private string $xmlString,
        private bool $includeAttributes = false,
        private bool $includeRoot = false,
        private string $namingConvention = 'raw',
    ) {}

    /**
     * Create a new instance of SimpleXmlParser from XML string
     * @param string $xmlString
     * @return SimpleXmlParser
     */
    public static function from(string $xmlString): self {
        return new self($xmlString);
    }

    /**
     * Sets inclusion of attributes in the result array
     * @param string $xmlString
     * @return SimpleXmlParser
     */
    public function withAttributes(): self {
        $this->includeAttributes = true;
        return $this;
    }

    /**
     * Sets inclusion of root element in the result array
     * @param string $xmlString
     * @return SimpleXmlParser
     */
    public function withRoot(): self {
        $this->includeRoot = true;
        return $this;
    }

    /**
     * Wraps the XML string with a root element
     * @param string $xmlString
     * @return SimpleXmlParser
     */
    public function wrapped(string $root = 'root'): self {
        $this->xmlString = "<$root>{$this->xmlString}</$root>";
        return $this;
    }

    /**
     * Sets the naming convention for the keys in the result array to camelCase
     * @param string $xmlString
     * @return SimpleXmlParser
     */
    public function asCamelCase(): self {
        $this->namingConvention = 'camel';
        return $this;
    }

    /**
     * Sets the naming convention for the keys in the result array to snake_case
     * @param string $xmlString
     * @return SimpleXmlParser
     */
    public function asSnakeCase(): self {
        $this->namingConvention = 'snake';
        return $this;
    }

    /**
     * Sets the naming convention for the keys in the result array to provided value
     * @param string $xmlString
     * @return SimpleXmlParser
     */
    public function withNaming(string $namingConvention): self {
        $this->namingConvention = $namingConvention;
        return $this;
    }

    /**
     * Convert the XML string to array
     * @return array
     */
    public function toArray(): array {
        if ($this->parsedData === null) {
            $this->parsedData = $this->convertXmlToArray();
        }
        return $this->parsedData;
    }

    /**
     * Convert the XML string to array
     * @return string
     */
    private function convertXmlToArray(): array {
        if ($this->xmlString === '') {
            return [];
        }
        $xmlElement = new SimpleXMLElement($this->xmlString, LIBXML_NOCDATA);
        $array = $this->xmlToArray($xmlElement);
        if ($this->includeRoot) {
            return [$xmlElement->getName() => $array];
        }
        return $array;
    }

    /**
     * Convert the XML element to array
     * @param SimpleXMLElement $element
     * @return array|string
     */
    private function xmlToArray(SimpleXMLElement $element): array|string {
        $result = [];

        // Handle attributes if required
        if ($this->includeAttributes) {
            foreach ($element->attributes() as $attrKey => $attrValue) {
                $result['_attributes'][$attrKey] = (string) $attrValue;
            }
        }

        // Handle child elements
        foreach ($element->children() as $child) {
            $childName = $this->sanitizeName($child->getName());
            $childValue = $this->xmlToArray($child);

            if (isset($result[$childName])) {
                if (!is_array($result[$childName]) || !isset($result[$childName][0])) {
                    $result[$childName] = [$result[$childName]];
                }
                $result[$childName][] = $childValue;
            } else {
                $result[$childName] = $childValue;
            }
        }

        // Handle text content or CDATA
        $textContent = trim((string) $element);
        if (strlen($textContent) > 0) {
            if ($this->includeAttributes && count($result) > 0) {
                $result['_value'] = $textContent;
            } else {
                return $textContent;
            }
        }

        return $result;
    }

    /**
     * Sanitize the name of the element
     * @param string $name
     * @return string
     */
    private function sanitizeName(string $name): string {
        return match($this->namingConvention) {
            'camel' => Str::camel($name),
            'snake' => Str::snake($name),
            default => $name,
        };
    }
}