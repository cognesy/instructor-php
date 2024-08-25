# NOTES

## Gaps or issues in docs or code

## Design decisions to revisit

## Example ideas

Examples to demonstrate use cases.

## Other

### Test coverage

Catch up with the latest additions.


## TODOs

- Parallel tool calls
- Multiple tools with tool selection
- Modules: Add module observability via events - currently no access to this info
- Generate unstructured, then format to structured - to improve reasoning

### API Client

- Clean up predefined models, prices, etc.

### Configuration

- Export configuration to user folder / use external configuration
- Examples how to override default configuration

### Refactorings

- More modular design - serialization, validation, transformation should be a configurable pipeline
- Validators / Deserializers / Transformers - chain of objects, not a single object
- Rework Events so they have toArray() method, make __toString() use it
- Role - should be enum, not string
- String >> Array >> Class - for example: prompts (they should be classes)
- Prompt - should be a class, not a string; prompt translates to Section/Messages; alt name: Instruction(s)

### Infrastructure

- Async mode
- Fix cache mode
- Finish logging support
- Document how to inject custom HTTP client
- Better error messages
- Documentation for logging
- PSR-14 events - finish, demo how to plug custom dispatcher
- PSR-11 container - finish, demo how to plug custom container
- PSR-3 logger - finish, demo how to plug custom logger

### Partially done

- Better API for image / audio inputs
- Make system, prompt, script etc. available for configuration by user



## Brain dump

- Streaming JSON parser - https://github.com/kelunik/streaming-json
- Retry - https://github.com/kelunik/retry
- Rate limiter - https://github.com/kelunik/rate-limit
- Check for broken links - https://github.com/kelunik/link-check
- Async flow execution - https://github.com/darkwood-com/flow
- Models to implement JsonSerializable: https://www.php.net/manual/en/jsonserializable.jsonserialize.php / https://www.sitepoint.com/use-jsonserializable-interface/
- Support JsonException for serialization / deserialization errors - https://www.php.net/manual/en/class.jsonexception.php
- ValueError - https://www.php.net/manual/en/class.valueerror.php
- Task runner - https://robo.li/
- Hub >> Laravel Zero - https://laravel-zero.com/
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
