<?php declare(strict_types=1);

namespace Tests\Addons\Feature\Capabilities;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\Bash\BashTool;
use Cognesy\Addons\Agent\Capabilities\Bash\UseBash;

describe('Bash Capability', function () {
    it('installs Bash capability correctly', function () {
        $agent = AgentBuilder::base()
            ->withCapability(new UseBash())
            ->build();
            
        expect($agent->tools()->has('bash'))->toBeTrue();
        expect($agent->tools()->get('bash'))->toBeInstanceOf(BashTool::class);
    });
});
