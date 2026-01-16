<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Core;

use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;
use Cognesy\Addons\StepByStep\Continuation\CanExplainContinuation;
use Cognesy\Addons\StepByStep\Continuation\CanResolveErrorContext;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ErrorPolicyCriterion;
use Cognesy\Addons\StepByStep\Continuation\ErrorContext;
use Cognesy\Addons\StepByStep\Continuation\ErrorHandlingDecision;
use Cognesy\Addons\StepByStep\Continuation\ErrorPolicy;
use Cognesy\Addons\StepByStep\Continuation\ErrorType;

final class StaticErrorContextResolver implements CanResolveErrorContext
{
    public function __construct(private ErrorContext $context) {}

    public function resolve(object $state): ErrorContext {
        return $this->context;
    }
}

it('implements continuation decision and explanation interfaces', function () {
    $context = new ErrorContext(
        type: ErrorType::Tool,
        consecutiveFailures: 1,
        totalFailures: 1,
    );
    $criterion = new ErrorPolicyCriterion(
        ErrorPolicy::stopOnAnyError(),
        new StaticErrorContextResolver($context),
    );

    expect($criterion)->toBeInstanceOf(CanDecideToContinue::class);
    expect($criterion)->toBeInstanceOf(CanExplainContinuation::class);
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

    expect($criterion->decide(new \stdClass()))->toBe($expected);
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

    $evaluation = $criterion->explain(new \stdClass());

    expect($evaluation->context)->toMatchArray([
        'errorType' => 'tool',
        'consecutiveFailures' => 2,
        'totalFailures' => 3,
        'maxRetries' => 5,
        'handling' => ErrorHandlingDecision::Retry->value,
        'toolName' => 'lookup',
    ]);
});
