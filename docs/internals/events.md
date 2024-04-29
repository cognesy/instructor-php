# Events

## Event classes

Instructor dispatches multiple classes of events (all inheriting from `Event` class) during its execution. You can listen to these events and react to them in your application, for example to log information or to monitor the execution process.

Check the list of available event classes in the `Cognesy\Instructor\Events` namespace.

## Event methods

Each Instructor event offers following methods, which make interacting with them more convenient:

 * `print()` - prints a string representation of the event to console output
 * `asConsole()` - returns the event in a format suitable for console output
 * `asLog()` - returns the event in a format suitable for logging


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
    ->wiretap(fn($event) => $event->print())
    // log all events in log-friendly format
    ->wiretap(fn($event) => YourLogger::log($event->asLog()))
    // log errors via your custom logger
    ->onError(fn($request, $error) => YourLogger::log($error)));

$instructor->respond(
    messages: "What is the population of Paris?",
    responseModel: Scalar::integer(),
);
// check your console for the details on the Instructor execution
```


## onError handler

`Instructor->onError(callable $callback)` method allows you to receive callback
on any uncaught error, so you can customize handling it, for example logging the
error or using some fallback mechanism in an attempt to recover.

In case Instructor encounters any error that it cannot handle, your callable (if
defined) will be called with an instance of `ErrorRaised` event, which contains
information about the error and request that caused it (among some other properties).

In most cases, after you process the error (e.g. store it in a log via some logger)
the best way to proceed is to rethrow the error.

If you do not rethrow the error and just return some value, Instructor will return
it as a result of response processing. This way you can provide a fallback response,
e.g. with an object with default values.


## Convenience methods for get streamed model updates

`Instructor` class provides convenience methods allowing client code to receive
model updates  when streaming is enabled:

 * `onPartialUpdate(callable $callback)` - to handle partial model updates of the response
 * `onSequenceUpdate(callable $callback)` - to handle partial sequence updates of the response

In both cases your callback will receive updated model, so you don't have to
extract it from the event.
