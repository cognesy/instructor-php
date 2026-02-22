<?php declare(strict_types=1);

namespace Cognesy\Experimental\NewModule\Tags;

use Cognesy\Experimental\NewModule\ModelId;
use Cognesy\Utils\TagMap\Contracts\TagInterface;

/**
 * ModelTag - Information about the model used for prediction
 */
final readonly class ModelTag implements TagInterface
{
    public function __construct(
        public ModelId $modelId
    ) {}
}
