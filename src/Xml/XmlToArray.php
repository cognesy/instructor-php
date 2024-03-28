<?php

namespace Cognesy\Instructor\Xml;

use DOMAttr;
use DOMCdataSection;
use DOMDocument;
use DOMElement;
use DOMNamedNodeMap;
use DOMText;

/**
 * Class XmlToArray
 * Copyright (c) 2017 Vladimir Yuldashev
 * License: MIT
 * Source: https://github.com/vyuldashev/xml-to-array/
 */
class XmlToArray
{
    protected $document;

    public function __construct(string $xml)
    {
        $this->document = new DOMDocument();
        $this->document->loadXML($xml);
    }

    public static function convert(string $xml): array
    {
        $converter = new static($xml);
        return $converter->toArray();
    }

    protected function convertAttributes(DOMNamedNodeMap $nodeMap): ?array
    {
        if ($nodeMap->length === 0) {
            return null;
        }

        $result = [];

        /** @var DOMAttr $item */
        foreach ($nodeMap as $item) {
            $result[$item->name] = $item->value;
        }

        return ['_attributes' => $result];
    }

    protected function convertDomElement(DOMElement $element)
    {
        $sameNames = [];
        $result = $this->convertAttributes($element->attributes);

        // Creates an index which counts each key, starting at zero, e.g. ['foo' => 2, 'bar' => 0]
        $childNodeNames = [];
        foreach ($element->childNodes as $key => $node) {
            if (array_key_exists($node->nodeName, $sameNames)) {
                $sameNames[$node->nodeName] += 1;
            } else {
                $sameNames[$node->nodeName] = 0;
            }
        }

        foreach ($element->childNodes as $key => $node) {
            if (is_null($result)) {
                $result = [];
            }

            if ($node instanceof DOMCdataSection) {
                $result['_cdata'] = $node->data;

                continue;
            }
            if ($node instanceof DOMText) {
                if (empty($result)) {
                    $result = $node->textContent;
                } else {
                    $result['_value'] = $node->textContent;
                }
                continue;
            }
            if ($node instanceof DOMElement) {
                if ($sameNames[$node->nodeName]) { // Truthy — When $sameNames['foo'] > 0
                    if (! array_key_exists($node->nodeName, $result)) { // Setup $result['foo']
                        $result[$node->nodeName] = [];
                    }

                    $result[$node->nodeName][$key] = $this->convertDomElement($node); // Store as $result['foo'][*]
                } else {
                    $result[$node->nodeName] = $this->convertDomElement($node); // Store as $result['foo']
                }

                continue;
            }
        }

        return $result;
    }

    public function toArray(): array
    {
        $result = [];

        if ($this->document->hasChildNodes()) {
            $children = $this->document->childNodes;

            foreach ($children as $child) {
                $result[$child->nodeName] = $this->convertDomElement($child);
            }
        }

        return $result;
    }
}