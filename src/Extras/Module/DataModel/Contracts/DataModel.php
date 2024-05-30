<?php
namespace Cognesy\Instructor\Extras\Module\DataModel\Contracts;

interface DataModel extends DataAccess, SchemaAccess
{
    /** Provides reference to data provider */
    public function getDataRef() : object;

    /** Provides reference to schema provider */
    public function getSchemaRef() : object;
}
