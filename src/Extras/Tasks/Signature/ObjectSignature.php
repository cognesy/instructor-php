<?php
namespace Cognesy\Instructor\Extras\Tasks\Signature;

use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\HasSignature;
use Cognesy\Instructor\Extras\Tasks\TaskData\Contracts\DataModel;
use Cognesy\Instructor\Extras\Tasks\TaskData\ObjectDataModel;
use Cognesy\Instructor\Schema\Data\Schema\Schema;

abstract class ObjectSignature implements HasSignature
{
    use Traits\ConvertsToSignatureString;
    use Traits\InitializesSignatureInputs;

    protected DataModel $input;
    protected DataModel $output;
    protected string $description = '';

    public function __construct(
        object $inputs,
        object $outputs,
        string $description = null,
    ) {
        if (!is_null($description)) {
            $this->description = $description;
        }
        $this->input = new ObjectDataModel($inputs, $this->inputNames());
        $this->output = new ObjectDataModel($outputs, $this->outputNames());
    }

    /** @return string[] */
    abstract public function inputNames(): array;

    /** @return string[] */
    abstract public function outputNames(): array;

    public function input(): DataModel {
        return $this->input;
    }

    public function output(): DataModel {
        return $this->output;
    }

    public function description(): string {
        return $this->description;
    }

    public function toArray(): array {
        return [
            'inputs' => $this->input->getValues(),
            'outputs' => $this->output->getValues(),
        ];
    }

    public function toInputSchema(): Schema {
        return $this->input->toSchema();
    }

    public function toOutputSchema(): Schema {
        return $this->output->toSchema();
    }
}
