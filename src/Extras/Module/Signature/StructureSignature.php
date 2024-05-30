<?php
namespace Cognesy\Instructor\Extras\Module\Signature;

use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Extras\Module\Signature\Contracts\HasSignature;
use Cognesy\Instructor\Extras\Module\DataModel\Contracts\DataModel;
use Cognesy\Instructor\Extras\Module\DataModel\ObjectDataModel;

class StructureSignature implements HasSignature
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
        Structure $inputs,
        Structure $outputs,
        string $description = null,
    ) {
        if (!is_null($description)) {
            $this->description = $description;
        }
        $this->input = new ObjectDataModel($inputs, $inputs->fieldNames());
        $this->output = new ObjectDataModel($outputs, $outputs->fieldNames());
    }
}
