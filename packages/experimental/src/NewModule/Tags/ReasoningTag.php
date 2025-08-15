<?php declare(strict_types=1);

namespace Cognesy\Experimental\NewModule\Tags;

use Cognesy\Pipeline\Contracts\TagInterface;

/**
 * ReasoningTag - Chain-of-thought reasoning from LLM predictions
 */
final readonly class ReasoningTag implements TagInterface
{
    public function __construct(
        public string $reasoning
    ) {}
}