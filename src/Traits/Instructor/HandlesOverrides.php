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

    public function appendValidator(CanValidateObject $validator) : static {
        $this->appendValidators([$validator]);
        return $this;
    }

    /** @param CanValidateObject[] $validators */
    public function appendValidators(array $validators) : static {
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

    public function appendTransformer(CanTransformObject $transformer) : static {
        $this->appendTransformers([$transformer]);
        return $this;
    }

    /** @param CanTransformObject[] $transformers */
    public function appendTransformers(array $transformers) : static {
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

    public function appendDeserializer(CanDeserializeClass $deserializer) : static {
        $this->appendDeserializers([$deserializer]);
        return $this;
    }

    /** @param CanDeserializeClass[] $deserializers */
    public function appendDeserializers(array $deserializers) : static {
        $responseGenerator = $this->config->get(ResponseDeserializer::class);
        $responseGenerator->appendDeserializers($deserializers);
        return $this;
    }
}
