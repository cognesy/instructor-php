<?php
namespace Cognesy\Instructor\Extras\Structure;

class Field {
    use Traits\HandlesFieldDefinitions;
    use Traits\HandlesFieldDescription;
    use Traits\HandlesFieldValidation;
    use Traits\HandlesFieldSchemas;
    use Traits\HandlesFieldValue;
    use Traits\HandlesFieldExamples;

    public function __construct(string $name = '') {
        $this->name = $name;
    }
}
