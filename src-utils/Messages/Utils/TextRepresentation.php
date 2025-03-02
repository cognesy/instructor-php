<?php

namespace Cognesy\Utils\Messages\Utils;

use BackedEnum;
use Closure;
use Cognesy\Utils\Json\Json;

/**
 * Tries to render arbitrary input to a text representation
 *
 * TODO: this should be moved to a chain-like component, so the way we handle inputs can be customized
 */
class TextRepresentation
{
    public static function fromAny(string|array|object $input) : string {
        return match(true) {
            is_string($input) => $input,
            is_array($input) => Json::encode($input),
            method_exists($input, 'toJson') => match(true) {
                is_string($input->toJson()) => $input->toJson(),
                default => Json::encode($input->toJson()),
            },
            method_exists($input, 'toArray') => Json::encode($input->toArray()),
            method_exists($input, 'toString') => $input->toString(),
            $input instanceof BackedEnum => $input->value,
            $input instanceof Closure => $input(),
            // ...how do we handle chat messages input?
            default => Json::encode($input), // fallback - just encode as JSON
        };
    }

    public static function fromParameter(mixed $value, string $key = null, array $parameters = []) : string {
        return match (true) {
            is_scalar($value) => $value,
            is_array($value) => Json::encode($value),
            is_callable($value) => $value($key, $parameters),
            is_object($value) && method_exists($value, 'toString') => $value->toString(),
            is_object($value) && method_exists($value, 'toJson') => $value->toJson(),
            is_object($value) && method_exists($value, 'toArray') => Json::encode($value->toArray()),
            is_object($value) && method_exists($value, 'toSchema') => Json::encode($value->toSchema()),
            is_object($value) && method_exists($value, 'toOutputSchema') => Json::encode($value->toOutputSchema()),
            is_object($value) && property_exists($value, 'value') => $value->value(),
            is_object($value) => Json::encode($value),
            default => $value,
        };
    }
}