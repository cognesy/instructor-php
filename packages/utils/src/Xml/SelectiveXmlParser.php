<?php

namespace Cognesy\Utils\Xml;

use XMLReader;

class SelectiveXmlParser
{
    private XmlValidator $validator;

    public function __construct(
        private array $parsedTags = [],
    ) {
        $this->validator = new XmlValidator();
    }

    public function parse(string $xmlContent): array {
        $this->validator->validate($xmlContent);
        $reader = XMLReader::xml($xmlContent);
        return $this->parseNodes($reader);
    }

    // INTERNAL ///////////////////////////////////////////////////

    private function parseNodes(XMLReader $reader): array {
        $nodes = [];
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $this->canParseTag($reader->localName)) {
                $nodes[] = $this->parseNode($reader);
            }
        }
        return $nodes;
    }

    private function parseNode(XMLReader $reader): array {
        $node = [
            'tag' => $reader->localName,
            'content' => '',
            'attributes' => $this->getAttributes($reader),
            'children' => [],
        ];

        if ($reader->isEmptyElement) {
            return $node;
        }

        while ($reader->read()) {
            $result = match ($reader->nodeType) {
                XMLReader::ELEMENT => $this->handleElement($reader, $node),
                XMLReader::TEXT, XMLReader::CDATA => $this->handleText($reader, $node),
                XMLReader::END_ELEMENT => ['return' => $node],
                default => ['continue' => $node],
            };

            if (isset($result['return'])) {
                return $result['return'];
            }
            $node = $result['continue'] ?? $result['node'];
        }

        return $node;
    }

    private function handleElement(XMLReader $reader, array $node): array {
        if ($this->canParseTag($reader->localName)) {
            $node['children'][] = $this->parseNode($reader);
            return ['continue' => $node];
        }

        $node['content'] .= $reader->readOuterXML();
        $reader->next();
        return ['continue' => $node];
    }

    private function handleText(XMLReader $reader, array $node): array {
        $node['content'] .= $reader->value;
        return ['continue' => $node];
    }

    private function getAttributes(XMLReader $reader): array {
        $attributes = [];
        if ($reader->hasAttributes) {
            while ($reader->moveToNextAttribute()) {
                $attributes[$reader->name] = $reader->value;
            }
            $reader->moveToElement();
        }
        return $attributes;
    }

    private function canParseTag(string $localName) : bool {
        if (empty($this->parsedTags)) {
            return true;
        }
        return in_array($localName, $this->parsedTags);
    }
}