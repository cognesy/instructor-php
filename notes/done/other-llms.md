# Other LLMs

> Priority: must have

1) Via custom BASE_URI - via existing OpenAI client
2) Custom LLM classes.
   LLM class is the one that needs to handle all model / API specific stuff (e.g. function calling - vide: Claude's FC XML "API", streaming, modes, etc.).

We MUST support models which offer OpenAI compatible API and function calling (step 1 above).
Most likely we do already, but it should be tested and documented, so anybody can do it easily.

Things missing currently:
- Tests
- Documentation
- Examples

Next steps:
- Implement custom LLM class - for Claude?

## Solution

New client API code (based on Saloon) helped to build support for multiple APIs / LLMs.

Documentation and examples are added.
