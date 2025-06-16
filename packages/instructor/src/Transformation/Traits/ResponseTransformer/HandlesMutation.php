<?php
namespace Cognesy\Instructor\Transformation\Traits\ResponseTransformer;

use Cognesy\Instructor\Transformation\Contracts\CanTransformData;

trait HandlesMutation
{
    /** @param CanTransformData[] $transformers */
    public function appendTransformers(array $transformers) : self {
        $this->transformers = array_merge($this->transformers, $transformers);
        return $this;
    }

    /** @param CanTransformData[] $transformers */
    public function setTransformers(array $transformers) : self {
        $this->transformers = $transformers;
        return $this;
    }
}