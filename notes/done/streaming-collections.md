# Streaming arrays / collections / iterables

> Priority: should have

Callback approach - provide callback to Instructor, which will be called for each
token received (?). It does not make sense for structured outputs, only if the result
is iterable / array.

Streamed responses require special handling in Instructor core - checking for "finishReason",
and handling other than "stop".

## Solution

New, streaming-friendly API code (based on Saloon) has been implemented and streaming
defect fixed (based on Adrien's feedback).
