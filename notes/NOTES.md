# CURRENT CONSIDERATIONS

## Better control over underlying prompts

- Unify TemplateUtil and Prompt + decide on how to proceed with ChatTemplate class
- Unify template conventions - <||> vs. {{}}, update docs
- Full control over generated prompt (access to Script object processing)

## High priority

- Polyglot/Instructor: Add (optional) automatic continuation if the finish reason is 'length' - this will allow to continue the response in the next call, if the model did not finish the response due to length limit
- Addon: ToolUse - apply context variables to message sequence (via ScriptParameters??)
- Polyglot: Batch completion API
- Polyglot: Async / parallel calls to multiple APIs
- Polyglot: OutputMode::Unrestricted - do not force the response to be a specific type, follow provided parameters

## Low priority

- Polyglot: Gemini context caching
- Polyglot: Citations API
- Polyglot: Predicted outputs API
- Addon: Generate unstructured, then format to structured - to improve reasoning
- Addon: MCP support as addon
- Add support for 'developer' role in messages

# SCRATCHPAD

- Make configurable per preset - merge per role, merge to string vs message sequence
- Move script structure to text template
- Add default template dialect to structured config file
- Settings and config paths - make easier to configure
- Clean up dependency on schema - make it very simple interface based API (object > json schema)
- Simplify the code of structure class, extract it to a separate package (instructor-adhoc)


# BACKLOG

## Refactorings

- More modular design - serialization, validation, transformation should be a configurable pipeline
- Rework Events so they have toArray() method, make __toString() use it
- Role - should be enum, not string?
- String >> Array >> Class - for example: prompts (they should be classes)
- Prompt - should be a class, not a string; prompt translates to Section/Messages?; alt name: Instruction(s)

## Addon: Evals

- Evals / eval framework
  * execution level correctness metric

## Addon: Agents

- ToolUse mechanism - finalize, document, add examples
- Agents - integrate ToolUse

## Addon: Modules

- Modules: Add module observability via events - currently no access to this info

## Addon: Prompt & schema optimization

- Schema abstraction layer - decouple names and descriptions from the model
- Prompt optimization via TextGrad

## Addons: Indexing & Search

- Indexing to vector DB
- pgvector? Scout + MeiliSearch / typesense?
- SEAL integration

## Infrastructure

- Async mode
- Fix response caching
- Finish logging support
- Documentation for logging
- Logging via PSR-3 + tail
- PSR-3 logger - finish, demo how to plug custom logger
- Document how to inject custom HTTP client
- Better error messages
- PSR-14 events - finish, demo how to plug custom dispatcher
- PSR-11 container - finish, demo how to plug custom container

## Other

- Revise examples debugging - not sure if it works as expected (what does it demonstrate?)
- Sequences - validate only individual items, reject ONLY the invalid; allows progressive extraction in multiple stages
- Use LLM to generate Example based on the class - just render object to JSON + add schema as an explanation - this should give the model enough info to come up with something that makes sense
- Extract APIClient to a separate package?
- Foreign client support - OpenAI PHP, Amazon Bedrock SDK, etc. - via some kind of adapters?
- example of integration with Laravel/Livewire
- Read rate limits from API responses
- Rate limited API calls - wait for the limit to reset
- Batch API support (Gemini, OpenAI, Anthropic)
- Fast/simple REST API server - compatible with OpenAI?
- CLI app

## Docs

- Better docs: add 'Output' section to each example, generate it and include in docs, so reader can see what they can expect
- Extract code examples from docs to be executable (and stored as separate scripts; front-matter or comments to mark sections to be displayed or hidden in the documentation rendered to the user), so we are sure they are working as the library code is changing
- How can we make the docs more modular, less rigid - so an individual file focuses on one specific topic or problem; how then we structure the docs then and turn them into logically connected flow, so the developer (persona) is not lost
- Persona based docs - identify main types of users and core scenarios/use-cases, so the examples and docs are relevant to them
- Examples from test cases
- Examples from last release (release notes, diff between tags)
- Throw-away docs - just for updates or situation specific; not included in the main documentation website

# PARTIALLY DONE

- Reasoning traces support in response objects (done: Deepseek, Anthropic)
- Better API for image / audio inputs
- Make system, prompt, script etc. available for configuration by user
- How to track API rate limits across multiple requests / parallel executions
- Make using DocBlocks optional - it may not always to be desired to pass this info to LLM
- Add super detailed tests of Module core functionality - esp. around input/output mappings
- Validators / Deserializers / Transformers - chain of objects, not a single object
- API Client: Clean up predefined models, prices, etc.




# BRAIN DUMP

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




# OTHER

## Gaps or issues in docs or code

## Design decisions to revisit

## Example ideas

Examples to demonstrate use cases.

## Test coverage

Catch up with the latest additions.



# DONE (TO BE CLEANED UP)

> NOTE: Move notes here

## Configuration

- Examples how to override default configuration

## Evals

- Simplify contracts - currently 5 (!) contracts for observations
- Add input, output, etc. tokens default metrics
