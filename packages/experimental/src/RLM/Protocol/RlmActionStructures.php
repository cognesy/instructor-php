<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Protocol;

use Cognesy\Dynamic\Structure;
use Cognesy\Schema\SchemaBuilder;

final class RlmActionStructures
{
    /**
     * Returns a structure schema for one of: plan | tool | write | final | await.
     */
    public static function decision(): Structure {
        $schema = SchemaBuilder::define('rlm_action', 'Recursive Language Model action.')
            ->option('type', ['plan', 'tool', 'write', 'final', 'await'], 'RLM action type.')
            ->array('subtasks', 'Subtasks for plan (optional).')
            ->string('name', 'Tool name (for type=tool).')
            ->array('args', 'Tool args (for type=tool).')
            ->string('var', 'Variable name (for type=write).')
            ->string('from', 'Handle string (for type=write/final).')
            ->string('reason', 'Reason (for type=await).')
            ->array('expected', 'Expected inputs (for type=await).')
            ->schema();

        return Structure::fromSchema($schema);
    }
}
