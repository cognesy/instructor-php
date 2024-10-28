<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Utils;

use SimpleXMLElement;

/**
 * Class Xml
 *
 * Convert XML to array
 *
 * @package Cognesy\Instructor\Utils
 */
class Xml
{
    private string $xmlString;
    private ?array $parsedArray = null;
    private bool $includeAttributes = false;
    private bool $includeRoot = false;
    private string $namingConvention = 'raw';

    private function __construct(
        string $xmlString,
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

    /**
     * Include attributes in the resulting array
     * @return self
     */
    public function withAttributes(): self {
        $this->includeAttributes = true;
        return $this;
    }

    /**
     * Include root element in the resulting array
     * @return self
     */
    public function withRoot(): self {
        $this->includeRoot = true;
        return $this;
    }

    public function wrapped(string $root = 'root'): self {
        $this->xmlString = "<$root>{$this->xmlString}</$root>";
        return $this;
    }

    /**
     * Set the naming convention for the resulting array
     * @param string $convention
     * @return self
     */
    public function withNaming(string $convention): self {
        $this->namingConvention = $convention;
        return $this;
    }

    /**
     * Return the array representation of the XML
     * @return array
     */
    public function toArray(): array {
        if ($this->parsedArray === null) {
            $this->parsedArray = $this->convertXmlToArray();
        }
        return $this->parsedArray;
    }

    // INTERNAL ///////////////////////////////////////////////////

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
     * TODO: allow conversion of names to snake_case or camelCase
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
