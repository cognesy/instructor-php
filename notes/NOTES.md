# NOTES

## Gaps or issues in docs or code

## Design decisions to revisit

## Example ideas

Examples to demonstrate use cases.

## Other

### Test coverage

Catch up with the latest additions.

## Brain dump

- MessageSequence to better process multi-stage chat content
- Extract APIClient to a separate package?
- example of integration with Laravel/Livewire
- DSPy next steps: eval, optimize, compile
- Finish logging support
- Documentation for logging
- Add more modules: XoT, RAG, ReAct, etc.
- RAG - how to handle multiple VDB providers?
- Better error messages
- Finish module observability via events - currently no access to this info & only 2 events supported
- Test validation in modules - provide an example
- Parallel execution of modules (e.g. a la Laravel jobs?)
- How to track API rate limits across multiple requests / parallel executions
- Moderation endpoint support
- Make using DocBlocks optional - it may not always to be desired to pass this info to LLM
- Rework Events so they have toArray() method, make __toString() use it
- Document how to inject custom HTTP client
- Git/GitHub integration module to allow easy automation
- Data mapping module(s) for easier data transformations
- Add super detailed tests of Module core functionality - esp. around input/output mappings
- How to handle dynamic module graph definition + visualization
- Foreign client support - OpenAI PHP, Amazon Bedrock SDK, etc. - via some kind of adapters?
- JSON Schema management for input/output definitions

## To research

- Schema.org ld+json // Spatie https://github.com/spatie/schema-org // https://developers.google.com/search/docs/appearance/structured-data?hl=pl
- nette/schema https://github.com/nette/schema
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
