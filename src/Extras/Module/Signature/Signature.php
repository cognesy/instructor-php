<?php

namespace Cognesy\Instructor\Extras\Module\Signature;

use Cognesy\Instructor\Extras\Module\Signature\Contracts\HasInputSchema;
use Cognesy\Instructor\Extras\Module\Signature\Contracts\HasOutputSchema;
use Cognesy\Instructor\Extras\Module\Signature\Traits\ConvertsToSignatureString;
use Cognesy\Instructor\Schema\Data\Schema\Schema;


class Signature implements HasInputSchema, HasOutputSchema
{
    use ConvertsToSignatureString;
    use Traits\Signature\HandlesAccess;
    use Traits\Signature\HandlesMutation;
    use Traits\Signature\HandlesConversion;
    use Traits\Signature\HandlesSerialization;
    use Traits\Signature\HandlesTemplates;

    public const ARROW = '->';

    private Schema $input;
    private Schema $output;
    private string $description;
    private string $shortSignature;
    private string $fullSignature;
    private string $compiled = '';

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
