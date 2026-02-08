<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\AgentBuilder\Capabilities\Metadata\MetadataListTool;

describe('MetadataListTool schema', function () {
    it('generates valid schema for parameterless tool', function () {
        $tool = new MetadataListTool();
        $schema = $tool->toToolSchema();

        expect($schema)->toMatchArray([
            'type' => 'function',
            'function' => [
                'name' => 'list_metadata',
                'description' => $tool->description(),
                'parameters' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                ],
            ],
        ]);
    });
});
