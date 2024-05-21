<?php
namespace Cognesy\Instructor\Extras\Structure;

use Cognesy\Instructor\Schema\Data\TypeDetails;

class Field {
    use Traits\HandlesFieldDefinitions;
    use Traits\HandlesReflection;
    use Traits\HandlesFieldInfo;
    use Traits\HandlesFieldValidation;
    use Traits\HandlesFieldSchemas;
    use Traits\HandlesFieldValue;
    use Traits\HandlesFieldExamples;
    use Traits\HandlesOptionality;

    public function __construct(
        string $name = '',
        string $description = '',
        string $instructions = '',
        TypeDetails $typeDetails = null,
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->instructions = $instructions;
        $this->typeDetails = $typeDetails;
    }
}
