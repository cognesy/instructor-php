# Supported LLM Providers

Only tested providers with examples are listed here.

## OpenAI

OpenAI is the default provider that is called by Instructor unless user
configures different one.

Supported extraction modes:
 - Mode::Tools (recommended)
 - Mode::Json
 - Mode::MdJson


## Ollama

Supported extraction modes:
 - Mode::MdJson

Example:
- `./examples/LLMSupportOllama/run.php`


## OpenRouter

You have to use our client adapter to work around the problem in the response format
returned by OpenRouter for non-streamed requests.

Supported extraction modes:
 - Mode::MdJson

Example:
 - `./examples/LLMSupportOpenRouter/run.php`
