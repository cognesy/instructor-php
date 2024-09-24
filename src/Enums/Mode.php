<?php

namespace Cognesy\Instructor\Enums;

enum Mode : string
{
    case Tools = 'tool_call';
    case Json = 'json';
    case JsonSchema = 'json_schema';
    case MdJson = 'markdown_json';
    case Text = 'text'; // unstructured text response

    public function is(array|Mode $mode) : bool {
        if (is_array($mode)) {
            return in_array($this, $mode);
        }
        return $this->value === $mode->value;
    }
}
