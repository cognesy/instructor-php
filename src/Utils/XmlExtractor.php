<?php

namespace Cognesy\Instructor\Utils;

use JetBrains\PhpStorm\Deprecated;

#[Deprecated('Not used - may be removed in the future.')]
class XmlExtractor
{
    public function extractToolCalls(string $content) : array {
        $xml = $this->tryExtractToolCall($content);
        $name = $this->tryExtractToolName($xml);
        $args = $this->tryExtractXmlArgs($content);
        if (empty($args)) {
            $args = $this->tryExtractJsonArgs($content);
        }
        return [$name, $args];
    }

    protected function tryExtractToolCall(string $content) : string {
        $pattern = '/<function_calls>.*<\/function_calls>/s';
        preg_match($pattern, $content, $matches);
        $xmlString = $matches[0] ?? '';
        return trim($xmlString);
    }

    protected function tryExtractToolName(string $content) : string {
        $pattern = '/<tool_name>(.*?)<\/tool_name>/s';
        preg_match($pattern, $content, $matches);
        $toolName = $matches[1] ?? '';
        return trim($toolName);
    }

    protected function tryExtractXmlArgs(string $content) : string {
        $pattern = '/<extracted_object>(.*?)<\/extracted_object>/s';
        preg_match($pattern, $content, $matches);
        $object = $matches[1] ?? '';
        return trim($object);
    }

    protected function tryExtractJsonArgs(string $content) : string {
        return trim(Json::find($content));
    }
}