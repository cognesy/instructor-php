<?php declare(strict_types=1);

namespace Cognesy\Dynamic;

use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Factories\SchemaFactory;

class Field {
    use Traits\Field\HandlesFieldDefinitions;
    use Traits\Field\HandlesCollectionPrototype;
    use Traits\Field\HandlesFieldSchema;
    use Traits\Field\HandlesFieldValidation;
    use Traits\Field\HandlesFieldValue;
    use Traits\Field\HandlesOptionality;

    public function __construct(
        string $name = '',
        string $description = '',
        ?TypeDetails $typeDetails = null,
        ?Schema $customSchema = null,
        ?Structure $prototype = null,
    ) {
        if (empty($name)) {
            throw new \Exception('Field name cannot be empty');
        }
        if ($typeDetails === null) {
            throw new \Exception('Field type details cannot be null');
        }
        $this->schema = $customSchema
            ?? (new SchemaFactory)->propertySchema($typeDetails, $name, $description);
        $this->prototype = $prototype;
    }
}
