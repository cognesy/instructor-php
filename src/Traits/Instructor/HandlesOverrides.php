<?php

namespace Cognesy\Instructor\Traits\Instructor;

use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Transformation\Contracts\CanTransformObject;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\Contracts\CanValidateObject;
use Cognesy\Instructor\Validation\ResponseValidator;

trait HandlesOverrides
{
    // VALIDATORS //////////////////////////////////////////////////////////

    public function setValidator(CanValidateObject $validator) : static {
        $this->setValidators([$validator]);
        return $this;
    }

    /** @param CanValidateObject[] $validators */
    public function setValidators(array $validators) : static {
        $responseGenerator = $this->config->get(ResponseValidator::class);
        $responseGenerator->setValidators($validators);
        return $this;
    }

    public function addValidator(CanValidateObject $validator) : static {
        $this->addValidators([$validator]);
        return $this;
    }

    /** @param CanValidateObject[] $validators */
    public function addValidators(array $validators) : static {
        $responseGenerator = $this->config->get(ResponseValidator::class);
        $responseGenerator->appendValidators($validators);
        return $this;
    }

    // TRANSFORMERS ////////////////////////////////////////////////////////

    public function setTransformer(CanTransformObject $transformer) : static {
        $this->setTransformers([$transformer]);
        return $this;
    }

    /** @param CanTransformObject[] $transformers */
    public function setTransformers(array $transformers) : static {
        $responseGenerator = $this->config->get(ResponseTransformer::class);
        $responseGenerator->setTransformers($transformers);
        return $this;
    }

    public function addTransformer(CanTransformObject $transformer) : static {
        $this->addTransformers([$transformer]);
        return $this;
    }

    /** @param CanTransformObject[] $transformers */
    public function addTransformers(array $transformers) : static {
        $responseGenerator = $this->config->get(ResponseTransformer::class);
        $responseGenerator->appendTransformers($transformers);
        return $this;
    }

    // DESERIALIZERS ///////////////////////////////////////////////////////

    public function setDeserializer(CanDeserializeClass $deserializer) : static {
        $this->setDeserializers([$deserializer]);
        return $this;
    }

    /** @param CanDeserializeClass[] $deserializers */
    public function setDeserializers(array $deserializers) : static {
        $responseGenerator = $this->config->get(ResponseDeserializer::class);
        $responseGenerator->setDeserializers($deserializers);
        return $this;
    }

    public function addDeserializer(CanDeserializeClass $deserializer) : static {
        $this->addDeserializers([$deserializer]);
        return $this;
    }

    /** @param CanDeserializeClass[] $deserializers */
    public function addDeserializers(array $deserializers) : static {
        $responseGenerator = $this->config->get(ResponseDeserializer::class);
        $responseGenerator->appendDeserializers($deserializers);
        return $this;
    }
}
