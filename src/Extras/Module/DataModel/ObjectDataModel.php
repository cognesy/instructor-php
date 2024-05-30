<?php
namespace Cognesy\Instructor\Extras\Module\DataModel;

use Cognesy\Instructor\Extras\Module\DataModel\Contracts\DataModel;

class ObjectDataModel implements DataModel
{
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

    public function getDataRef() : object {
        return $this->data;
    }

    public function getSchemaRef() : object {
        return $this->data;
    }
}
