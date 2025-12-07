<?php

declare(strict_types=1);

namespace Cognesy\Logging\Pipeline;

use Cognesy\Events\Event;
use Cognesy\Logging\Contracts\EventFilter;
use Cognesy\Logging\Contracts\EventEnricher;
use Cognesy\Logging\Contracts\EventFormatter;
use Cognesy\Logging\Contracts\LogWriter;

/**
 * Functional pipeline builder for event logging
 */
final class LoggingPipeline
{
    /** @var EventFilter[] */
    private array $filters = [];

    /** @var EventEnricher[] */
    private array $enrichers = [];

    /** @var EventFormatter[] */
    private array $formatters = [];

    /** @var LogWriter[] */
    private array $writers = [];

    private function __construct() {}

    public static function create(): self
    {
        return new self();
    }

    public function filter(EventFilter $filter): self
    {
        $this->filters[] = $filter;
        return $this;
    }

    public function enrich(EventEnricher $enricher): self
    {
        $this->enrichers[] = $enricher;
        return $this;
    }

    public function format(EventFormatter $formatter): self
    {
        $this->formatters[] = $formatter;
        return $this;
    }

    public function write(LogWriter $writer): self
    {
        $this->writers[] = $writer;
        return $this;
    }

    /**
     * Build the pipeline into a callable that can be used as an event listener
     * @return callable(Event): void
     */
    public function build(): callable
    {
        return function (Event $event): void {
            // Apply all filters - if any returns false, skip processing
            foreach ($this->filters as $filter) {
                if (!$filter($event)) {
                    return; // Event filtered out
                }
            }

            // Enrich the event with context from all enrichers
            $context = null;
            foreach ($this->enrichers as $enricher) {
                $newContext = $enricher($event);

                if ($context === null) {
                    $context = $newContext;
                } else {
                    // Merge contexts
                    $context = $context
                        ->withFrameworkContext($newContext->frameworkContext)
                        ->withPerformanceMetrics($newContext->performanceMetrics)
                        ->withUserContext($newContext->userContext);
                }
            }

            // If no enrichers, create basic context
            if ($context === null) {
                $context = \Cognesy\Logging\LogContext::fromEvent($event);
            }

            // Format with all formatters (chain them)
            $logEntry = null;
            foreach ($this->formatters as $formatter) {
                if ($logEntry === null) {
                    $logEntry = $formatter($event, $context);
                } else {
                    // Apply formatter to modify existing entry
                    $newEntry = $formatter($event, $context);
                    $logEntry = $logEntry
                        ->withMessage($newEntry->message)
                        ->withContext(array_merge($logEntry->context, $newEntry->context))
                        ->withLevel($newEntry->level)
                        ->withChannel($newEntry->channel);
                }
            }

            // If no formatters, use default
            if ($logEntry === null) {
                $logEntry = \Cognesy\Logging\LogEntry::create(
                    level: $event->logLevel,
                    message: $event->name(),
                    context: $context->toArray(),
                );
            }

            // Write to all writers
            foreach ($this->writers as $writer) {
                $writer($logEntry);
            }
        };
    }

    /**
     * Build and return the pipeline as a simple callable
     * @return callable(Event): void
     */
    public function __invoke(): callable
    {
        return $this->build();
    }
}