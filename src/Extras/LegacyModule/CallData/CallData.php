<?php
namespace Cognesy\Instructor\Extras\Module\CallData;

use Cognesy\Experimental\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Module\CallData\Contracts\HasInputOutputData;
use Cognesy\Instructor\Extras\Module\DataAccess\ObjectDataAccess;

class CallData implements HasInputOutputData
{
    use Traits\CallData\ProvidesSignature;
    use Traits\CallData\ProvidesSchema;
    use Traits\CallData\HandlesInputOutputData;

    public function __construct(
        object $input,
        object $output,
        Signature $signature,
    ) {
        $this->signature = $signature;
        $this->input = new ObjectDataAccess($input, $signature->toInputSchema()->getPropertyNames());
        $this->output = new ObjectDataAccess($output, $signature->toOutputSchema()->getPropertyNames());
    }
}
