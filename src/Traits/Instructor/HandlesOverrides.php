<?php

namespace Cognesy\Instructor\Traits\Instructor;

use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Transformation\Contracts\CanTransformObject;
use Cognesy\Instructor\Validation\Contracts\CanValidateObject;

trait HandlesOverrides
{
    public function withValidator(CanValidateObject $validator) : static {
        $this->config->override([CanValidateObject::class => $validator]);
        return $this;
    }

    /** @param CanValidateObject[] $validators */
    public function withValidators(array $validators) : static {
        $this->config->override(['validators' => $validators]);
        return $this;
    }

    public function withTransformer(array $transformer) : static {
        $this->config->override(['transformers' => [$transformer]]);
        return $this;
    }

    /** @param CanTransformObject[] $transformers */
    public function withTransformers(array $transformers) : static {
        $this->config->override(['transformers' => $transformers]);
        return $this;
    }

    public function withDeserializer(array $deserializer) : static {
        $this->config->override(['deserializers' => [$deserializer]]);
        return $this;
    }

    /** @param CanDeserializeClass[] $deserializers */
    public function withDeserializers(array $deserializers) : static {
        $this->config->override(['deserializers' => $deserializers]);
        return $this;
    }
}
