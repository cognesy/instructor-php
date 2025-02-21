<?php

namespace Cognesy\Polyglot\LLM\Enums;

enum LLMFinishReason : string
{
    case Stop = 'stop';
    case Length = 'length';
    case ToolCalls = 'tool_calls';
    case ContentFilter = 'content_filter';
    case Error = 'error';
    case Other = 'other';

    public function equals(string|LLMFinishReason $reason) : bool {
        return match(true) {
            $reason instanceof LLMFinishReason => ($this->value === $reason->value),
            is_string($reason) => ($this->value === $reason),
            default => false,
        };
    }

    public static function fromText(string $text) : LLMFinishReason {
        $text = strtolower($text);
        return match ($text) {
            'blocklist' => self::ContentFilter,
            'complete' => self::Stop,
            'error' => self::Error,
            'finish_reason_unspecified' => self::Other,
            'language' => self::ContentFilter,
            'length' => self::Length,
            'malformed_function_call' => self::Error,
            'max_tokens' => self::Length,
            'model_length' => self::Length,
            'other' => self::Other,
            'prohibited_content' => self::ContentFilter,
            'recitation' => self::ContentFilter,
            'safety' => self::ContentFilter,
            'spii' => self::ContentFilter,
            'stop' => self::Stop,
            'stop_sequence' => self::Stop,
            'tool_call' => self::ToolCalls,
            'tool_calls' => self::ToolCalls,
            'tool_use' => self::ToolCalls,
            default => self::Other,
        };
    }
}
