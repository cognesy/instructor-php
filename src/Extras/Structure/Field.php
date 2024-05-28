<?php
namespace Cognesy\Instructor\Extras\Structure;

use Cognesy\Instructor\Contracts\DataModel\CanHandleDataField;
use Cognesy\Instructor\Schema\Data\TypeDetails;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;

class Field implements CanHandleDataField {
    use \Cognesy\Instructor\Extras\Structure\Traits\Field\HandlesFieldDefinitions;
    use \Cognesy\Instructor\Extras\Structure\Traits\Field\HandlesFieldSchema;
    use \Cognesy\Instructor\Extras\Structure\Traits\Field\HandlesFieldValidation;
    use \Cognesy\Instructor\Extras\Structure\Traits\Field\HandlesFieldValue;
    use \Cognesy\Instructor\Extras\Structure\Traits\Field\HandlesOptionality;

    public function __construct(
        string $name = '',
        string $description = '',
        TypeDetails $typeDetails = null,
    ) {
        $this->schema = (new SchemaFactory)->makePropertySchema($typeDetails, $name, $description);
    }
}
