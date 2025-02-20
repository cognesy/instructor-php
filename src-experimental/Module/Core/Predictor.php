<?php
namespace Cognesy\Experimental\Module\Core;

use Closure;
use Cognesy\Experimental\Module\Core\Traits\Predictor\HandlesAccess;
use Cognesy\Experimental\Module\Core\Traits\Predictor\HandlesCreation;
use Cognesy\Experimental\Module\Core\Traits\Predictor\HandlesFeedback;
use Cognesy\Experimental\Module\Core\Traits\Predictor\HandlesParametrization;
use Cognesy\Experimental\Module\Core\Traits\Predictor\HandlesPrediction;
use Cognesy\Experimental\Module\Signature\Signature;
use Cognesy\Instructor\Features\Core\Data\StructuredOutputRequestInfo;
use Cognesy\Instructor\Instructor;
use Cognesy\LLM\LLM\Inference;

class Predictor
{
    use HandlesCreation;
    use HandlesAccess;
    use HandlesFeedback;
    use HandlesParametrization;
    use HandlesPrediction;

    protected Instructor $instructor;
    protected Inference $inference;
    protected string $connection;

    protected StructuredOutputRequestInfo $requestInfo;
    protected ?Signature $signature;
    protected Feedback $feedback;
    protected Closure $feedbackFn;
    protected string $roleDescription;
    private string $instructions;
//    protected ProvideFeedback $provideFeedback;

    public function __construct(
        string|Signature $signature = '',
        string $description = '',
        string $roleDescription = '',
        string $instructions = '',
    ) {
        $this->instructor = new Instructor();
        $this->inference = new Inference();
        $this->requestInfo = new StructuredOutputRequestInfo();
        $this->signature = match(true) {
            !empty($signature) => $this->makeSignature($signature, $description),
            default => null,
        };
        $this->instructions = $instructions;
        $this->roleDescription = $roleDescription;
        $this->feedback = new Feedback();
//        $this->provideFeedback = new ProvideFeedback();
//        $this->instructions = new Parameter(
//            $this->signature->toInstructions(),
//            requiresFeedback: true,
//            roleDescription: 'Predictor instructions'
//        );
    }
}
