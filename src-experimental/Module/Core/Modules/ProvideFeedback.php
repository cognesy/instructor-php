<?php

namespace Cognesy\Experimental\Module\Core\Modules;

use Cognesy\Experimental\Module\Core\Feedback;
use Cognesy\Experimental\Module\Core\Predictor;
use Cognesy\Experimental\Module\Signature\Signature;

class ProvideFeedback
{
    private Predictor $makeFeedback;

    public function __construct() {
        $this->makeFeedback = Predictor::fromSignature(
            signature: 'input, output, signature, instructions, role -> feedback',
            description: "Generate feedback on how to improve the output based on the input, signature, instructions and the role in the process."
        );
    }

    public function for(
        array $input,
        mixed $output,
        Signature $signature,
        string $instructions,
        string $roleDescription
    ): Feedback {
        return ($this)(
            input: $input,
            output: $output,
            signature: $signature->toSignatureString(),
            instructions: $instructions,
            roleDescription: $roleDescription
        )->get('feedback');
    }

    protected function forward(mixed ...$callArgs): array {
        $feedback = $this->makeFeedback->predict(...$callArgs);
        return [
            'feedback' => new Feedback($feedback)
        ];
    }
}
