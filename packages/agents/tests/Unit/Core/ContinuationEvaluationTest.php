<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Core;

use Cognesy\Agents\AgentHooks\Guards\StepsLimitHook;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;

it('builds default reasons from decisions', function (ContinuationDecision $decision, string $expected) {
    $evaluation = ContinuationEvaluation::fromDecision(StepsLimitHook::class, $decision);

    expect($evaluation->criterionClass)->toBe(StepsLimitHook::class);
    expect($evaluation->decision)->toBe($decision);
    expect($evaluation->reason)->toBe($expected);
    expect($evaluation->stopReason)->toBeNull();
})->with([
    [ContinuationDecision::ForbidContinuation, 'StepsLimitHook forbade continuation'],
    [ContinuationDecision::RequestContinuation, 'StepsLimitHook requested continuation'],
    [ContinuationDecision::AllowContinuation, 'StepsLimitHook permits continuation'],
    [ContinuationDecision::AllowStop, 'StepsLimitHook allows stop'],
]);
