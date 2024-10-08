<?php
namespace Cognesy\Instructor\Features\Transformation\Traits\ResponseTransformer;

use Cognesy\Instructor\Features\Transformation\Contracts\CanTransformObject;

trait HandlesMutation
{
    /** @param \Cognesy\Instructor\Features\Transformation\Contracts\CanTransformObject[] $transformers */
    public function appendTransformers(array $transformers) : self {
        $this->transformers = array_merge($this->transformers, $transformers);
        return $this;
    }

    /** @param \Cognesy\Instructor\Features\Transformation\Contracts\CanTransformObject[] $transformers */
    public function setTransformers(array $transformers) : self {
        $this->transformers = $transformers;
        return $this;
    }
}