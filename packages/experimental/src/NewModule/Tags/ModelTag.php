<?php declare(strict_types=1);

namespace Cognesy\Experimental\NewModule\Tags;

use Cognesy\Pipeline\Contracts\TagInterface;

/**
 * ModelTag - Information about the model used for prediction
 */
final readonly class ModelTag implements TagInterface
{
    public function __construct(
        public string $modelId
    ) {
        if (empty($modelId)) {
            throw new \InvalidArgumentException('Model ID cannot be empty');
        }
    }
}