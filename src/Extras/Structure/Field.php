<?php
namespace Cognesy\Instructor\Extras\Structure;

use Cognesy\Instructor\Schema\Data\TypeDetails;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;

class Field {
    use Traits\Field\HandlesFieldDefinitions;
    use Traits\Field\HandlesFieldSchema;
    use Traits\Field\HandlesFieldValidation;
    use Traits\Field\HandlesFieldValue;
    use Traits\Field\HandlesOptionality;

    public function __construct(
        string $name = '',
        string $description = '',
        TypeDetails $typeDetails = null,
    ) {
        if (empty($name)) {
            throw new \Exception('Field name cannot be empty');
        }
        if ($typeDetails === null) {
            throw new \Exception('Field type details cannot be null');
        }
        $this->schema = (new SchemaFactory)->makePropertySchema($typeDetails, $name, $description);
    }
}
