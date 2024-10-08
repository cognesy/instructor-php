<?php

namespace Cognesy\Instructor\Features\Deserialization\Traits\ResponseDeserializer;

use Cognesy\Instructor\Features\Deserialization\Contracts\CanDeserializeClass;

trait HandlesMutation
{
    /** @param \Cognesy\Instructor\Features\Deserialization\Contracts\CanDeserializeClass[] $deserializers */
    public function appendDeserializers(array $deserializers) : self {
        $this->deserializers = array_merge($this->deserializers, $deserializers);
        return $this;
    }

    /** @param \Cognesy\Instructor\Features\Deserialization\Contracts\CanDeserializeClass[] $deserializers */
    public function setDeserializers(array $deserializers) : self {
        $this->deserializers = $deserializers;
        return $this;
    }
}