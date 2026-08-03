<?php declare(strict_types=1);

namespace Cognesy\Utils\Tokenization\Contracts;

/**
 * A tokenizer that produces the token IDs themselves, not just their count.
 *
 * Implementations are expected to be reusable: constructing one may load a
 * multi-megabyte vocabulary, so callers should keep the instance around
 * instead of building one per call.
 */
interface CanTokenizeText extends CanCountTokens
{
    /**
     * Token IDs for the text, in order.
     *
     * @return list<int>
     */
    public function encode(string $text): array;

    /**
     * Name of the encoding the token IDs belong to, e.g. `r50k_base`.
     *
     * Token IDs and counts are only comparable within the same encoding.
     */
    public function encoding(): string;
}
