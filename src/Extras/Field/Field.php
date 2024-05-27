<?php
namespace Cognesy\Instructor\Extras\Field;

use Cognesy\Instructor\Contracts\DataModel\CanHandleDataField;
use Cognesy\Instructor\Schema\Data\TypeDetails;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;

class Field implements CanHandleDataField {
    use Traits\HandlesFieldDefinitions;
    use Traits\HandlesFieldSchema;
    use Traits\HandlesFieldValidation;
    use Traits\HandlesFieldValue;
    use Traits\HandlesOptionality;

    public function __construct(
        string $name = '',
        string $description = '',
        TypeDetails $typeDetails = null,
    ) {
        $this->schema = (new SchemaFactory)->makePropertySchema($typeDetails, $name, $description);
    }
}
