<?php
namespace Cognesy\Instructor\Extras\Module\Core;

use Closure;
use Cognesy\Instructor\Data\RequestInfo;
use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Instructor;

class Predictor
{
    use Traits\Predict\HandlesCreation;
    use Traits\Predict\HandlesAccess;
    use Traits\Predict\HandlesFeedback;
    use Traits\Predict\HandlesParametrization;
    use Traits\Predict\HandlesPrediction;

    protected Instructor $instructor;
    protected RequestInfo $requestInfo;
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
        $this->requestInfo = new RequestInfo();
        $this->signature = match(true) {
            !empty($signature) => $this->makeSignature($signature, $description),
            default => null,
        };
//        $this->provideFeedback = new ProvideFeedback();
//        $this->instructions = new Parameter(
//            $this->signature->toInstructions(),
//            requiresFeedback: true,
//            roleDescription: 'Predictor instructions'
//        );
    }
}
