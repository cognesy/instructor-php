<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Transformation\Contracts\CanTransformObject;
use Cognesy\Instructor\Validation\Contracts\CanValidateObject;

trait HandlesOverrides
{
    // VALIDATORS //////////////////////////////////////////////////////////

    public function setValidator(CanValidateObject $validator) : static {
        $this->setValidators([$validator]);
        return $this;
    }

    /** @param CanValidateObject[] $validators */
    public function setValidators(array $validators) : static {
        $this->responseValidator->setValidators($validators);
        return $this;
    }

    public function addValidator(CanValidateObject $validator) : static {
        $this->addValidators([$validator]);
        return $this;
    }

    /** @param CanValidateObject[] $validators */
    public function addValidators(array $validators) : static {
        $this->responseValidator->appendValidators($validators);
        return $this;
    }

    // TRANSFORMERS ////////////////////////////////////////////////////////

    public function setTransformer(CanTransformObject $transformer) : static {
        $this->setTransformers([$transformer]);
        return $this;
    }

    /** @param CanTransformObject[] $transformers */
    public function setTransformers(array $transformers) : static {
        $this->responseTransformer->setTransformers($transformers);
        return $this;
    }

    public function addTransformer(CanTransformObject $transformer) : static {
        $this->addTransformers([$transformer]);
        return $this;
    }

    /** @param CanTransformObject[] $transformers */
    public function addTransformers(array $transformers) : static {
        $this->responseTransformer->appendTransformers($transformers);
        return $this;
    }

    // DESERIALIZERS ///////////////////////////////////////////////////////

    public function setDeserializer(CanDeserializeClass $deserializer) : static {
        $this->setDeserializers([$deserializer]);
        return $this;
    }

    /** @param CanDeserializeClass[] $deserializers */
    public function setDeserializers(array $deserializers) : static {
        $this->responseDeserializer->setDeserializers($deserializers);
        return $this;
    }

    public function addDeserializer(CanDeserializeClass $deserializer) : static {
        $this->addDeserializers([$deserializer]);
        return $this;
    }

    /** @param CanDeserializeClass[] $deserializers */
    public function addDeserializers(array $deserializers) : static {
        $this->responseDeserializer->appendDeserializers($deserializers);
        return $this;
    }
}
