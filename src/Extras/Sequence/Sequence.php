<?php

namespace Cognesy\Instructor\Extras\Sequence;

use ArrayAccess;
use Cognesy\Instructor\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Contracts\CanValidateSelf;
use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Deserialization\Symfony\Deserializer;
use Cognesy\Instructor\Validation\Symfony\Validator;
use IteratorAggregate;

class Sequence implements
    Sequenceable,
    IteratorAggregate,
    CanProvideSchema,
    CanDeserializeSelf,
    CanValidateSelf,
    ArrayAccess
{
    use Traits\HandlesArrayAccess;
    use Traits\HandlesDeserialization;
    use Traits\HandlesIteratorAggregate;
    use Traits\HandlesSequenceable;
    use Traits\HandlesValidation;
    use Traits\ProvidesSchema;

    public function __construct(string $class = '', string $name = '', string $description = '') {
        $this->class = $class;
        $this->name = $name;
        $this->description = $description;
        $this->deserializer = new Deserializer();
        $this->validator = new Validator();
    }
}
