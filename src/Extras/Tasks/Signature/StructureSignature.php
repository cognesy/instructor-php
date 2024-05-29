<?php

namespace Cognesy\Instructor\Extras\Tasks\Signature;

use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Tasks\TaskData\Contracts\TaskData;
use Cognesy\Instructor\Extras\Tasks\TaskData\DualTaskData;

class StructureSignature implements Signature
{
    use Traits\ConvertsToSignatureString;
    use Traits\InitializesSignatureInputs;

    protected TaskData $data;
    protected string $description = '';

    public function __construct(
        Structure $inputs,
        Structure $outputs,
        string $description = null,
    ) {
        if (!is_null($description)) {
            $this->description = $description;
        }
        $this->data = new DualTaskData($inputs, $outputs, $inputs->fieldNames(), $outputs->fieldNames());
    }

    public function data(): TaskData {
        return $this->data;
    }

    public function description(): string {
        return $this->description;
    }
}