# Events

## Event classes

Instructor dispatches multiple classes of events (all inheriting from `Event` class) during its execution. You can listen to these events and react to them in your application, for example to log information or to monitor the execution process.


## Receiving notification on internal events

Instructor allows you to receive detailed information at every stage of request and response processing via events.

* `(new Instructor)->onEvent(string $class, callable $callback)` method - receive callback when specified type of event is dispatched
* `(new Instructor)->wiretap(callable $callback)` method - receive any event dispatched by Instructor, may be useful for debugging or performance analysis
* `(new Instructor)->onError(callable $callback)` method - receive callback on any uncaught error, so you can customize handling it, for example logging the error or using some fallback mechanism in an attempt to recover

Receiving events can help you to monitor the execution process and makes it easier for a developer to understand and resolve any processing issues.

```php
$instructor = (new Instructor)
    // see requests to LLM
    ->onEvent(RequestSentToLLM::class, fn($e) => dump($e))
    // see responses from LLM
    ->onEvent(ResponseReceivedFromLLM::class, fn($event) => dump($event))
    // see all events in console-friendly format
    ->wiretap(fn($event) => dump($event->toConsole()))
    // log errors via your custom logger
    ->onError(fn($request, $error) => $logger->log($error));

$instructor->respond(
    messages: "What is the population of Paris?",
    responseModel: Scalar::integer(),
);
// check your console for the details on the Instructor execution
```
