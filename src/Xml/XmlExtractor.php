<?php

namespace Cognesy\Instructor\Xml;

use Cognesy\Instructor\Utils\Json;

class XmlExtractor
{
    public function extractFunctionCalls(string $content) : array {
        $xml = $this->tryExtractFunctionCall($content);
        $functionName = $this->tryExtractFunctionName($xml);
        $args = $this->tryExtractXmlArgs($content);
        if (empty($args)) {
            $args = $this->tryExtractJsonArgs($content);
        }
        return [
            $functionName,
            $args,
        ];
    }

    protected function tryExtractFunctionCall(string $content) : string {
        $pattern = '/<function_calls>.*<\/function_calls>/s';
        preg_match($pattern, $content, $matches);
        $xmlString = $matches[0] ?? '';
        return trim($xmlString);
    }

    protected function tryExtractFunctionName(string $content) : string {
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