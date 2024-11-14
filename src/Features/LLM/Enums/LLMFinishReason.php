<?php

namespace Cognesy\Instructor\Features\LLM\Enums;

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
        return match ($text) {
            'BLOCKLIST' => self::ContentFilter,
            'COMPLETE' => self::Stop,
            'error' => self::Error,
            'FINISH_REASON_UNSPECIFIED' => self::Other,
            'LANGUAGE' => self::ContentFilter,
            'length' => self::Length,
            'MALFORMED_FUNCTION_CALL' => self::Error,
            'max_tokens' => self::Length,
            'MAX_TOKENS' => self::Length,
            'model_length' => self::Length,
            'OTHER' => self::Other,
            'PROHIBITED_CONTENT' => self::ContentFilter,
            'RECITATION' => self::ContentFilter,
            'SAFETY' => self::ContentFilter,
            'SPII' => self::ContentFilter,
            'stop' => self::Stop,
            'STOP' => self::Stop,
            'stop_sequence' => self::Stop,
            'TOOL_CALL' => self::ToolCalls,
            'tool_calls' => self::ToolCalls,
            'tool_use' => self::ToolCalls,
            default => self::Other,
        };
    }
}
