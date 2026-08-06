<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Closure;
use Cognesy\Agents\Evals\Events\JudgeGuardsNotConfigured;
use Cognesy\Utils\Time\ClockInterface;
use Cognesy\Utils\Time\SystemClock;
use Composer\InstalledVersions;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use JsonSerializable;
use OutOfBoundsException;
use Override;
use RuntimeException;

final class ArtifactEvalReporter implements CanReportAgentEvals
{
    private const PACKAGE_NAME = 'cognesy/agents';

    private string $runDirectory = '';
    private ?DateTimeImmutable $startedAt = null;

    /** @var array<string, mixed> */
    private array $envelope = [];

    /** @var list<EvalResult> */
    private array $results = [];

    /**
     * @param ClockInterface $clock Governs `provenance.startedAt`. Inject a
     *        frozen clock in tests so artifacts are timestamp-independent.
     * @param Closure(): (?string)|null $gitShaResolver Governs
     *        `provenance.package.gitSha`. Defaults to a pure-filesystem walk
     *        from the current working directory that never shells out and
     *        degrades to null outside a git checkout (or a linked worktree
     *        whose gitdir can't be read). Inject a fixed resolver in tests so
     *        artifacts are sha-independent.
     * @param Closure(): (?string)|null $packageVersionResolver Governs
     *        `provenance.package.version`. Defaults to Composer's
     *        `InstalledVersions`, resolving to null when the package isn't
     *        installed via Composer (e.g. a path repository without a lock
     *        entry) rather than fabricating a version.
     */
    public function __construct(
        private readonly string $root = '.instructor/evals',
        private readonly ClockInterface $clock = new SystemClock(),
        private readonly ?Closure $gitShaResolver = null,
        private readonly ?Closure $packageVersionResolver = null,
    ) {}

    #[Override]
    public function id(): string {
        return 'artifacts:' . $this->root;
    }

    #[Override]
    public function onRunStarted(int $caseCount): void {
        $this->startedAt = $this->clock->now();
        $this->runDirectory = rtrim($this->root, '/') . '/' . $this->startedAt->setTimezone(new DateTimeZone('UTC'))->format('Ymd-His-u') . '-' . bin2hex(random_bytes(3));
        if (!mkdir($this->runDirectory, 0777, true) && !is_dir($this->runDirectory)) {
            throw new RuntimeException('Cannot create eval artifact directory.');
        }
        $this->results = [];
        $this->envelope = [
            'package' => [
                'version' => $this->packageVersionResolver !== null ? ($this->packageVersionResolver)() : self::defaultPackageVersion(),
                'gitSha' => $this->gitShaResolver !== null ? ($this->gitShaResolver)() : self::defaultGitSha(),
            ],
            'startedAt' => $this->startedAt->format(DateTimeImmutable::ATOM),
        ];
    }

    #[Override]
    public function onEvalCompleted(EvalResult $result): void {
        $this->results[] = $result;
        $directory = $this->runDirectory . '/evals/' . self::sanitize($result->id());
        if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create per-eval artifact directory.');
        }
        self::json($directory . '/details.json', $result->toArray([...$this->envelope, 'repeat' => $result->trialCount()]));
        self::write($directory . '/events.ndjson', self::eventLines($result->run()->events()));
        self::json($directory . '/target-trace.json', $result->run()->toArray());
        self::write($directory . '/target-steps.jsonl', self::stepLines($result->run()->steps()));
        self::writeJudgeArtifacts($directory, $result->assertions());
        self::writeTrialArtifacts($directory, $result);
        // No target-messages.json: a raw conversation snapshot embeds tool
        // arguments and tool results VERBATIM, bypassing the digesting that
        // `EvalTracePolicy::safe()` exists to perform on every other artifact -
        // writing it here would reintroduce the exact leak class the trace
        // hardening closed. That policy reason holds independently of the fact
        // that nothing in the reachable pipeline captures such a snapshot today
        // (`EvalStep` deliberately carries no input messages; see its docblock).
    }

    #[Override]
    public function onRunCompleted(EvalRunResult $result): void {
        self::json($this->runDirectory . '/summary.json', $result->toArray([...$this->envelope, 'repeat' => self::runRepeat($result)]));
        $lines = array_map(static fn (EvalResult $eval): string => json_encode($eval->toArray(), JSON_THROW_ON_ERROR), $this->results);
        self::write($this->runDirectory . '/results.jsonl', implode("\n", $lines) . ($lines !== [] ? "\n" : ''));
    }

    public function runDirectory(): string {
        return $this->runDirectory;
    }

    // INTERNAL ////////////////////////////////////////////////

    private static function sanitize(string $id): string {
        return trim(preg_replace('/[^A-Za-z0-9._\/-]+/', '_', str_replace('..', '_', $id)) ?? '_', '/');
    }

    /** @throws JsonException */
    private static function eventLines(EvalEvents $events): string {
        $lines = [];
        foreach ($events as $event) {
            $data = $event instanceof JsonSerializable ? $event->jsonSerialize() : get_object_vars($event);
            $lines[] = json_encode(['type' => $event::class, 'data' => $data], JSON_THROW_ON_ERROR);
        }
        return implode("\n", $lines) . ($lines !== [] ? "\n" : '');
    }

    /** @throws JsonException */
    private static function stepLines(EvalSteps $steps): string {
        $lines = array_map(static fn (EvalStep $step): string => json_encode($step->toArray(), JSON_THROW_ON_ERROR), $steps->all());
        return implode("\n", $lines) . ($lines !== [] ? "\n" : '');
    }

    /**
     * One `judges/NNN.json` (+ `judges/NNN-steps.jsonl`) per assertion that
     * carries a judge's own `AgentRun`, numbered 1-up in `$assertions`'
     * insertion order - the order eval code actually recorded them in, not
     * filesystem or hash order, so numbering is stable across repeated runs of
     * the same eval. Lightweight judges (no `AgentRun`) are already fully
     * captured by `details.json`'s `assertions` entries and get no file here.
     */
    private static function writeJudgeArtifacts(string $directory, AssertionResults $assertions): void {
        $number = 0;
        foreach ($assertions->all() as $assertion) {
            $run = $assertion->judgeScore()?->run;
            if ($run === null) {
                continue;
            }
            $number++;
            $judgesDirectory = $directory . '/judges';
            if (!is_dir($judgesDirectory) && !mkdir($judgesDirectory, 0777, true) && !is_dir($judgesDirectory)) {
                throw new RuntimeException('Cannot create judge artifact directory.');
            }
            $stem = sprintf('%03d', $number);
            self::json($judgesDirectory . "/{$stem}.json", [
                'assertion' => $assertion->name(),
                'label' => $assertion->label(),
                'score' => $assertion->judgeScore()?->score,
                'reason' => $assertion->judgeScore()?->reason,
                'evidence' => $assertion->judgeScore()?->evidence->all() ?? [],
                // Concise judge-run summary only - the full step-by-step trace
                // lives in the sibling -steps.jsonl file so it is never duplicated.
                'run' => [
                    'status' => $run->status()?->value,
                    'stepCount' => $run->stepCount(),
                    'toolCount' => $run->tools()->count(),
                    'usage' => $run->usage()->toArray(),
                    'duration' => $run->duration(),
                    'stopSignal' => $run->stopSignal()?->toArray(),
                    'llmProfile' => $run->llmProfile()?->toArray(),
                    'guardsWarningObserved' => self::hasGuardWarning($run),
                ],
            ]);
            self::write($judgesDirectory . "/{$stem}-steps.jsonl", self::stepLines($run->steps()));
        }
    }

    /**
     * `trials/NNN/details.json` (+ that trial's own `judges/`) per trial of a
     * repeated case, numbered 1-up in EXECUTION order, so trial 3's artifacts are
     * trial 3's on every run of the same eval. Each trial's assertions are also
     * kept in the aggregate `details.json` under `repetition.results`; these files
     * add what that summary deliberately omits - the trial's own target trace and
     * its judges' traces. Nothing is written for a case that ran once: its single
     * trial IS the aggregate, already fully captured beside this directory.
     */
    private static function writeTrialArtifacts(string $directory, EvalResult $result): void {
        $number = 0;
        foreach ($result->trials() as $trial) {
            $number++;
            $trialDirectory = $directory . '/trials/' . sprintf('%03d', $number);
            if (!is_dir($trialDirectory) && !mkdir($trialDirectory, 0777, true) && !is_dir($trialDirectory)) {
                throw new RuntimeException('Cannot create per-trial artifact directory.');
            }
            self::json($trialDirectory . '/details.json', $trial->toArray());
            self::write($trialDirectory . '/target-steps.jsonl', self::stepLines($trial->run()->steps()));
            self::writeJudgeArtifacts($trialDirectory, $trial->assertions());
        }
    }

    /**
     * The trial count the run actually used, read back from the results rather
     * than from the options the reporter never sees. A run whose cases all ran
     * once reports 1, which is what a non-repeated run has always reported.
     */
    private static function runRepeat(EvalRunResult $result): int {
        $repeat = 1;
        foreach ($result->all() as $eval) {
            $repeat = max($repeat, $eval->trialCount());
        }
        return $repeat;
    }

    private static function hasGuardWarning(AgentRun $run): bool {
        foreach ($run->events() as $event) {
            if ($event instanceof JudgeGuardsNotConfigured) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string, mixed> $data @throws JsonException */
    private static function json(string $path, array $data): void {
        self::write($path, json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    private static function write(string $path, string $content): void {
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException("Cannot write {$path}");
        }
    }

    /**
     * Pure filesystem walk from the current working directory to the nearest
     * `.git` - handles both an ordinary repository (`.git/` directory) and a
     * linked worktree (`.git` file containing `gitdir: <path>`). Never shells
     * out, so it works the same whether `git` is on PATH or not. Returns null
     * as soon as any expected file is missing or unreadable, rather than
     * guessing - including when run outside a git checkout entirely, a real
     * supported case (e.g. a source archive or minimal container image).
     */
    private static function defaultGitSha(): ?string {
        $directory = getcwd();
        if ($directory === false) {
            return null;
        }
        for ($depth = 0; $depth < 64; $depth++) {
            $gitPath = $directory . '/.git';
            if (is_dir($gitPath)) {
                return self::readHeadSha($gitPath);
            }
            if (is_file($gitPath)) {
                return self::readHeadSha(self::resolveLinkedGitDir($directory, $gitPath));
            }
            $parent = dirname($directory);
            if ($parent === $directory) {
                return null;
            }
            $directory = $parent;
        }
        return null;
    }

    private static function resolveLinkedGitDir(string $workingDirectory, string $gitFilePath): ?string {
        $contents = file_get_contents($gitFilePath);
        if (!is_string($contents) || !str_starts_with(trim($contents), 'gitdir:')) {
            return null;
        }
        $linked = trim(substr(trim($contents), strlen('gitdir:')));
        return str_starts_with($linked, '/') ? $linked : $workingDirectory . '/' . $linked;
    }

    private static function readHeadSha(?string $gitDir): ?string {
        if ($gitDir === null) {
            return null;
        }
        $headPath = $gitDir . '/HEAD';
        if (!is_file($headPath)) {
            return null;
        }
        $head = trim((string) file_get_contents($headPath));
        if (!str_starts_with($head, 'ref:')) {
            // Detached HEAD: the file holds the sha directly.
            return preg_match('/^[0-9a-f]{7,40}$/i', $head) === 1 ? $head : null;
        }
        $ref = trim(substr($head, 4));
        $refPath = $gitDir . '/' . $ref;
        if (is_file($refPath)) {
            $sha = trim((string) file_get_contents($refPath));
            return $sha !== '' ? $sha : null;
        }
        return self::readPackedRef($gitDir, $ref);
    }

    private static function readPackedRef(string $gitDir, string $ref): ?string {
        $packedRefsPath = $gitDir . '/packed-refs';
        if (!is_file($packedRefsPath)) {
            return null;
        }
        $lines = file($packedRefsPath, FILE_IGNORE_NEW_LINES);
        foreach ($lines === false ? [] : $lines as $line) {
            if (str_ends_with($line, ' ' . $ref)) {
                return substr($line, 0, (int) strpos($line, ' '));
            }
        }
        return null;
    }

    private static function defaultPackageVersion(): ?string {
        if (!class_exists(InstalledVersions::class)) {
            return null;
        }
        try {
            if (InstalledVersions::isInstalled(self::PACKAGE_NAME)) {
                return InstalledVersions::getPrettyVersion(self::PACKAGE_NAME);
            }
            return InstalledVersions::getRootPackage()['pretty_version'] ?? null;
        } catch (OutOfBoundsException) {
            return null;
        }
    }
}
