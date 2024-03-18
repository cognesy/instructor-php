# `Instructor` class

Instructor class is the main entry point to the library. It is responsible for
handling all interactions with the client code and internal Instructor components.

One of the essential tasks of the `Instructor` class is to read the configuration
and use it to retrieve a component implementing `CanHandleRequest` interface (specified in the configuration) to process the request and return the response.

`Instructor` class dispatches several high level events during initialization and processing
of the request and response:

 - `InstructorStarted` - dispatched when Instructor is created
 - `InstructorReady` - dispatched when Instructor is configured and ready to process the request
 - `RequestReceived` - dispatched when the request is received
 - `ResponseGenerated` - dispatched when the response is generated
 - `ErrorRaised` - dispatched when an uncaught error occurs

`Instructor` class contains top level try-catch block to let user handle any uncaught
errors before throwing them to the client code.

`Instructor` class provides several methods allowing client code to plug
into Instructor event system, including:
 - onEvent() - to receive a callback when specified type of event is dispatched
 - wiretap() - to receive any event dispatched by Instructor
 - onError() - to receive callback on any uncaught error

Additionally, `Instructor` class provides convenience methods allowing client code to
receive model updates when streaming is enabled:
- onPartialUpdate() - to handle partial model updates of the response
- onSequenceUpdate() - to handle partial sequence updates of the response
