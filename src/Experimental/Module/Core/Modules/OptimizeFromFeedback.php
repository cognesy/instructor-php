<?php

namespace Cognesy\Instructor\Experimental\Module\Core\Modules;

use Cognesy\Instructor\Experimental\Module\Core\Feedback;
use Cognesy\Instructor\Experimental\Module\Core\Predictor;

class OptimizeFromFeedback
{
    private Predictor $optimizeInstructions;

    public function __construct() {
        $this->optimizeInstructions = Predictor::fromSignature(
            signature: 'current_instructions, feedback -> optimized_instructions',
            description: "Refine the current instructions based on the gradient feedback to improve future predictions."
        );
    }

    public function for(string $currentInstructions, Feedback $feedback): string {
        return ($this)(
            current_instructions: $currentInstructions,
            gradient_feedback: $feedback->get()
        )->get('optimized_instructions');
    }

    protected function forward(mixed ...$callArgs): array {
        $optimizedInstructions = $this->optimizeInstructions->predict(...$callArgs);
        return [
            'optimized_instructions' => $optimizedInstructions
        ];
    }
}
