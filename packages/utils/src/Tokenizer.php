<?php declare(strict_types=1);

namespace Cognesy\Utils;

use Cognesy\Utils\Tokenization\Contracts\CanCountTokens;
use Cognesy\Utils\Tokenization\TokenizerResolver;

/**
 * Static entry point for token counting.
 *
 * The work is done by a {@see CanCountTokens} implementation, so the tokenizer
 * can be swapped process-wide:
 *
 * ```php
 * Tokenizer::setDefault(TiktokenDriver::forModel('gpt-4o'));
 * ```
 *
 * Code that can take a dependency should accept `CanCountTokens` directly -
 * this facade exists for call sites that cannot, and for backwards
 * compatibility. The default instance is built once per process, which matters:
 * loading a BPE vocabulary costs tens of milliseconds and tens of megabytes.
 *
 * Which implementation that is, and how to pick one with `INSTRUCTOR_TOKENIZER`
 * instead of code, is described on {@see TokenizerResolver}.
 */
class Tokenizer
{
    private static ?CanCountTokens $tokenizer = null;

    /**
     * Counts the number of tokens in a given string content.
     *
     * @param string $content The content to be tokenized.
     * @return int The number of tokens in the content.
     */
    public static function tokenCount(string $content) : int {
        return self::default()->tokenCount($content);
    }

    /**
     * The tokenizer used by {@see self::tokenCount()}, built on first use by
     * {@see TokenizerResolver} - tiktoken when its vocabulary can be obtained,
     * the bundled tokenizer otherwise.
     */
    public static function default() : CanCountTokens {
        return self::$tokenizer ??= TokenizerResolver::resolve();
    }

    /**
     * Replaces the process-wide tokenizer. Pass null to fall back to the default
     * implementation.
     *
     * Token counts are only comparable within a single encoding: switching
     * implementations changes the numbers every consumer sees, so persisted
     * token budgets should be revisited alongside the switch.
     */
    public static function setDefault(?CanCountTokens $tokenizer) : void {
        self::$tokenizer = $tokenizer;
    }

    /**
     * Drops the current tokenizer, releasing its vocabulary. The next call
     * rebuilds the default implementation.
     */
    public static function reset() : void {
        self::$tokenizer = null;
    }
}
