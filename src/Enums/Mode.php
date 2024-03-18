<?php

namespace Cognesy\Instructor\Enums;

enum Mode : string
{
    case Tools = 'tool_call';
    case Json = 'json_mode';
    // modes below are not implemented yet
    case ParallelTools = 'parallel_tool_call';
    case MistralTools = 'mistral_tools';
    case MdJson = 'markdown_json_mode';
    case JsonSchema = 'json_schema_mode';
    case Yaml = 'yaml_mode';
    case Functions = 'function_call'; // deprecated by OpenAI, low priority (but probably easy)
}
