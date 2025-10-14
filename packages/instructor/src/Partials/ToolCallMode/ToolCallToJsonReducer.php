<?php declare(strict_types=1);

namespace Cognesy\Instructor\Partials\ToolCallMode;

use Cognesy\Instructor\Partials\Data\PartialContext;
use Cognesy\Instructor\Streaming\PartialGen\PartialJson;
use Cognesy\Stream\Contracts\Reducer;

class ToolCallToJsonReducer implements Reducer
{
    public function __construct(
        private Reducer $inner,
    ) {}

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        assert($reducible instanceof PartialContext);

        // If no tool call update, pass through
        if ($reducible->toolCallUpdate === null) {
            return $this->inner->step($accumulator, $reducible);
        }

        // Convert normalized args to PartialJson
        $normalized = $reducible->toolCallUpdate->normalizedArgs;
        if ($normalized === '') {
            return $accumulator; // Skip empty
        }

        $json = new PartialJson(
            raw: $reducible->toolCallUpdate->rawArgs,
            normalized: $normalized,
        );

        return $this->inner->step($accumulator, $reducible->withJson($json));
    }

    #[\Override]
    public function init(): mixed {
        return $this->inner->init();
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }
}