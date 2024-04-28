# Supported LLM Providers

Only tested providers with examples are listed here.



## Anthropic

Supported extraction modes:

- Mode::Tools
- Mode::Json (recommended)
- Mode::MdJson

Example:
- `./examples/05_APISupport/LLMSupportAnthropic/run.php`



## Anyscale

Supported extraction modes:

- Mode::MdJson
- Mode::Json (for selected models, recommended)
- Mode::Tools (for selected models)

Example:
- `./examples/05_APISupport/LLMSupportAnyscale/run.php`




## Azure OpenAI

Azure is an alternative provider of OpenAI models. You can consider using it as
a backup provider in case OpenAI is not available.

Supported extraction modes:

- Mode::Tools (recommended)
- Mode::Json
- Mode::MdJson

Example:
- `./examples/05_APISupport/LLMSupportAzureOAI/run.php`



## FireworksAI

Supported extraction modes:

- Mode::MdJson
- Mode::Json (for selected models)
- Mode::Tools (for selected models)

Example:
- `./examples/05_APISupport/LLMSupportFireworksAI/run.php`




## Groq

Supported extraction modes:

- Mode::MdJson
- Mode::Json (recommended)
- Mode::Tools (experimental - not stable)

Example:
- `./examples/05_APISupport/LLMSupportGroq/run.php`




## Mistral API

Supported extraction modes:

- Mode::MdJson
- Mode::Json (for selected models)
- Mode::Tools (for selected models)

Example:
- `./examples/05_APISupport/LLMSupportMistral/run.php`



## Ollama

Supported extraction modes:

- Mode::MdJson
- Mode::Json (for selected models, recommended)
- Mode::Tools (for selected models)

Example:
- `./examples/05_APISupport/LLMSupportOllama/run.php`



## OpenAI

OpenAI is the default provider that is called by Instructor unless user
configures different one.

Supported extraction modes:
 - Mode::Tools (recommended)
 - Mode::Json
 - Mode::MdJson

Majority of examples use OpenAI provider.

- `./examples/05_APISupport/LLMSupportOpenAI/run.php`



## OpenRouter

You have to use our client adapter to work around the problem in the response format
returned by OpenRouter for non-streamed requests.

Supported extraction modes:

 - Mode::MdJson
 - Mode::Json (for selected models)
 - Mode::Tools (for selected models)

Example:
 - `./examples/05_APISupport/LLMSupportOpenRouter/run.php`



## TogetherAI

Supported extraction modes:

- Mode::MdJson
- Mode::Json (for selected models)
- Mode::Tools (for selected models)

Example:
- `./examples/05_APISupport/LLMSupportTogetherAI/run.php`
