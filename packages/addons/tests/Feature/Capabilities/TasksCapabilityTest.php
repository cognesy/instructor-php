<?php declare(strict_types=1);

namespace Tests\Addons\Feature\Capabilities;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\Tasks\TodoWriteTool;
use Cognesy\Addons\Agent\Capabilities\Tasks\UseTaskPlanning;

describe('Tasks Capability', function () {
    it('installs TaskPlanning capability correctly', function () {
        $agent = AgentBuilder::base()
            ->withCapability(new UseTaskPlanning())
            ->build();
            
        expect($agent->tools()->has('todo_write'))->toBeTrue();
        expect($agent->tools()->get('todo_write'))->toBeInstanceOf(TodoWriteTool::class);
    });
});
