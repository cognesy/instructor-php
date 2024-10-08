<?php

namespace Cognesy\Instructor\Extras\Sequence;

use ArrayAccess;
use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Features\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Features\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Features\Validation\Contracts\CanValidateSelf;
use Cognesy\Instructor\Features\Validation\Validators\SymfonyValidator;
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
        $this->deserializer = new SymfonyDeserializer();
        $this->validator = new SymfonyValidator();
    }
}
