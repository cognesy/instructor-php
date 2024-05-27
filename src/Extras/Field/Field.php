<?php
namespace Cognesy\Instructor\Extras\Field;

use Cognesy\Instructor\Contracts\DataModel\CanHandleField;
use Cognesy\Instructor\Schema\Data\TypeDetails;

class Field implements CanHandleField {
    use Traits\HandlesFieldDefinitions;
    use Traits\HandlesFieldInfo;
    use Traits\HandlesFieldSchemas;
    use Traits\HandlesFieldValidation;
    use Traits\HandlesFieldValue;
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
