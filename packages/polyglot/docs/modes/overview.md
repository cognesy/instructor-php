---
title: Overview of Output Modes
description: 'Learn how to work with different output modes in Polyglot.'
---

One of Polyglot's key strengths is its ability to support various output formats from LLM providers. This flexibility allows you to structure responses in the format that best suits your application, whether you need plain text, structured JSON data, or function/tool calls. This chapter explores the different output modes supported by Polyglot and how to implement them effectively.

Polyglot's support for different output formats gives you the flexibility to work with LLM responses in the way that best suits your application's needs. Whether you need simple text, structured JSON, or interactive tool calls, you can configure the output format to match your requirements.

## Understanding Output Modes

Polyglot supports multiple output modes through the `Mode` enum:

```php
<?php
use Cognesy\Polyglot\LLM\Enums\OutputMode;

// Available modes
// Mode::Text       - Plain text output (default)
// Mode::Json       - JSON output
// Mode::JsonSchema - JSON output validated against a schema
// Mode::MdJson     - JSON wrapped in Markdown code blocks
// Mode::Tools      - Function/tool calling
```

Each mode influences:
1. How the request is formatted and sent to the provider
2. How the provider's response is processed
3. What extraction or validation is applied to the response


## Output Modes Overview

| Mode            | Description                                                                 | Best For                                      |
|-----------------|-----------------------------------------------------------------------------|-----------------------------------------------|
| `Mode::Text`     | Default mode, returns unstructured text                                     | Simple text generation                        |
| `Mode::Json`     | Returns structured JSON data                                                | Structured data processing                    |
| `Mode::JsonSchema` | Returns JSON data validated against a schema                               | Strictly typed data                           |
| `Mode::MdJson`   | Returns JSON wrapped in Markdown code blocks                                | Compatibility across providers                |
| `Mode::Tools`    | Returns function/tool calls                                                 | Function calling/external actions             |


### Choosing the Right Format

Consider these factors when selecting an output format:

1. **Complexity of the data**: More complex data structures benefit from JSON Schema
2. **Provider support**: Check which formats are natively supported by your provider
3. **Consistency requirements**: Stricter format requirements favor JSON Schema or Tools
4. **Application needs**: Consider how the data will be used in your application


### Improving Format Reliability

For better results:

1. **Be explicit in prompts**: Clearly describe the expected format
2. **Provide examples**: Show what good responses look like
3. **Use constraints**: Specify limits and requirements
4. **Test across providers**: Verify formats work with all providers you use
5. **Implement fallbacks**: Have backup strategies for format failures

