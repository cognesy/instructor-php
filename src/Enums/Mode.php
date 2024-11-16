<?php

namespace Cognesy\Instructor\Enums;

enum Mode : string
{
    case Tools = 'tool_call';
    case Json = 'json';
    case JsonSchema = 'json_schema';
    case MdJson = 'md_json';
    case Text = 'text'; // unstructured text response

    public function is(array|Mode $mode) : bool {
        return match(true) {
            is_array($mode) => $this->isIn($mode),
            default => $this->value === $mode->value,
        };
    }

    public function isIn(array $modes) : bool {
        return in_array($this, $modes);
    }
}
