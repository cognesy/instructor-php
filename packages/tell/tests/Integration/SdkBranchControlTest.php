<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Tell;

it('controls immutable-history workspace branches through the public SDK', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(
        FakeAgentDriver::fromResponses('baseline', 'review'),
    ));
    $project = tellLastTemporaryRoot() . '/project';
    mkdir($project, 0755, true);
    $tell = Tell::open($project, $factory);
    $workspace = $tell->workspace();
    $workspace->initialize();

    $baseline = $tell->run(TellRequest::prompt('Record baseline.')->durable());
    $branches = $workspace->branches();
    $baselineBranch = $branches->create('baseline');
    $review = $branches->create('release-review');
    $branches->checkout('release-review');
    $result = $tell->run(TellRequest::prompt('Review release.')->durable());
    $recovery = $branches->create('release-review-recovery', from: 'release-review');
    $reset = $branches->reset('release-review', 1);
    $after = $branches->show('release-review');

    expect($baseline->branch())->toBe('main')
        ->and($review->name)->toBe('release-review')
        ->and($review->turnCount)->toBe(1)
        ->and($result->branch())->toBe('release-review')
        ->and($recovery->head)->not->toBeNull()
        ->and($reset->changed)->toBeTrue()
        ->and($reset->previousHead)->toBe($recovery->head)
        ->and($after->head)->toBe($baselineBranch->head)
        ->and($after->turnCount)->toBe(1)
        ->and($branches->current()->name)->toBe('release-review');
});

it('keeps invalid or overlong branch reset operations outside the public SDK state', function (): void {
    $factory = tellTestFactory();
    $project = tellLastTemporaryRoot() . '/project';
    mkdir($project, 0755, true);
    $branches = Tell::open($project, $factory)->workspace();
    $branches->initialize();

    expect(fn () => $branches->branches()->create('agent-owned'))
        ->toThrow(InvalidArgumentException::class, 'reserved')
        ->and(fn () => $branches->branches()->reset('main', 0))
        ->toThrow(InvalidArgumentException::class, 'between 1 and 1000');
});
