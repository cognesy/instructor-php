<?php
namespace Cognesy\Instructor\Extras\Module\Signature;

use Cognesy\Instructor\Extras\Module\Signature\Contracts\HasSignature;
use Cognesy\Instructor\Extras\Module\DataModel\Contracts\DataModel;
use Cognesy\Instructor\Extras\Module\DataModel\ObjectDataModel;

abstract class ObjectSignature implements HasSignature
{
    use Traits\ConvertsToSignatureString;
    use Traits\InitializesSignatureInputs;
    use Traits\HandlesErrors;
    use Traits\HandlesTaskData;
    use Traits\HandlesTaskSchema;

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
        $this->input = new ObjectDataModel($inputs, self::inputNames());
        $this->output = new ObjectDataModel($outputs, self::outputNames());
    }

    /** @return string[] */
    abstract static public function inputNames(): array;

    /** @return string[] */
    abstract static public function outputNames(): array;
}
