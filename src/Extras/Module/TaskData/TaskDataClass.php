<?php
namespace Cognesy\Instructor\Extras\Module\TaskData;

use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Extras\Module\DataAccess\Contracts\DataAccess;
use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Module\TaskData\Contracts\HasInputOutputData;

class TaskDataClass implements HasInputOutputData, CanProvideSchema
{
    use Traits\TaskDataClass\HandlesInputOutputData;
    use Traits\TaskDataClass\HandlesSchema;
    use Traits\TaskDataClass\HandlesSignature;

    private Signature $signature;
    private DataAccess $input;
    private DataAccess $output;
}
