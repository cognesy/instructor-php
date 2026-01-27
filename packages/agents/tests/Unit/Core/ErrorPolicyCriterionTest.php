<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Core;

use Cognesy\Agents\Core\Continuation\Contracts\CanEvaluateContinuation;
use Cognesy\Agents\Core\Continuation\Criteria\ErrorPolicyCriterion;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\ErrorHandling\Contracts\CanResolveErrorContext;
use Cognesy\Agents\Core\ErrorHandling\Data\ErrorContext;
use Cognesy\Agents\Core\ErrorHandling\Enums\ErrorHandlingDecision;
use Cognesy\Agents\Core\ErrorHandling\Enums\ErrorType;
use Cognesy\Agents\Core\ErrorHandling\ErrorPolicy;

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

    expect($criterion->evaluate(AgentState::empty())->decision)->toBe($expected);
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

    $evaluation = $criterion->evaluate(AgentState::empty());

    expect($evaluation->context)->toMatchArray([
        'errorType' => 'tool',
        'consecutiveFailures' => 2,
        'totalFailures' => 3,
        'maxRetries' => 5,
        'handling' => ErrorHandlingDecision::Retry->value,
        'toolName' => 'lookup',
    ]);
});
