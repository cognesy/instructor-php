<?php

namespace Cognesy\Instructor\Deserialization\Traits\ResponseDeserializer;

use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;

trait HandlesMutation
{
    /** @param CanDeserializeClass[] $deserializers */
    public function appendDeserializers(array $deserializers) : self {
        $this->deserializers = array_merge($this->deserializers, $deserializers);
        return $this;
    }

    /** @param CanDeserializeClass[] $deserializers */
    public function setDeserializers(array $deserializers) : self {
        $this->deserializers = $deserializers;
        return $this;
    }
}