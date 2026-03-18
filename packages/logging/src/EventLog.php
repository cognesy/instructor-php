<?php declare(strict_types=1);

namespace Cognesy\Logging;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Logging\Config\EventLogConfig;
use Cognesy\Logging\Filters\EventClassFilter;
use Cognesy\Logging\Filters\EventHierarchyFilter;
use Cognesy\Logging\Filters\LogLevelFilter;
use Cognesy\Logging\Integrations\EventPipelineWiretap;
use Cognesy\Logging\Observability\FileJsonLogWriter;
use Cognesy\Logging\Observability\StructuredEventFormatter;
use Cognesy\Logging\Pipeline\LoggingPipeline;
use Psr\Log\LoggerInterface;

/**
 * Factory for event dispatchers with optional structured file logging.
 *
 * Usage in runtime default paths:
 *
 *   // Before
 *   $events = new EventDispatcher('instructor.runtime');
 *
 *   // After
 *   $events = EventLog::root('instructor.runtime');
 *
 * Set INSTRUCTOR_LOG_PATH=/path/to/file.jsonl to enable automatic JSONL logging.
 * Or call EventLog::enable('/path/to/file.jsonl') / EventLog::enable(new EventLogConfig(...))
 * once at application bootstrap.
 *
 * When a framework (Laravel/Symfony) injects $events explicitly, resolveEvents()
 * receives a non-null argument and EventLog::root() is never called.
 */
final class EventLog
{
    private static ?EventLogConfig $enabledConfig = null;

    /**
     * Opt in to file logging for this process.
     * Call once in bootstrap (AppServiceProvider::boot(), index.php, etc.)
     * Explicit programmatic config takes precedence over YAML and environment defaults.
     */
    public static function enable(string|EventLogConfig $config): void
    {
        self::$enabledConfig = match (true) {
            is_string($config) => EventLogConfig::default()->withOverrides(['path' => $config]),
            default => $config,
        };
    }

    /**
     * Disable programmatic logging (resets the config set via enable()).
     * Does not affect values read from the environment or YAML defaults.
     */
    public static function disable(): void
    {
        self::$enabledConfig = null;
    }

    /**
     * Creates a root dispatcher.
     *
     * If the resolved EventLogConfig enables a file sink, attaches a JSONL logging
     * wiretap at the configured minimum level. Otherwise returns a plain dispatcher
     * with no file I/O.
     *
     * The optional $logger parameter allows injecting a PSR-3 logger as an additional
     * sink alongside (or instead of) the file writer.
     */
    public static function root(
        string $name,
        ?LoggerInterface $logger = null,
    ): CanHandleEvents {
        $dispatcher = new EventDispatcher($name);
        $config = self::resolveConfig();

        if (!$config->isEnabled() && $logger === null) {
            return $dispatcher;
        }

        $pipeline = self::makePipeline($name, $config);

        if ($config->isEnabled()) {
            $pipeline = $pipeline->write(new FileJsonLogWriter($config->path));
        }

        if ($logger !== null) {
            $pipeline = $pipeline->write(new Writers\PsrLoggerWriter($logger));
        }

        $dispatcher->wiretap(new EventPipelineWiretap($pipeline->build()));

        return $dispatcher;
    }

    /**
     * Creates a child dispatcher that bubbles events to $parent.
     *
     * Never attaches its own wiretap — logging is handled at the root.
     */
    public static function child(
        string $name,
        CanHandleEvents $parent,
    ): CanHandleEvents {
        return new EventDispatcher($name, $parent);
    }

    // INTERNAL ///////////////////////////////////////////////////////////////////

    private static function resolveConfig(): EventLogConfig
    {
        if (self::$enabledConfig !== null) {
            return self::$enabledConfig;
        }

        return EventLogConfig::default();
    }

    private static function makePipeline(string $name, EventLogConfig $config): LoggingPipeline
    {
        $pipeline = LoggingPipeline::create()
            ->filter(new LogLevelFilter($config->level));

        if ($config->includeEvents !== []) {
            $pipeline = $pipeline->filter(self::makeEventFilter(
                excludedClasses: [],
                includedClasses: $config->includeEvents,
                useHierarchyFilter: $config->useHierarchyFilter,
            ));
        }

        if ($config->excludeEvents !== []) {
            $pipeline = $pipeline->filter(self::makeEventFilter(
                excludedClasses: $config->excludeEvents,
                includedClasses: [],
                useHierarchyFilter: $config->useHierarchyFilter,
            ));
        }

        if ($config->excludeHttpDebug) {
            $pipeline = $pipeline->filter(EventHierarchyFilter::excludeHttpDebug());
        }

        return $pipeline->format(new StructuredEventFormatter(
            component: $name,
            includePayload: $config->includePayload,
            includeCorrelation: $config->includeCorrelation,
            includeEventMetadata: $config->includeEventMetadata,
            includeComponentMetadata: $config->includeComponentMetadata,
            stringClipLength: $config->stringClipLength,
        ));
    }

    /**
     * @param list<string> $excludedClasses
     * @param list<string> $includedClasses
     */
    private static function makeEventFilter(
        array $excludedClasses,
        array $includedClasses,
        bool $useHierarchyFilter,
    ): EventClassFilter|EventHierarchyFilter {
        return match ($useHierarchyFilter) {
            true => new EventHierarchyFilter($excludedClasses, $includedClasses),
            false => new EventClassFilter($excludedClasses, $includedClasses),
        };
    }
}
