# Debugging

## Events

Instructor emits events at various points in its lifecycle, which you can listen to
and react to. You can use these events to debug execution flow and to inspect
data at various stages of processing.

For more details see the [Events](events.md) section.


## HTTP Debugging

Instructor gives you access to SaloonPHP debugging mode via API client class
`withDebug()` method. Calling it on a client instance causes underlying SaloonPHP
library to output HTTP request and response details to the console, so you can
see what is being sent to LLM API and what is being received.

You can also directly access Saloon connector instance via `connector()` method
on the client instance, and call Saloon debugging methods on it - see SaloonPHP
debugging documentation for more details:
https://docs.saloon.dev/the-basics/debugging

Additionally, `connector()` method on the client instance allows you to access
other capabilities of Saloon connector, such as setting or modifying middleware.
See SaloonPHP documentation for more details:
https://docs.saloon.dev/digging-deeper/middleware

