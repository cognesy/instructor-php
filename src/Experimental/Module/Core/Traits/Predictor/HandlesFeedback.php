<?php

namespace Cognesy\Instructor\Experimental\Module\Core\Traits\Predictor;

use Cognesy\Instructor\Experimental\Module\Core\Feedback;
use Cognesy\Instructor\Experimental\Module\Core\Predictor;
use Cognesy\Instructor\Experimental\Module\Signature\Signature;

trait HandlesFeedback
{
    public function feedback(): Feedback {
        return $this->feedback;
    }

    public function addFeedback(string $message): void {
        $this->feedback->add($message);
    }

    public function clearFeedback(): void {
        $this->feedback->clear();
    }

    public function provideFeedback(
        array $input,
        array $output,
        Signature $signature,
        string $instructions,
        string $roleDescription,
    ): Feedback {
        $generateFeedback = Predictor::fromSignature(
            signature: 'input, output, signature, instructions, role -> feedback',
            description: "Generate feedback on how to improve the output based on the input, signature, instructions and the role in the process."
        );
        $result = $generateFeedback->predict(
            $input,
            $output,
            $signature->toSignatureString(),
            $instructions,
            $roleDescription
        );
        return new Feedback($result);
    }
}
