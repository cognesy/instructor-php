<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Tell\Tests\Support\TestAutoload;
use Cognesy\Tell\Data\TellShellJobApproval;
use Cognesy\Tell\Data\TellShellJobEvent;
use Cognesy\Tell\Data\TellShellJobRequest;
use Cognesy\Tell\Shell\Exception\TellShellJobDeniedException;
use Cognesy\Tell\Shell\Exception\TellShellJobHostDisposedException;
use Cognesy\Tell\Shell\Exception\TellShellJobStartException;
use Cognesy\Tell\Shell\TellShellJobApprovals;
use Cognesy\Tell\Shell\TellShellJobHost;
use Cognesy\Tell\Shell\TellShellJobObservers;
use Cognesy\Tell\Shell\TellShellJobPolicy;
use Cognesy\Tell\Shell\TellShellJobState;
use Symfony\Component\Process\Process;

it('runs a shell job beyond the start call and preserves both output streams', function (): void {
    $project = tellTestProject();
    $host = TellShellJobHost::shellJobs(
        $project,
        approval: TellShellJobApprovals::allowAll(),
    )->boot();

    try {
        $started = $host->jobs()->start(TellShellJobRequest::command(
            'printf first; sleep 0.05; printf second; printf problem >&2',
        ));
        $finished = $host->jobs()->wait($started->id, 3_000);
        $output = $host->jobs()->read($started->id);

        expect($started->state)->toBe(TellShellJobState::Running)
            ->and($finished->state)->toBe(TellShellJobState::Exited)
            ->and($finished->exitCode)->toBe(0)
            ->and($output->text())->toContain('first')->toContain('second')->toContain('problem')
            ->and(array_column($output->chunks, 'stream'))->toContain('stdout', 'stderr');
    } finally {
        $host->dispose();
    }
});

it('denies by default before allocating a visible job identity', function (): void {
    $host = TellShellJobHost::shellJobs(tellTestProject())->boot();

    try {
        expect(fn () => $host->jobs()->start(TellShellJobRequest::command('printf denied')))
            ->toThrow(TellShellJobDeniedException::class)
            ->and($host->jobs()->all())->toBe([]);
    } finally {
        $host->dispose();
    }
});

it('validates containment and resource bounds before asking for approval', function (): void {
    $project = tellTestProject();
    $approvals = new ArrayObject();
    $approval = TellShellJobApprovals::callback(
        static function () use ($approvals): TellShellJobApproval {
            $approvals->append(true);

            return TellShellJobApproval::allow();
        },
    );
    $host = TellShellJobHost::shellJobs(
        $project,
        policy: new TellShellJobPolicy(maxLifetimeMs: 100, maxRetainedOutputBytes: 20),
        approval: $approval,
    )->boot();

    try {
        expect(fn () => $host->jobs()->start(
            TellShellJobRequest::command('printf bad')->in(dirname($project)),
        ))->toThrow(InvalidArgumentException::class)
            ->and(fn () => $host->jobs()->start(
                TellShellJobRequest::command('printf bad')->forMilliseconds(101),
            ))->toThrow(InvalidArgumentException::class)
            ->and(fn () => $host->jobs()->start(
                TellShellJobRequest::command('printf bad')->retaining(21),
            ))->toThrow(InvalidArgumentException::class)
            ->and($approvals)->toHaveCount(0)
            ->and($host->jobs()->all())->toBe([]);
    } finally {
        $host->dispose();
    }
});

it('enforces timeout and bounded retained output', function (): void {
    $host = TellShellJobHost::shellJobs(
        tellTestProject(),
        policy: new TellShellJobPolicy(
            maxLifetimeMs: 5_000,
            maxRetainedOutputBytes: 16,
            maxReadBytes: 8,
            cancellationGraceMs: 25,
        ),
        approval: TellShellJobApprovals::allowAll(),
    )->boot();

    try {
        $outputJob = $host->jobs()->start(TellShellJobRequest::command("printf '1234567890abcdefghij'"));
        $host->jobs()->wait($outputJob->id, 2_000);
        $first = $host->jobs()->read($outputJob->id);
        $second = $host->jobs()->read($outputJob->id, $first->nextCursor);
        $timeoutJob = $host->jobs()->start(TellShellJobRequest::command('sleep 5')->forMilliseconds(30));
        $timedOut = $host->jobs()->wait($timeoutJob->id, 2_000);

        expect($first->truncated)->toBeTrue()
            ->and($first->hasMore)->toBeTrue()
            ->and(strlen($first->text()))->toBe(8)
            ->and(strlen($first->text() . $second->text()))->toBe(16)
            ->and($timedOut->state)->toBe(TellShellJobState::TimedOut);
    } finally {
        $host->dispose();
    }
});

it('cancels idempotently and invalidates retained manager handles on disposal', function (): void {
    $host = TellShellJobHost::shellJobs(
        tellTestProject(),
        policy: new TellShellJobPolicy(cancellationGraceMs: 25),
        approval: TellShellJobApprovals::allowAll(),
    )->boot();
    $jobs = $host->jobs();
    $started = $jobs->start(TellShellJobRequest::command('sleep 5'));

    $first = $jobs->cancel($started->id);
    $second = $jobs->cancel($started->id);
    $host->dispose();

    expect($first->state)->toBe(TellShellJobState::Cancelled)
        ->and($second->state)->toBe(TellShellJobState::Cancelled)
        ->and(fn () => $jobs->all())->toThrow(TellShellJobHostDisposedException::class)
        ->and(fn () => $host->health())->toThrow(TellShellJobHostDisposedException::class);
});

it('keeps independent hosts and their job catalogues isolated', function (): void {
    $project = tellTestProject();
    $firstHost = TellShellJobHost::shellJobs($project, approval: TellShellJobApprovals::allowAll())->boot();
    $secondHost = TellShellJobHost::shellJobs($project, approval: TellShellJobApprovals::allowAll())->boot();

    try {
        $job = $firstHost->jobs()->start(TellShellJobRequest::command('printf isolated'));
        $firstHost->jobs()->wait($job->id, 2_000);

        expect($firstHost->jobs()->all())->toHaveCount(1)
            ->and($secondHost->jobs()->all())->toBe([])
            ->and(array_column($firstHost->health(), 'module'))->toBe(['shell.jobs'])
            ->and(array_column($secondHost->health(), 'module'))->toBe(['shell.jobs']);
    } finally {
        $firstHost->dispose();
        $secondHost->dispose();
    }
});

it('emits normalized redacted resource events without exposing commands or output', function (): void {
    $events = new ArrayObject();
    $observer = TellShellJobObservers::callback(
        static fn (TellShellJobEvent $event) => $events->append($event->toArray()),
    );
    $host = TellShellJobHost::shellJobs(
        tellTestProject(),
        approval: TellShellJobApprovals::allowAll(),
        observer: $observer,
    )->boot();
    $secret = 'never-print-this-secret';

    try {
        $job = $host->jobs()->start(TellShellJobRequest::command("printf '{$secret}'"));
        $host->jobs()->wait($job->id, 2_000);

        $encoded = json_encode($events->getArrayCopy(), JSON_THROW_ON_ERROR);
        expect($events->count())->toBeGreaterThan(3)
            ->and(array_unique(array_column($events->getArrayCopy(), 'schema')))->toBe(['tell.shell-job.event.v1'])
            ->and($encoded)->not->toContain($secret)
            ->and($encoded)->not->toContain("printf '{$secret}'")
            ->and(array_column($events->getArrayCopy(), 'kind'))->toContain(
                'module.loading',
                'module.active',
                'shell.job.running',
                'shell.job.exited',
            );
    } finally {
        $host->dispose();
    }
});

it('contains observer failures instead of corrupting shell-job lifecycle', function (): void {
    $host = TellShellJobHost::shellJobs(
        tellTestProject(),
        approval: TellShellJobApprovals::allowAll(),
        observer: TellShellJobObservers::callback(static fn () => throw new RuntimeException('observer failed')),
    )->boot();

    try {
        $job = $host->jobs()->start(TellShellJobRequest::command('printf healthy'));

        expect($host->jobs()->wait($job->id, 2_000)->state)->toBe(TellShellJobState::Exited);
    } finally {
        $host->dispose();
    }
});

it('publishes no partial job when process startup fails after approval', function (): void {
    $project = tellTestProject();
    $events = new ArrayObject();
    $host = TellShellJobHost::shellJobs(
        $project,
        approval: TellShellJobApprovals::callback(
            static function () use ($project): TellShellJobApproval {
                rmdir($project);

                return TellShellJobApproval::allow();
            },
        ),
        observer: TellShellJobObservers::callback(
            static fn (TellShellJobEvent $event) => $events->append($event->toArray()),
        ),
    )->boot();

    try {
        expect(fn () => $host->jobs()->start(TellShellJobRequest::command('printf never-started')))
            ->toThrow(TellShellJobStartException::class)
            ->and($host->jobs()->all())->toBe([])
            ->and(array_column($events->getArrayCopy(), 'kind'))->toContain('shell.job.start_failed')
            ->and(json_encode($events->getArrayCopy(), JSON_THROW_ON_ERROR))->not->toContain('never-started');
    } finally {
        $host->dispose();
    }
});

it('abandons a running process before disposing its owner module', function (): void {
    $project = tellTestProject();
    $marker = $project . '/leaked.txt';
    $events = new ArrayObject();
    $host = TellShellJobHost::shellJobs(
        $project,
        policy: new TellShellJobPolicy(cancellationGraceMs: 25),
        approval: TellShellJobApprovals::allowAll(),
        observer: TellShellJobObservers::callback(
            static fn (TellShellJobEvent $event) => $events->append($event->toArray()),
        ),
    )->boot();
    $host->jobs()->start(TellShellJobRequest::command(
        'sleep 0.2; printf leaked > ' . escapeshellarg($marker),
    ));

    $host->dispose();
    usleep(300_000);

    $kinds = array_column($events->getArrayCopy(), 'kind');
    expect($marker)->not->toBeFile()
        ->and($kinds)->toContain('shell.job.cancelled', 'module.disposed')
        ->and(array_search('shell.job.cancelled', $kinds, true))
        ->toBeLessThan(array_key_last($kinds));
});

it('keeps the ordinary deterministic Tell host Cordis-free', function (): void {
    $project = tellTestProject();
    $worker = dirname(__DIR__) . '/Fixtures/ordinary-host-worker.php';
    $process = new Process([PHP_BINARY, $worker, TestAutoload::path(), $project]);
    $process->mustRun();

    expect(trim($process->getOutput()))->toBe('cordis=not-loaded');
});

it('boots a fresh isolated runtime each time a reusable builder is booted', function (): void {
    $builder = TellShellJobHost::shellJobs(
        tellTestProject(),
        approval: TellShellJobApprovals::allowAll(),
    );
    $first = $builder->boot();
    $firstJob = $first->jobs()->start(TellShellJobRequest::command('printf first'));
    $first->jobs()->wait($firstJob->id, 2_000);
    $first->dispose();

    $second = $builder->boot();
    try {
        $secondJob = $second->jobs()->start(TellShellJobRequest::command('printf second'));
        $finished = $second->jobs()->wait($secondJob->id, 2_000);

        expect($second->jobs()->all())->toHaveCount(1)
            ->and($secondJob->id)->not->toBe($firstJob->id)
            ->and($finished->state)->toBe(TellShellJobState::Exited)
            ->and($second->jobs()->read($secondJob->id)->text())->toBe('second');
    } finally {
        $second->dispose();
    }
});

it('cancels multiple running jobs in reverse ownership order on shutdown', function (): void {
    $project = tellTestProject();
    $events = new ArrayObject();
    $host = TellShellJobHost::shellJobs(
        $project,
        policy: new TellShellJobPolicy(cancellationGraceMs: 25),
        approval: TellShellJobApprovals::allowAll(),
        observer: TellShellJobObservers::callback(
            static fn (TellShellJobEvent $event) => $events->append($event->toArray()),
        ),
    )->boot();
    $firstMarker = $project . '/first-leaked.txt';
    $secondMarker = $project . '/second-leaked.txt';
    $first = $host->jobs()->start(TellShellJobRequest::command(
        'sleep 0.2; printf leaked > ' . escapeshellarg($firstMarker),
    ));
    $second = $host->jobs()->start(TellShellJobRequest::command(
        'sleep 0.2; printf leaked > ' . escapeshellarg($secondMarker),
    ));

    $host->dispose();
    usleep(300_000);

    $cancelled = array_values(array_filter(
        $events->getArrayCopy(),
        static fn (array $event): bool => $event['kind'] === 'shell.job.cancelled',
    ));
    expect($firstMarker)->not->toBeFile()
        ->and($secondMarker)->not->toBeFile()
        ->and(array_column($cancelled, 'sourceId'))->toBe([$second->id, $first->id]);
});

it('reports useful health without commands output or raw runtime objects', function (): void {
    $secret = 'health-must-not-leak';
    $host = TellShellJobHost::shellJobs(
        tellTestProject(),
        approval: TellShellJobApprovals::allowAll(),
    )->boot();

    try {
        $job = $host->jobs()->start(TellShellJobRequest::command("sleep 0.05; printf '{$secret}'"));
        $active = $host->health();
        $host->jobs()->wait($job->id, 2_000);
        $settled = $host->health();
        $encoded = json_encode($active, JSON_THROW_ON_ERROR);

        expect(array_column($active, 'module'))->toContain('shell.jobs', 'shell.job.' . $job->id)
            ->and(array_unique(array_column($active, 'state')))->toBe(['active'])
            ->and($encoded)->not->toContain($secret)
            ->and($encoded)->not->toContain('CordisPhp')
            ->and(array_column($settled, 'module'))->toBe(['shell.jobs']);
    } finally {
        $host->dispose();
    }
});
