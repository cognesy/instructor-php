<?php declare(strict_types=1);

namespace Tests\Addons\Feature\Capabilities;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\File\ReadFileTool;
use Cognesy\Addons\Agent\Capabilities\File\UseFileTools;

describe('File Capability', function () {
    it('installs FileTools capability correctly', function () {
        $agent = AgentBuilder::base()
            ->withCapability(new UseFileTools())
            ->build();
            
        expect($agent->tools()->has('read_file'))->toBeTrue();
        expect($agent->tools()->get('read_file'))->toBeInstanceOf(ReadFileTool::class);
    });
});
