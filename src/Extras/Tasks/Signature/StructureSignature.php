<?php

namespace Cognesy\Instructor\Extras\Tasks\Signature;

use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Tasks\TaskData\Contracts\TaskData;
use Cognesy\Instructor\Extras\Tasks\TaskData\DualTaskData;
use Cognesy\Instructor\Utils\Template;

class StructureSignature implements Signature
{
    use Traits\ConvertsToSignatureString;

    protected TaskData $taskData;
    protected string $description = '';
    protected string $prompt = 'Your task is to infer output argument values in input data based on specification: {signature} {description}';

    public function __construct(
        Structure $inputs,
        Structure $outputs,
        string $description = null,
    ) {
        if (!is_null($description)) {
            $this->description = $description;
        }
        $this->taskData = new DualTaskData($inputs, $outputs, $inputs->fieldNames(), $outputs->fieldNames());
    }

    public function data(): TaskData {
        return $this->taskData;
    }

    public function description(): string {
        return $this->description;
    }
}