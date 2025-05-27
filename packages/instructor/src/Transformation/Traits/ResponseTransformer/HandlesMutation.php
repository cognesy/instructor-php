<?php
namespace Cognesy\Instructor\Transformation\Traits\ResponseTransformer;

use Cognesy\Instructor\Transformation\Contracts\CanTransformObject;

trait HandlesMutation
{
    /** @param CanTransformObject[] $transformers */
    public function appendTransformers(array $transformers) : self {
        $this->transformers = array_merge($this->transformers, $transformers);
        return $this;
    }

    /** @param CanTransformObject[] $transformers */
    public function setTransformers(array $transformers) : self {
        $this->transformers = $transformers;
        return $this;
    }
}