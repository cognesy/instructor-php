## `StructuredOutput` class

`StructuredOutput` class is the main entry point to the library. It is responsible for
handling all interactions with the client code and internal Instructor components.


## Request handlers

One of the essential tasks of the `StructuredOutput` class is to read the configuration
and use it to retrieve a component implementing `CanHandleRequest` interface (specified in the configuration) to process the request and return the response.


## Dispatched events

`StructuredOutput` class dispatches several high level events during initialization and processing
of the request and response:

 - `InstructorStarted` - dispatched when Instructor is created
 - `InstructorReady` - dispatched when Instructor is configured and ready to process the request
 - `RequestReceived` - dispatched when the request is received
 - `ResponseGenerated` - dispatched when the response is generated


## Event listeners

`StructuredOutput` class provides several methods allowing client code to plug
into Instructor event system, including:
 - `onEvent()` - to receive a callback when specified type of event is dispatched
 - `wiretap()` - to receive any event dispatched by Instructor


## Response model updates

Additionally, `StructuredOutput` class provides convenience methods allowing client code to
receive model updates when streaming is enabled:

 - `onPartialUpdate()` - to handle partial model updates of the response
 - `onSequenceUpdate()` - to handle partial sequence updates of the response


## Error handling

`StructuredOutput` class contains top level try-catch block to let user handle any uncaught
errors before throwing them back to the client code. It allows you to register a handler
which will log the error or notify your monitoring system about a problem.
