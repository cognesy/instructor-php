# NOTES

## Gaps or issues in docs or code

## Design decisions to revisit

## Example ideas

Examples to demonstrate use cases.

## Other

### Test coverage

Catch up with the latest additions.

## Brain dump

- Finish logging support
- Document how to inject custom HTTP client
- Better error messages
- Documentation for logging
- Finish module observability via events - currently no access to this info & only 2 events supported
- Rework Events so they have toArray() method, make __toString() use it
- Role - should be enum, not string
- String >> Array >> Class - for example: prompts (they should be classes)
- Prompt - should be a class, not a string; prompt translates to Section/Messages; alt name: Instruction(s)
- Better API for image / audio inputs
 
- Models to implement JsonSerializable: https://www.php.net/manual/en/jsonserializable.jsonserialize.php / https://www.sitepoint.com/use-jsonserializable-interface/
- Support JsonException for serialization / deserialization errors - https://www.php.net/manual/en/class.jsonexception.php
- ValueError - https://www.php.net/manual/en/class.valueerror.php
- Use LLM to generate Example based on the class - just render object to JSON + add schema as an explanation - this should give the model enough info to come up with something that makes sense
- Sequences - validate only individual items, reject ONLY the invalid; allows progressive extraction in multiple stages
- Extract APIClient to a separate package?
- example of integration with Laravel/Livewire
- DSPy next steps: eval, optimize, compile
- Add more modules: XoT, RAG, ReAct, etc.
- RAG - how to handle multiple VDB providers?
- Test validation in modules - provide an example
- Parallel execution of modules (e.g. a la Laravel jobs?)
- How to track API rate limits across multiple requests / parallel executions
- Moderation endpoint support
- Make using DocBlocks optional - it may not always to be desired to pass this info to LLM
- Git/GitHub integration module to allow easy automation
- Data mapping module(s) for easier data transformations
- Add super detailed tests of Module core functionality - esp. around input/output mappings
- How to handle dynamic module graph definition + visualization
- Foreign client support - OpenAI PHP, Amazon Bedrock SDK, etc. - via some kind of adapters?
- JSON Schema management for input/output definitions

## To research

- Schema.org ld+json // Spatie https://github.com/spatie/schema-org // https://developers.google.com/search/docs/appearance/structured-data?hl=pl
- OpenAPI Schema
- nette/schema https://github.com/nette/schema
- https://flow-php.com/
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
