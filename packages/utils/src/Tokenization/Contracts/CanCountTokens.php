<?php declare(strict_types=1);

namespace Cognesy\Utils\Tokenization\Contracts;

/**
 * Minimal tokenization contract: how many tokens a piece of text costs.
 *
 * Most callers only budget context windows, so they should depend on this
 * rather than on CanTokenizeText - it can be satisfied by an API-provided
 * count or a heuristic estimator that cannot produce token IDs at all.
 */
interface CanCountTokens
{
    /**
     * Number of tokens the text encodes to.
     */
    public function tokenCount(string $text): int;
}
