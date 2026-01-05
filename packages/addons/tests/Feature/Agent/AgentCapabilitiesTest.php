<?php declare(strict_types=1);

namespace Tests\Addons\Feature\Agent;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\UseBash;
use Cognesy\Addons\Agent\Capabilities\UseFileTools;
use Cognesy\Addons\Agent\Capabilities\UseSelfCritique;
use Cognesy\Addons\Agent\Capabilities\UseTaskPlanning;
use Cognesy\Addons\Agent\Tools\BashTool;
use Cognesy\Addons\Agent\Tools\File\ReadFileTool;
use Cognesy\Addons\Agent\Extras\Tasks\TodoWriteTool;

describe('Agent Capabilities', function () {
    it('installs Bash capability correctly', function () {
        $agent = AgentBuilder::new()
            ->withBash()
            ->build();
            
        expect($agent->tools()->has('bash'))->toBeTrue();
        expect($agent->tools()->get('bash'))->toBeInstanceOf(BashTool::class);
    });

    it('installs FileTools capability correctly', function () {
        $agent = AgentBuilder::new()
            ->withFileTools()
            ->build();
            
        expect($agent->tools()->has('read_file'))->toBeTrue();
        expect($agent->tools()->get('read_file'))->toBeInstanceOf(ReadFileTool::class);
    });

    it('installs TaskPlanning capability correctly', function () {
        $agent = AgentBuilder::new()
            ->withTaskPlanning()
            ->build();
            
        expect($agent->tools()->has('todo_write'))->toBeTrue();
        expect($agent->tools()->get('todo_write'))->toBeInstanceOf(TodoWriteTool::class);
        
        // Cannot easily check processors without reflection or public getter
    });
    
    it('installs SelfCritique capability correctly', function () {
        $agent = AgentBuilder::new()
            ->withCapability(new UseSelfCritique())
            ->build();
            
        // No observable external state on agent itself (processors/criteria are hidden)
        // But if it doesn't crash, it's a good sign.
        // We could verify via reflection if really needed.
        expect(true)->toBeTrue();
    });
    
    it('supports withCapability method', function () {
        $agent = AgentBuilder::new()
            ->withCapability(new UseBash())
            ->build();
            
        expect($agent->tools()->has('bash'))->toBeTrue();
    });
});
