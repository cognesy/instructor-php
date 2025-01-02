<?php

namespace Cognesy\Instructor\Enums;

/**
 * Mode is an enumeration representing different modes of responses or processing types.
 * Each case corresponds to a specific mode with an associated string value.
 *
 * Enum Cases:
 * - Tools: Represents a "tool_call" mode.
 * - Json: Represents a "json" mode.
 * - JsonSchema: Represents a "json_schema" mode.
 * - MdJson: Represents a "md_json" mode.
 * - Text: Represents an unstructured "text" response mode.
 */
enum Mode : string
{
    case Tools = 'tool_call';
    case Json = 'json';
    case JsonSchema = 'json_schema';
    case MdJson = 'md_json';
    case Text = 'text'; // unstructured text response

    /**
     * Checks whether the given mode matches the current mode.
     *
     * @param array|Mode $mode The mode to compare, can be an array of modes or a single Mode instance.
     * @return bool Returns true if the given mode matches the current mode, false otherwise.
     */
    public function is(array|Mode $mode) : bool {
        return match(true) {
            is_array($mode) => $this->isIn($mode),
            default => $this->value === $mode->value,
        };
    }

    /**
     * Determines whether the current instance is present in the given array of modes.
     *
     * @param array $modes An array of modes to check against.
     * @return bool Returns true if the current instance is found in the array of modes, false otherwise.
     */
    public function isIn(array $modes) : bool {
        return in_array($this, $modes);
    }
}
