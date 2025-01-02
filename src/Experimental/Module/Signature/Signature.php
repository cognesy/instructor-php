<?php
namespace Cognesy\Instructor\Experimental\Module\Signature;

use Cognesy\Instructor\Experimental\Module\Signature\Contracts\HasInputSchema;
use Cognesy\Instructor\Experimental\Module\Signature\Contracts\HasOutputSchema;
use Cognesy\Instructor\Features\Schema\Data\Schema\Schema;

/**
 * Signature represents a specification of the module - its input and output schemas, and a description.
 *
 * Description parameter of the signature is a base, constant description of the module function.
 * It is used to as initial base for the optimization process, along with the input and output schemas,
 * but it never changes as a result of the optimization. This way it can be used in UI to display the
 * brief description of the module.
 *
 * Instructions parameter of the signature is a result of optimization process. It is changing as a result
 * of the optimization. It is used to provide the detailed instructions to the Large Language Model on how to
 * execute the transformation specified by the signature and explained by the description. It may become
 * a very long and complex and is not suitable for UI display.
 */
class Signature implements HasInputSchema, HasOutputSchema
{
    use Traits\ConvertsToSignatureString;
    use Traits\Signature\HandlesAccess;
    use Traits\Signature\HandlesConversion;
    use Traits\Signature\HandlesSerialization;

    public const ARROW = '->';

    private Schema $input;
    private Schema $output;
    private string $description;
    private string $shortSignature;
    private string $fullSignature;

    public function __construct(
        Schema $input,
        Schema $output,
        string $description = ''
    ) {
        $this->input = $input;
        $this->output = $output;
        $this->description = $description;
        $this->shortSignature = $this->makeShortSignatureString();
        $this->fullSignature = $this->makeSignatureString();
    }
}
