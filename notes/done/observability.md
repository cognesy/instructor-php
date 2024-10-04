# Observability


## Problem and ideas

> Priority: must have

Requirements and solution - to be analyzed

- How to track regular vs streamed responses? Streamed responses are unreadable / meaningless individually. Higher abstraction layer is needed to handle them - e.g. "folder" with individual chunks of data. Completion ID allows to track incoming chunks under a single context.
- Completion, if streamed, needs extra info on whether it has been completed or disrupted for any reason.


## Solution

You can:
- wiretap() to get stream of all internal events
- connect to specific events via onEvent()

This allows you plug in your preferred logging / monitoring system.

- Performance - timestamps are available on events, which allows you to record performance of either full flow or individual steps.
- Errors - can be done via onError() - has to be changed now
- Validation errors - can be done via onEvent()
- Generated data models - can be done via onEvent()

