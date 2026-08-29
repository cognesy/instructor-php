<?php

declare(strict_types=1);

namespace Cognesy\Tell\Configuration;

use InvalidArgumentException;

/** Resolved, finite execution limits for one Tell invocation. */
final readonly class TellExecutionPolicy
{
    public const int DEFAULT_MAX_RETRIES = 0;
    public const int DEFAULT_TIMEOUT_MS = 30_000;
    public const int DEFAULT_MAX_OUTPUT_CHARS = 200_000;
    public const int DEFAULT_MAX_TOOL_OUTPUT_CHARS = 40_000;
    public const int DEFAULT_MAX_TOOL_CALLS = 100;

    /**
     * The ceiling on one spilled tool result, and the switch for spilling
     * itself: zero turns spilling off, and the older head/tail truncation is
     * what a tool result over `maxToolOutputChars` gets instead. It is the
     * same size as a whole turn's model-output budget - large enough for a
     * long test run or build log, small enough that a command's captured
     * output stays a bounded cost.
     */
    public const int DEFAULT_MAX_SPILL_BYTES = 200_000;

    /**
     * How many bytes a spill stub may spend on the head of the result it
     * replaces. Zero keeps the header and the read hint and drops the preview.
     */
    public const int DEFAULT_MAX_STUB_BYTES = 2_000;

    public const int MAX_RETRIES = 10;
    public const int MAX_TIMEOUT_MS = 3_600_000;
    public const int MAX_OUTPUT_CHARS = 1_000_000;
    public const int MAX_TOOL_OUTPUT_CHARS = 250_000;
    public const int MAX_TOOL_CALLS = 1_000;
    public const int MAX_SPILL_BYTES = 5_000_000;
    public const int MAX_STUB_BYTES = 100_000;

    /** @param array<string, 'cli'|'branch'|'project'|'user'|'bundled'> $provenance */
    public function __construct(
        public int $maxRetries = self::DEFAULT_MAX_RETRIES,
        public int $timeoutMs = self::DEFAULT_TIMEOUT_MS,
        public int $maxOutputChars = self::DEFAULT_MAX_OUTPUT_CHARS,
        public int $maxToolOutputChars = self::DEFAULT_MAX_TOOL_OUTPUT_CHARS,
        public int $maxToolCalls = self::DEFAULT_MAX_TOOL_CALLS,
        public int $maxSpillBytes = self::DEFAULT_MAX_SPILL_BYTES,
        public int $maxStubBytes = self::DEFAULT_MAX_STUB_BYTES,
        private array $provenance = [],
    ) {
        self::assertRange('maxRetries', $this->maxRetries, 0, self::MAX_RETRIES);
        self::assertRange('timeoutMs', $this->timeoutMs, 1, self::MAX_TIMEOUT_MS);
        self::assertRange('maxOutputChars', $this->maxOutputChars, 1, self::MAX_OUTPUT_CHARS);
        self::assertRange('maxToolOutputChars', $this->maxToolOutputChars, 1, self::MAX_TOOL_OUTPUT_CHARS);
        self::assertRange('maxToolCalls', $this->maxToolCalls, 0, self::MAX_TOOL_CALLS);
        self::assertRange('maxSpillBytes', $this->maxSpillBytes, 0, self::MAX_SPILL_BYTES);
        self::assertRange('maxStubBytes', $this->maxStubBytes, 0, self::MAX_STUB_BYTES);
    }

    /**
     * Whether a tool result larger than `maxToolOutputChars` is written to a
     * blob in Tell's own storage and replaced with a stub the model can read
     * back.
     */
    public function spillsToolOutput(): bool {
        return $this->maxSpillBytes > 0;
    }

    public static function defaults(): self {
        return new self(provenance: self::bundledProvenance());
    }

    /**
     * @param  array<string, mixed>  $branchValues
     * @param  array<string, int>  $cliOverrides
     * @param  array<string, int>  $projectDefaults
     * @param  array<string, int>  $userDefaults
     */
    public static function resolve(
        array $branchValues,
        array $cliOverrides = [],
        array $projectDefaults = [],
        array $userDefaults = [],
    ): self {
        $defaults = self::defaults()->values();
        $values = $defaults;
        $provenance = self::bundledProvenance();
        foreach (array_keys($defaults) as $key) {
            if (array_key_exists($key, $userDefaults)) {
                $values[$key] = self::integer($key, $userDefaults[$key]);
                $provenance[$key] = 'user';
            }
            if (array_key_exists($key, $projectDefaults)) {
                $values[$key] = self::integer($key, $projectDefaults[$key]);
                $provenance[$key] = 'project';
            }
            if (array_key_exists($key, $branchValues)) {
                $values[$key] = self::integer($key, $branchValues[$key]);
                $provenance[$key] = 'branch';
            }
            if (array_key_exists($key, $cliOverrides)) {
                $values[$key] = self::integer($key, $cliOverrides[$key]);
                $provenance[$key] = 'cli';
            }
        }

        return new self(
            maxRetries: $values['maxRetries'],
            timeoutMs: $values['timeoutMs'],
            maxOutputChars: $values['maxOutputChars'],
            maxToolOutputChars: $values['maxToolOutputChars'],
            maxToolCalls: $values['maxToolCalls'],
            maxSpillBytes: $values['maxSpillBytes'],
            maxStubBytes: $values['maxStubBytes'],
            provenance: $provenance,
        );
    }

    /** @return array{maxRetries: int, timeoutMs: int, maxOutputChars: int, maxToolOutputChars: int, maxToolCalls: int, maxSpillBytes: int, maxStubBytes: int} */
    public function values(): array {
        return [
            'maxRetries' => $this->maxRetries,
            'timeoutMs' => $this->timeoutMs,
            'maxOutputChars' => $this->maxOutputChars,
            'maxToolOutputChars' => $this->maxToolOutputChars,
            'maxToolCalls' => $this->maxToolCalls,
            'maxSpillBytes' => $this->maxSpillBytes,
            'maxStubBytes' => $this->maxStubBytes,
        ];
    }

    /** @return array<string, 'cli'|'branch'|'project'|'user'|'bundled'> */
    public function provenance(): array {
        return $this->provenance === [] ? self::bundledProvenance() : $this->provenance;
    }

    /** @return array{values: array<string, int>, provenance: array<string, 'cli'|'branch'|'project'|'user'|'bundled'>} */
    public function toArray(): array {
        return ['values' => $this->values(), 'provenance' => $this->provenance()];
    }

    private static function integer(string $name, mixed $value): int {
        if (is_int($value) || (is_string($value) && preg_match('/^-?\\d+$/', $value) === 1)) {
            return (int) $value;
        }
        throw new InvalidArgumentException("{$name} must be an integer.");
    }

    private static function assertRange(string $name, int $value, int $minimum, int $maximum): void {
        if ($value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException("{$name} must be between {$minimum} and {$maximum}.");
        }
    }

    /** @return array<string, 'bundled'> */
    private static function bundledProvenance(): array {
        return array_fill_keys(array_keys(self::defaultsValues()), 'bundled');
    }

    /** @return array<string, int> */
    private static function defaultsValues(): array {
        return [
            'maxRetries' => self::DEFAULT_MAX_RETRIES,
            'timeoutMs' => self::DEFAULT_TIMEOUT_MS,
            'maxOutputChars' => self::DEFAULT_MAX_OUTPUT_CHARS,
            'maxToolOutputChars' => self::DEFAULT_MAX_TOOL_OUTPUT_CHARS,
            'maxToolCalls' => self::DEFAULT_MAX_TOOL_CALLS,
            'maxSpillBytes' => self::DEFAULT_MAX_SPILL_BYTES,
            'maxStubBytes' => self::DEFAULT_MAX_STUB_BYTES,
        ];
    }
}
