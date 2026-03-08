## Event classes

Instructor dispatches multiple classes of events during its execution. All of them are descendants of `Event` class.

You can listen to these events and react to them in your application, for example to log information or to monitor the execution process.

Check the list of available event classes in the `Cognesy\Instructor\Events` namespace.

## Event methods

Each Instructor event offers following methods, which make interacting with them more convenient:

 * `print()` - prints a string representation of the event to console output
 * `printDebug()` - prints a string representation of the event to console output, with additional debug information
 * `asConsole()` - returns the event in a format suitable for console output
 * `asLog()` - returns the event in a format suitable for logging


## Receiving notification on internal events

Instructor allows you to receive detailed information at every stage of request and response processing via events.

 * `StructuredOutputRuntime->onEvent(string $class, callable $callback)` - receive callback when specified type of event is dispatched
 * `StructuredOutputRuntime->wiretap(callable $callback)` - receive any event dispatched by Instructor, may be useful for debugging or performance analysis

Receiving events can help you to monitor the execution process and makes it easier for a developer to understand and resolve any processing issues.

```php
$runtime = StructuredOutputRuntime::fromProvider(LLMProvider::new())
    // see requests to LLM
    ->onEvent(HttpRequestSent::class, fn($e) => dump($e))
    // see responses from LLM
    ->onEvent(HttpResponseReceived::class, fn($event) => dump($event))
    // see all events in console-friendly format
    ->wiretap(fn($event) => $event->print())
    // log all events in log-friendly format
    ->wiretap(fn($event) => YourLogger::log($event->asLog()));

(new StructuredOutput)->withRuntime($runtime)->with(
    messages: "What is the population of Paris?",
    responseModel: Scalar::integer(),
)->get();
// check your console for the details on the Instructor execution
```


## Stream and event options for model updates

`StructuredOutput` exposes streamed model updates through `StructuredOutputStream`:

 * `stream()->partials()` - partial model snapshots
 * `stream()->sequence()` - completed sequence-item snapshots

You can also subscribe to event bus notifications:

 * `StructuredOutputRuntime->onEvent(PartialResponseGenerated::class, callable $callback)`
 * `StructuredOutputRuntime->onEvent(SequenceUpdated::class, callable $callback)`
