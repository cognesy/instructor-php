<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Core;

use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\ContinuationEvaluation;

it('builds default reasons from decisions', function (ContinuationDecision $decision, string $expected) {
    $evaluation = ContinuationEvaluation::fromDecision('Acme\\Limits\\StepsLimit', $decision);

    expect($evaluation->criterionClass)->toBe('Acme\\Limits\\StepsLimit');
    expect($evaluation->decision)->toBe($decision);
    expect($evaluation->reason)->toBe($expected);
})->with([
    [ContinuationDecision::ForbidContinuation, 'StepsLimit forbade continuation'],
    [ContinuationDecision::RequestContinuation, 'StepsLimit requested continuation'],
    [ContinuationDecision::AllowContinuation, 'StepsLimit permits continuation'],
    [ContinuationDecision::AllowStop, 'StepsLimit allows stop'],
]);
