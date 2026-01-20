<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Addons\AgentBuilder\Capabilities\Metadata\MetadataListTool;
use Cognesy\Utils\Json\EmptyObject;

describe('MetadataListTool schema', function () {
    it('uses an object for empty properties', function () {
        $tool = new MetadataListTool();
        $schema = $tool->toToolSchema();
        $properties = $schema['function']['parameters']['properties'] ?? null;

        expect($properties)->toBeInstanceOf(EmptyObject::class);
    });
});
