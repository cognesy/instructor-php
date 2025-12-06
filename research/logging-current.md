 Current Logging Architecture

  1. Framework & Standards

  - PSR-3 Compliant: Uses the PHP Standard Recommendation for logging interface
  - Event-Driven: All logging is based on an events system rather than direct logging calls
  - Optional Dependencies: No mandatory logging library - users can bring their own PSR-3 logger

  2. Core Components

  Event Base Class (packages/events/src/Event.php)

  Every event has built-in logging properties:
  public string $logLevel = LogLevel::DEBUG;  // Customizable per event
  public function asLog(): string             // Formats event as log string
  public function asConsole(): string         // Formats for console output

  Event Dispatcher System

  - EventDispatcher: PSR-14 compliant event dispatcher
  - Wiretap Pattern: Global listeners that capture all events
  - Priority-based: Listeners execute by priority order
  - Framework Integrations: Laravel and Symfony event dispatcher adapters

  3. How Logging Works

  Pattern 1: Wiretap (Global Logging)

  $logger = new StdoutLogger();  // Your PSR-3 logger

  $person = (new StructuredOutput)
      ->wiretap(fn(Event $e) =>
          $logger->log($e->logLevel, $e->name(), ['id' => $e->id, 'data' => $e->data])
      )
      ->withMessages("Jason is 25 years old")
      ->withResponseClass(User::class)
      ->get();

  Pattern 2: Event-Specific Logging

  $output = (new StructuredOutput)
      ->onEvent(ResponseValidationFailed::class, fn($e) =>
          $logger->error("Validation failed: {$e->name()}", (array)$e->data)
      )
      ->withMessages($text)
      ->withResponseClass(User::class)
      ->get();

  4. Event Categories & Log Levels

  Instructor Events (packages/instructor/src/Events/)

  - StructuredOutputStarted (DEBUG)
  - ResponseValidationFailed (WARNING)
  - ResponseGenerationFailed (ERROR)
  - StructuredOutputRecoveryLimitReached (ERROR)
  - ChunkReceived, PartialJsonReceived (DEBUG)

  HTTP Client Events (packages/http-client/src/Events/)

  - HttpRequestSent (DEBUG)
  - HttpResponseReceived (DEBUG)
  - HttpRequestFailed (ERROR)
  - DebugRequestBodyUsed, DebugResponseBodyReceived (DEBUG)

  5. Built-in Logging Utilities

  FileLogger (packages/setup/src/Loggers/FileLogger.php)

  Simple PSR-3 implementation for file logging:
  class FileLogger implements LoggerInterface {
      public function log($level, string|\Stringable $message, array $context = []): void {
          $timestamp = date('[Y-m-d H:i:s]');
          $logMessage = sprintf('%s [%s] %s', $timestamp, strtoupper((string)$level), (string)$message);
          file_put_contents($this->logFile, $logMessage . PHP_EOL, FILE_APPEND);
      }
  }

  Event Formatting

  - logFormat(): (id) timestamp (level) [FullClassName] - message
  - consoleFormat(): Column-aligned console output
  - logFilter(): Filters events by severity level

  6. Configuration & Control

  Debug Configuration

  The system includes configurable debug flags:
  // DebugConfig controls what gets logged
  $config = new DebugConfig(
      httpRequestUrl: true,
      httpRequestBody: false,
      httpResponseBody: true,
      // ... other flags
  );

  Conditional Event Dispatch

  Events are only dispatched when relevant debug flags are enabled, improving performance.

  7. Integration Examples

  The codebase includes examples for:
  - Basic PSR-3 logging (examples/A02_Advanced/LoggingPSR/)
  - Monolog integration (examples/A02_Advanced/LoggingMonolog/)
  - HTTP middleware logging with custom middleware classes

  8. Key Design Principles

  1. Minimal Dependencies: No required logging library
  2. PSR Standards: PSR-3 (Logging) and PSR-14 (Events) compliant
  3. Performance Conscious: Conditional event dispatch
  4. Framework Agnostic: Works with any PSR-3 logger (Monolog, Laravel Log, etc.)
  5. Rich Context: Events carry structured data for detailed logging

  9. Current Logging Flow

  Application Code
      ↓
  StructuredOutput (with HandlesEvents trait)
      ↓
  Dispatch Event (with logLevel property)
      ↓
  EventDispatcher
      ↓
  Wiretap/OnEvent Listeners
      ↓
  User's PSR-3 Logger
      ↓
  Output (File, Console, External Service)

  This architecture provides a flexible, standards-compliant logging system that integrates seamlessly with existing PHP logging ecosystems while maintaining excellent performance and minimal overhead.
