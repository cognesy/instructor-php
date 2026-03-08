## `StructuredOutput` class

`StructuredOutput` class is the main entry point to the library. It is responsible for
handling all interactions with the client code and internal Instructor components.


## Runtime composition

`StructuredOutput` is a facade over `StructuredOutputRuntime` + `StructuredOutputRequest`.

Main responsibilities:
- build immutable request state via fluent `with*()` methods
- delegate execution to runtime through `create()`
- expose convenience execution methods (`get()`, `response()`, `stream()`)

`PendingStructuredOutput` stays as the public lazy handle, while mutable execution orchestration is kept behind an internal execution session so the pending wrapper itself stays narrow.

Advanced access:
- `withRuntime(CanCreateStructuredOutput $runtime)` - inject an already configured runtime
- `withRequest(StructuredOutputRequest $request)` - replace the full request object

## Dispatched events

`StructuredOutput` class dispatches several high level events during initialization and processing
of the request and response:

 - `StructuredOutputStarted` - dispatched when structured output processing starts
 - `StructuredOutputRequestReceived` - dispatched when the request is received
 - `StructuredOutputResponseGenerated` - dispatched when the response is generated
 - `StructuredOutputResponseUpdated` - dispatched when the response update is streamed


## Event listeners

`StructuredOutputRuntime` provides event registration methods for client code:
 - `onEvent()` - to receive a callback when specified type of event is dispatched
 - `wiretap()` - to receive any event dispatched by Instructor


## Response model updates

`StructuredOutput` exposes model updates when streaming is enabled through:

 - `stream()->partials()` - for partial model updates
 - `stream()->sequence()` - for sequence updates
 - `StructuredOutputRuntime->onEvent()` with `PartialResponseGenerated` / `SequenceUpdated` for event-bus handling

Internally, Instructor now accumulates streaming deltas inside
`StructuredOutputStreamState` and derives partial snapshots from that mutable state.


## Error handling

`StructuredOutput` does not expose a dedicated global exception handler API.
Errors bubble to the caller unless your code handles them.

For observability and diagnostics, prefer:
- `StructuredOutputRuntime->onEvent(...)`
- `StructuredOutputRuntime->wiretap(...)`
