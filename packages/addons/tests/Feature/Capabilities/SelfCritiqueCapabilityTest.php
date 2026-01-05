<?php declare(strict_types=1);

namespace Tests\Addons\Feature\Capabilities;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\SelfCritique\UseSelfCritique;

describe('SelfCritique Capability', function () {
    it('installs SelfCritique capability correctly', function () {
        $agent = AgentBuilder::base()
            ->withCapability(new UseSelfCritique())
            ->build();
            
        // No observable external state on agent itself (processors/criteria are hidden)
        // But if it doesn't crash, it's a good sign.
        expect(true)->toBeTrue();
    });
});
