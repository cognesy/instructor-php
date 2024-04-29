<?php
namespace Cognesy\Instructor\Extras\Structure;

use Cognesy\Instructor\Schema\Data\TypeDetails;

class Field {
    use Traits\HandlesFieldDefinitions;
    use Traits\HandlesFieldDescription;
    use Traits\HandlesFieldValidation;
    use Traits\HandlesFieldSchemas;
    use Traits\HandlesFieldValue;
    use Traits\HandlesFieldExamples;

    public function __construct(
        string $name = '',
        string $description = '',
        TypeDetails $typeDetails = null,
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->typeDetails = $typeDetails;
    }
}
