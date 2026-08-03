<?php declare(strict_types=1);

namespace Cognesy\Addons\Tests\Support;

use Cognesy\Utils\Tokenization\Contracts\CanCountTokens;

/**
 * Charges a flat cost per message regardless of its content.
 *
 * Real BPE counts scale with text length, so any split this produces is one the
 * bundled tokenizer could not produce for the same inputs - which is what makes
 * it usable to prove a component actually consulted the injected counter.
 */
final class FixedCostTokenizer implements CanCountTokens
{
    /** @var list<string> */
    public array $seen = [];

    public function __construct(
        private readonly int $costPerCall = 1,
    ) {}

    #[\Override]
    public function tokenCount(string $text): int {
        $this->seen[] = $text;
        return $this->costPerCall;
    }
}
