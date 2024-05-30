<?php
namespace Cognesy\Instructor\Extras\Tasks\TaskData;

use Cognesy\Instructor\Extras\Tasks\TaskData\Contracts\DataModel;

class ObjectDataModel implements DataModel
{
    use Traits\HandlesObjectSchema;
    use Traits\HandlesObjectValues;
    use Traits\ProvidesSchemaAccess;
    use Traits\ProvidesDataAccess;

    private object $data;
    /** @var string[] */
    private array $propertyNames;

    /**
     * @param object $data
     * @param string[] $propertyNames
     */
    public function __construct(
        object $data,
        array $propertyNames,
    ) {
        $this->data = $data;
        $this->propertyNames = $propertyNames;
    }

    /** @return string[] */
    public function getPropertyNames(): array {
        return $this->propertyNames;
    }

    public function getRef() : object {
        return $this->data;
    }
}
