<?php
namespace Cognesy\Instructor\Extras\Module\Optimizers;

use Cognesy\Instructor\Extras\Module\Core\Feedback;
use Cognesy\Instructor\Extras\Module\Core\Modules\OptimizeFromFeedback;
use Cognesy\Instructor\Extras\Module\Core\Predictor;

class TextGradientDescent
{
    private array $parameters;
    private OptimizeFromFeedback $optimize;

    public function __construct(array $parameters) {
        $this->parameters = $parameters;
        $this->optimize = new OptimizeFromFeedback();
    }

    public function step() : void {
        foreach ($this->parameters as $param) {
            if ($param instanceof Predictor) {
                $feedback = $param->feedback();
                $combinedFeedback = $feedback; // TODO: merge gradients
                $this->updateParameter($param, $combinedFeedback);
                $param->clearFeedback();
            }
        }
    }

    private function updateParameter(Predictor $param, Feedback $feedback) : void {
        $currentInstructions = $param->instructions();
        $newInstructions = $this->optimize->for($currentInstructions, $feedback);
        $param->using(instructions: $newInstructions);
    }
}
