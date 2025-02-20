<?php

namespace Cognesy\Utils\Xml;

use Cognesy\Utils\Str;
use SimpleXMLElement;

class SimpleXmlParser
{
    private ?array $parsedData = null;

    public function __construct(
        private string $xmlString,
        private bool $includeAttributes = false,
        private bool $includeRoot = false,
        private string $namingConvention = 'raw',
    ) {}

    public static function from(string $xmlString): self {
        return new self($xmlString);
    }

    public function withAttributes(): self {
        $this->includeAttributes = true;
        return $this;
    }

    public function withRoot(): self {
        $this->includeRoot = true;
        return $this;
    }

    public function wrapped(string $root = 'root'): self {
        $this->xmlString = "<$root>{$this->xmlString}</$root>";
        return $this;
    }

    public function asCamelCase(): self {
        $this->namingConvention = 'camel';
        return $this;
    }

    public function asSnakeCase(): self {
        $this->namingConvention = 'snake';
        return $this;
    }

    public function withNaming(string $namingConvention): self {
        $this->namingConvention = $namingConvention;
        return $this;
    }

    public function toArray(): array {
        if ($this->parsedData === null) {
            $this->parsedData = $this->convertXmlToArray();
        }
        return $this->parsedData;
    }

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
     * Conversion of names to snake_case or camelCase
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