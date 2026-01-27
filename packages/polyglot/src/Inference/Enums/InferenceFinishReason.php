<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Enums;

enum InferenceFinishReason : string
{
    case Stop = 'stop';
    case Length = 'length';
    case ToolCalls = 'tool_calls';
    case ContentFilter = 'content_filter';
    case Error = 'error';
    case Other = 'other';

    public function equals(string|InferenceFinishReason $reason) : bool {
        if ($reason instanceof InferenceFinishReason) {
            return $this->value === $reason->value;
        }
        return $this->value === $reason;
    }

    public static function fromText(string $text) : InferenceFinishReason {
        $text = strtolower($text);
        return match ($text) {
            'blocklist' => self::ContentFilter,
            'complete' => self::Stop,
            'completed' => self::Stop, // OpenResponses
            'content_filter' => self::ContentFilter,
            'end_turn' => self::Stop, // Anthropic
            'error' => self::Error,
            'failed' => self::Error, // OpenResponses
            'finish_reason_unspecified' => self::Other,
            'incomplete' => self::Length, // OpenResponses
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

    public function isOneOf(InferenceFinishReason ...$reasons): bool {
        foreach ($reasons as $reason) {
            if ($this->equals($reason)) {
                return true;
            }
        }
        return false;
    }
}
