<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Core;

use Cognesy\Agents\Core\Continuation\Criteria\StepsLimit;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;

it('builds default reasons from decisions', function (ContinuationDecision $decision, string $expected) {
    $evaluation = ContinuationEvaluation::fromDecision(StepsLimit::class, $decision);

    expect($evaluation->criterionClass)->toBe(StepsLimit::class);
    expect($evaluation->decision)->toBe($decision);
    expect($evaluation->reason)->toBe($expected);
    expect($evaluation->stopReason)->toBeNull();
})->with([
    [ContinuationDecision::ForbidContinuation, 'StepsLimit forbade continuation'],
    [ContinuationDecision::RequestContinuation, 'StepsLimit requested continuation'],
    [ContinuationDecision::AllowContinuation, 'StepsLimit permits continuation'],
    [ContinuationDecision::AllowStop, 'StepsLimit allows stop'],
]);
