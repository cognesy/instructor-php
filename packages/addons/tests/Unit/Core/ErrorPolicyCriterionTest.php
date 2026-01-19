<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Core;

use Cognesy\Addons\StepByStep\Continuation\CanEvaluateContinuation;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ErrorPolicyCriterion;
use Cognesy\Addons\StepByStep\ErrorHandling\CanResolveErrorContext;
use Cognesy\Addons\StepByStep\ErrorHandling\ErrorContext;
use Cognesy\Addons\StepByStep\ErrorHandling\ErrorHandlingDecision;
use Cognesy\Addons\StepByStep\ErrorHandling\ErrorPolicy;
use Cognesy\Addons\StepByStep\ErrorHandling\ErrorType;

final class StaticErrorContextResolver implements CanResolveErrorContext
{
    public function __construct(private ErrorContext $context) {}

    public function resolve(object $state): ErrorContext {
        return $this->context;
    }
}

it('implements CanEvaluateContinuation interface', function () {
    $context = new ErrorContext(
        type: ErrorType::Tool,
        consecutiveFailures: 1,
        totalFailures: 1,
    );
    $criterion = new ErrorPolicyCriterion(
        ErrorPolicy::stopOnAnyError(),
        new StaticErrorContextResolver($context),
    );

    expect($criterion)->toBeInstanceOf(CanEvaluateContinuation::class);
});

it('maps handling decisions to continuation decisions', function (ErrorPolicy $policy, ContinuationDecision $expected) {
    $context = new ErrorContext(
        type: ErrorType::Tool,
        consecutiveFailures: 1,
        totalFailures: 1,
    );
    $criterion = new ErrorPolicyCriterion(
        $policy,
        new StaticErrorContextResolver($context),
    );

    expect($criterion->evaluate(new \stdClass())->decision)->toBe($expected);
})->with([
    [ErrorPolicy::stopOnAnyError(), ContinuationDecision::ForbidContinuation],
    [ErrorPolicy::retryToolErrors(3), ContinuationDecision::AllowContinuation],
    [ErrorPolicy::ignoreToolErrors(), ContinuationDecision::AllowContinuation],
]);

it('exposes error policy context in evaluation', function () {
    $context = new ErrorContext(
        type: ErrorType::Tool,
        consecutiveFailures: 2,
        totalFailures: 3,
        message: 'fail',
        toolName: 'lookup',
    );
    $policy = ErrorPolicy::retryToolErrors(5);
    $criterion = new ErrorPolicyCriterion(
        $policy,
        new StaticErrorContextResolver($context),
    );

    $evaluation = $criterion->evaluate(new \stdClass());

    expect($evaluation->context)->toMatchArray([
        'errorType' => 'tool',
        'consecutiveFailures' => 2,
        'totalFailures' => 3,
        'maxRetries' => 5,
        'handling' => ErrorHandlingDecision::Retry->value,
        'toolName' => 'lookup',
    ]);
});
