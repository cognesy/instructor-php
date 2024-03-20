# NOTES



## Gaps or issues in docs or code

### Instructor init

Currently, Instructor uses Configuration::fresh() to always get new instance of
configuration. If switched to auto(), which returns singleton instance, we get
errors in tests - there's some problem to be diagnosed, its unclear why it happens.

### Handling of union types

Currently not supported, needs to be supported to allow better interaction with external code, eg. Carbon dates.

### Handling of some usual data types

 - date/time - via Carbon?
 - currency

### Integrations with DTO libraries

 - Spatie data object
 - Symfony DTO
 - Laravel DTO

### Test coverage

Catch up with the latest additions.

### Instructor::response()/request() default params

Default values are duplicated across method declarations and Request class.
Clean it up.

### Usage data for streamed responses

Usage for streamed responses is available via events, but not via rawResponse().
Should we provide some general way to handle usage data across LLM drivers? 

### Type adapters

To handle types like Carbon. Register adapters for given type and
use them in sequence until first succeeds.

### CanProvideExamples contract

Initially just to feed extraction prompts, esp. for weak models. Later: to
generate examples for prompt optimization.

### CanProcessResponseMessage

To manually process text of response.

### CanProcessRawResponse

To work with raw response object.

### Non empty constructors

Infer constructor arguments from the provided data

### Early validation of streamed responses

Currently, streamed responses are validated at the end of the process.
Validating response early would allow to get the correct response faster,
as we could restart processing and trigger inference re-attempt as soon
as we recognize that the response for sure will be invalid - esp. for weak
models.

### Moderation

Use moderation endpoint to automatically verify request prior to sending
it to the model.


## Design decisions to revisit

### Sequenceable/Sequence::toSchema()

Currently:

```php
public function toSchema(
    SchemaFactory $schemaFactory,
    TypeDetailsFactory $typeDetailsFactory,
): Schema {}
```

Should be changed to:

```php
public function toSchema(): Schema {}
```

But I need to figure out how to provide access to the SchemaFactory and TypeDetailsFactory.

### ResponseHandler

Currently, it's a big pile of `ifs`, should be refactored to a chain of responsibility or pipeline.

### Handling of Sequenceables and Partials

There must be a better, more generic way to do it.




## Research

- Queue-based load leveling
- Throttling
- Circuit breaker
- Producer-consumer / queue-worker
- Rate limiting
- Retry on service failure
- Backpressure
- Batch stage chain
- Request aggregator
- Rolling poller window
- Sparse task scheduler
- Marker and sweeper
- Actor model




## Other

### PR to OAI PHP client

RawResponseHandler to allow fixing issues with incorrect responses of OpenAI API proxies.

