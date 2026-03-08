<?php declare(strict_types=1);

namespace Cognesy\Utils\Profiler;

use WeakMap;

final class ObjectCreationTrace
{
    private static bool $enabled = false;
    private static bool $captureCallsites = false;
    /** @var array<string, true> */
    private static array $trackedClasses = [];
    /** @var array<string, int> */
    private static array $createdByClass = [];
    /** @var array<string, array<string, int>> */
    private static array $createdByCallsite = [];
    /** @var ObjectCreationSnapshot[] */
    private static array $samples = [];
    /** @var WeakMap<object, class-string>|null */
    private static ?WeakMap $instances = null;

    /**
     * @param list<string> $trackedClasses
     */
    public static function enable(array $trackedClasses = [], bool $captureCallsites = false): void
    {
        self::reset();
        self::$enabled = true;
        self::$captureCallsites = $captureCallsites;
        self::$trackedClasses = array_fill_keys($trackedClasses, true);
        self::$instances = new WeakMap();
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public static function reset(): void
    {
        self::$enabled = false;
        self::$captureCallsites = false;
        self::$trackedClasses = [];
        self::$createdByClass = [];
        self::$createdByCallsite = [];
        self::$samples = [];
        self::$instances = null;
    }

    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    public static function record(object $object): void
    {
        if (!self::$enabled) {
            return;
        }

        $class = $object::class;
        if (!self::shouldTrack($class)) {
            return;
        }

        $instances = self::instances();
        $instances[$object] = $class;
        self::$createdByClass[$class] = (self::$createdByClass[$class] ?? 0) + 1;

        if (!self::$captureCallsites) {
            return;
        }

        $callsite = self::resolveCallsite();
        self::$createdByCallsite[$class][$callsite] = (self::$createdByCallsite[$class][$callsite] ?? 0) + 1;
    }

    public static function sample(string $label, bool $collectCycles = true): ObjectCreationSnapshot
    {
        if ($collectCycles) {
            gc_collect_cycles();
        }

        $createdByClass = self::sorted(self::$createdByClass);
        $liveByClass = self::liveByClass();

        $snapshot = new ObjectCreationSnapshot(
            label: $label,
            memoryUsage: memory_get_usage(false),
            realMemoryUsage: memory_get_usage(true),
            createdTotal: array_sum($createdByClass),
            liveTotal: array_sum($liveByClass),
            createdByClass: $createdByClass,
            liveByClass: $liveByClass,
        );

        self::$samples[] = $snapshot;

        return $snapshot;
    }

    /**
     * @return ObjectCreationSnapshot[]
     */
    public static function samples(): array
    {
        return self::$samples;
    }

    /**
     * @return array<string, int>
     */
    public static function createdByClass(): array
    {
        return self::sorted(self::$createdByClass);
    }

    /**
     * @return array<string, int>
     */
    public static function liveByClass(): array
    {
        $live = [];

        if (self::$instances === null) {
            return $live;
        }

        foreach (self::$instances as $class) {
            $live[$class] = ($live[$class] ?? 0) + 1;
        }

        return self::sorted($live);
    }

    /**
     * @return array<string, array<string, int>>
     */
    public static function createdByCallsite(): array
    {
        $sorted = self::$createdByCallsite;
        ksort($sorted);

        foreach ($sorted as &$callsites) {
            ksort($callsites);
        }

        return $sorted;
    }

    public static function createdCount(string $class): int
    {
        return self::$createdByClass[$class] ?? 0;
    }

    public static function liveCount(string $class): int
    {
        return self::liveByClass()[$class] ?? 0;
    }

    /**
     * @return array{createdByClass: array<string, int>, createdByCallsite: array<string, array<string, int>>, samples: list<array<string, mixed>>}
     */
    public static function report(): array
    {
        return [
            'createdByClass' => self::createdByClass(),
            'createdByCallsite' => self::createdByCallsite(),
            'samples' => array_map(
                static fn(ObjectCreationSnapshot $sample): array => $sample->toArray(),
                self::$samples,
            ),
        ];
    }

    public static function toJson(): string
    {
        return (string) json_encode(self::report(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private static function instances(): WeakMap
    {
        if (self::$instances === null) {
            self::$instances = new WeakMap();
        }

        return self::$instances;
    }

    private static function shouldTrack(string $class): bool
    {
        if (self::$trackedClasses === []) {
            return true;
        }

        return isset(self::$trackedClasses[$class]);
    }

    private static function resolveCallsite(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6);

        foreach ($trace as $frame) {
            /** @var array{function: string, class?: string, file?: string, line?: int} $frame */
            $class = $frame['class'] ?? '';
            $function = $frame['function'];

            if ($class === self::class) {
                continue;
            }

            if ($class === TracksObjectCreation::class || $function === 'trackObjectCreation') {
                continue;
            }

            if ($function === '__construct') {
                continue;
            }

            $file = $frame['file'] ?? 'unknown';
            $line = $frame['line'] ?? 0;

            return "{$file}:{$line}";
        }

        return 'unknown';
    }

    /**
     * @param array<string, int> $values
     * @return array<string, int>
     */
    private static function sorted(array $values): array
    {
        ksort($values);
        return $values;
    }
}
