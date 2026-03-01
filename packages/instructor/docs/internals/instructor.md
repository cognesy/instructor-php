## `StructuredOutput` class

`StructuredOutput` class is the main entry point to the library. It is responsible for
handling all interactions with the client code and internal Instructor components.


## Runtime composition

`StructuredOutput` is a facade over `StructuredOutputRuntime` + `StructuredOutputRequest`.

Main responsibilities:
- build immutable request state via fluent `with*()` methods
- delegate execution to runtime through `create()`
- expose convenience execution methods (`get()`, `response()`, `stream()`)

Advanced access:
- `runtime()` - returns current runtime object
- `withRequest(StructuredOutputRequest $request)` - replace the full request object

## Dispatched events

`StructuredOutput` class dispatches several high level events during initialization and processing
of the request and response:

 - `StructuredOutputStarted` - dispatched when structured output processing starts
 - `StructuredOutputRequestReceived` - dispatched when the request is received
 - `StructuredOutputResponseGenerated` - dispatched when the response is generated
 - `StructuredOutputResponseUpdated` - dispatched when the response update is streamed


## Event listeners

`StructuredOutput` class provides several methods allowing client code to plug
into Instructor event system, including:
 - `onEvent()` - to receive a callback when specified type of event is dispatched
 - `wiretap()` - to receive any event dispatched by Instructor


## Response model updates

`StructuredOutput` exposes model updates when streaming is enabled through:

 - `stream()->partials()` - for partial model updates
 - `stream()->sequence()` - for sequence updates
 - `onEvent()` with `PartialResponseGenerated` / `SequenceUpdated` for event-bus handling


## Error handling

`StructuredOutput` does not expose a dedicated global exception handler API.
Errors bubble to the caller unless your code handles them.

For observability and diagnostics, prefer:
- `onEvent(...)`
- `wiretap(...)`
- `dispatch(new Event(...))` for custom instrumentation events
