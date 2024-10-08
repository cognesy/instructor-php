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

    public static function fromText(string $text) : LLMFinishReason {
        return match ($text) {
            'stop_sequence' => self::Stop,
            'COMPLETE' => self::Stop,
            'stop' => self::Stop,
            'STOP' => self::Stop,
            'max_tokens' => self::Length,
            'MAX_TOKENS' => self::Length,
            'length' => self::Length,
            'model_length' => self::Length,
            'SAFETY' => self::ContentFilter,
            'RECITATION' => self::ContentFilter,
            'LANGUAGE' => self::ContentFilter,
            'BLOCKLIST' => self::ContentFilter,
            'PROHIBITED_CONTENT' => self::ContentFilter,
            'SPII' => self::ContentFilter,
            'error' => self::Error,
            'MALFORMED_FUNCTION_CALL' => self::Error,
            'tool_calls' => self::ToolCalls,
            'FINISH_REASON_UNSPECIFIED' => self::Other,
            'OTHER' => self::Other,
            default => self::Other,
        };
    }
}
