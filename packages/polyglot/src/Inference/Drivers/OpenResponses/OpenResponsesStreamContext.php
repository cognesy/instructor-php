<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenResponses;

/**
 * Per-stream mutable state for OpenResponses streaming.
 *
 * A fresh instance is created for each stream, so state never
 * leaks across retries or driver reuse.
 */
final class OpenResponsesStreamContext
{
    public ?OpenResponseItemId $currentItemId = null;
    public string $currentItemType = '';
    /** @var array<string, \Cognesy\Polyglot\Inference\Data\ToolCallId> */
    public array $itemToCallId = [];
    /** @var array<string, string> */
    public array $itemToName = [];
    /** @var array<string, bool> */
    public array $seenOutputTextItems = [];
    /** @var array<string, bool> */
    public array $seenReasoningItems = [];
    /** @var array<string, string> */
    public array $toolArgsAccumulated = [];
}
