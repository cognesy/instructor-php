<?php
namespace Cognesy\Instructor\Extras\Module\TaskData;

use Cognesy\Instructor\Extras\Module\DataAccess\Contracts\DataAccess;
use Cognesy\Instructor\Extras\Module\DataAccess\ObjectDataAccess;
use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Module\TaskData\Contracts\HasInputOutputData;

class TaskData implements HasInputOutputData
{
    use Traits\TaskData\HandlesSignature;
    use Traits\TaskData\HandlesInputOutputData;

    protected DataAccess $input;
    protected DataAccess $output;
    protected Signature $signature;
    protected string $description = '';

    public function __construct(
        object $input,
        object $output,
        Signature $signature,
    ) {
        $this->signature = $signature;
        $this->description = $signature->getDescription();
        $this->input = new ObjectDataAccess($input, $signature->toInputSchema()->getPropertyNames());
        $this->output = new ObjectDataAccess($output, $signature->toOutputSchema()->getPropertyNames());
    }
}
