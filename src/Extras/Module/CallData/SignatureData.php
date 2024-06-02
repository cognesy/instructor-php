<?php
namespace Cognesy\Instructor\Extras\Module\CallData;

use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Extras\Module\DataAccess\Contracts\DataAccess;
use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Module\CallData\Contracts\HasInputOutputData;

/**
 * This class supports self-contained data & signature automatically
 * derived from properties annotated with #[InputField] and #[OutputField]
 * attributes.
 *
 * To use it inherit from this class. Additionally, you can implement
 * static method for() with typed parameters to provide a factory method
 * providing type checking of input parameters.
 */
class SignatureData implements HasInputOutputData, CanProvideSchema
{
    use Traits\CallDataClass\HandlesInputOutputData;
    use Traits\CallDataClass\HandlesSchema;
    use Traits\CallDataClass\HandlesSignature;

    private Signature $signature;
    private DataAccess $input;
    private DataAccess $output;
}
