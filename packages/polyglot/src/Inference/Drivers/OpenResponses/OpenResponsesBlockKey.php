<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenResponses;

/** Canonical assistant-block identity for OpenResponses stream events. */
final readonly class OpenResponsesBlockKey
{
    private const string REASONING_PREFIX = 'openresponses:reasoning:';
    private const string TEXT_PREFIX = 'openresponses:text:';
    private const string TOOL_PREFIX = 'openresponses:tool:';

    public static function reasoning(string $itemIdentity): string
    {
        return self::REASONING_PREFIX . $itemIdentity;
    }

    public static function text(string $itemIdentity, string $contentIndex): string
    {
        return self::TEXT_PREFIX . $itemIdentity . ':' . $contentIndex;
    }

    public static function tool(?\Stringable $callId, string $itemIdentity): string
    {
        $identity = match (true) {
            $callId !== null && (string) $callId !== '' => (string) $callId,
            default => $itemIdentity,
        };
        return self::TOOL_PREFIX . $identity;
    }
}
