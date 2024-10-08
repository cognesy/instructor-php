<?php

namespace Cognesy\Instructor\Utils\Messages\Utils;

use BackedEnum;
use Closure;
use Cognesy\Instructor\Utils\Json\Json;

// TODO: this should be moved to a chain-like component, so the way we handle inputs can be customized
class Text
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
}