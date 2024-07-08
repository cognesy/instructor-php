<?php
namespace Cognesy\Instructor\Extras\Module\DataAccess;

use Cognesy\Instructor\Extras\Module\DataAccess\Contracts\DataAccess;

class ObjectDataAccess implements DataAccess
{
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

    public function getDataRef() : object {
        return $this->data;
    }
}
