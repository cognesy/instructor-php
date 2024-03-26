# Supported LLM Providers

Only tested providers with examples are listed here.

## OpenAI

OpenAI is the default provider that is called by Instructor unless user
configures different one.

Supported extraction modes:
 - Mode::Tools (recommended)
 - Mode::Json
 - Mode::MdJson

Majority of examples use OpenAI provider.



## Azure OpenAI

Azure is an alternative provider of OpenAI models. You can consider using it as
a backup provider in case OpenAI is not available.

Supported extraction modes:
- Mode::Tools (recommended)
- Mode::Json
- Mode::MdJson

Example:
- `./examples/LLMSupportAzureOAI/run.php`



## Ollama

Supported extraction modes:

 - Mode::MdJson
 - Mode::Json (for selected models)

Example:
 - `./examples/LLMSupportOllama/run.php`



## Mistral API

Supported extraction modes:

 - Mode::MdJson
 - Mode::Json (for selected models)
 - Mode::Tools (for selected models)

Example:
 - `./examples/LLMSupportMistral/run.php`



## Anthropic

Supported extraction modes:

- Mode::MdJson
- Mode::Json (for selected models)

Mode::Tools is not supported yet.

Example:
 - `./examples/LLMSupportAnthropic/run.php`



## OpenRouter

You have to use our client adapter to work around the problem in the response format
returned by OpenRouter for non-streamed requests.

Supported extraction modes:

 - Mode::MdJson
 - Mode::Json (for selected models)
 - Mode::Tools (for selected models)

Example:
 - `./examples/LLMSupportOpenRouter/run.php`

