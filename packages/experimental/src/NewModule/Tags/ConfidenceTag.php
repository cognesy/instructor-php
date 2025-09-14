<?php declare(strict_types=1);

namespace Cognesy\Experimental\NewModule\Tags;

use Cognesy\Utils\TagMap\Contracts\TagInterface;

/**
 * ConfidenceTag - Confidence score for prediction (0.0 to 1.0)
 */
final readonly class ConfidenceTag implements TagInterface
{
    public function __construct(
        public float $confidence
    ) {
        if ($confidence < 0.0 || $confidence > 1.0) {
            throw new \InvalidArgumentException('Confidence must be between 0.0 and 1.0');
        }
    }
}