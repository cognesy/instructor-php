<?php

namespace Cognesy\Instructor\Enums;

enum Mode : string
{
    case Tools = 'tool_call';
    case Json = 'json';
    case MdJson = 'markdown_json';
    case Text = 'text'; // unstructured text response

    public function is(Mode $mode) : bool {
        return $this->value === $mode->value;
    }
}
