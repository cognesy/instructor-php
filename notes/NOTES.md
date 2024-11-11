# NOTES

## High priority

- Evals / eval framework
  * execution level correctness metric
- Add 'Output' section to each example, generate it and include in docs, so reader can see what they can expect
- Logging via PSR-3
- Schema abstraction layer - decouple names and descriptions from the model
- Prompt optimization via TextGrad
- Agents
- Indexing to vector DB
- CLI app
- Fast/simple REST API server - compatible with OpenAI? 
- Revise examples debugging - not sure if it works as expected (what does it demonstrate?)
- Multiple tools with tool selection
- Parallel tool calls
- Export configuration to user folder / use external configuration
- Full control over generated prompt (access to Script object processing)
- Gemini OpenAI-style API

## Low priority

- Batch API support (Gemini, OpenAI, Anthropic)
- Gemini context caching
- Generate unstructured, then format to structured - to improve reasoning


# BACKLOG

## Addon: Modules

- Modules: Add module observability via events - currently no access to this info


## Refactorings

- More modular design - serialization, validation, transformation should be a configurable pipeline
- Rework Events so they have toArray() method, make __toString() use it
- Role - should be enum, not string?
- String >> Array >> Class - for example: prompts (they should be classes)
- Prompt - should be a class, not a string; prompt translates to Section/Messages; alt name: Instruction(s)

## Infrastructure

- Async mode
- Fix response caching
- Finish logging support
- Document how to inject custom HTTP client
- Better error messages
- Documentation for logging
- PSR-14 events - finish, demo how to plug custom dispatcher
- PSR-11 container - finish, demo how to plug custom container
- PSR-3 logger - finish, demo how to plug custom logger

## Other

- Sequences - validate only individual items, reject ONLY the invalid; allows progressive extraction in multiple stages
- Use LLM to generate Example based on the class - just render object to JSON + add schema as an explanation - this should give the model enough info to come up with something that makes sense
- Extract APIClient to a separate package?
- Foreign client support - OpenAI PHP, Amazon Bedrock SDK, etc. - via some kind of adapters?
- example of integration with Laravel/Livewire
- Read rate limits from API responses
- Rate limited API calls - wait for the limit to reset



# Partially done

- Better API for image / audio inputs
- Make system, prompt, script etc. available for configuration by user
- How to track API rate limits across multiple requests / parallel executions
- Make using DocBlocks optional - it may not always to be desired to pass this info to LLM
- Add super detailed tests of Module core functionality - esp. around input/output mappings
- Validators / Deserializers / Transformers - chain of objects, not a single object
- API Client: Clean up predefined models, prices, etc.




# Brain dump

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
- DSPy next steps: eval, optimize, compile
- Add more modules: XoT, RAG, ReAct, etc.
- RAG - how to handle multiple VDB providers?
- Test validation in modules - provide an example
- Parallel execution of modules (e.g. a la Laravel jobs?)
- Moderation endpoint support
- Git/GitHub integration module to allow easy automation
- Data mapping module(s) for easier data transformations
- How to handle dynamic module graph definition + visualization
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




# Other

## Gaps or issues in docs or code

## Design decisions to revisit

## Example ideas

Examples to demonstrate use cases.

## Test coverage

Catch up with the latest additions.



# Done

> NOTE: Move notes here

## Configuration

- Examples how to override default configuration

## Evals

- Simplify contracts - currently 5 (!) contracts for observations
- Add input, output, etc. tokens default metrics

