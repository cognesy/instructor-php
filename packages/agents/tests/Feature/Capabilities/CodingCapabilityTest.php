<?php

declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Capabilities;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Coding\UseCodingTools;
use Cognesy\Agents\Tests\Support\TestHelpers;

describe('Coding Capability', function () {
    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir().'/coding_capability_'.bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);
    });

    afterEach(function () {
        TestHelpers::recursiveDelete($this->tempDir);
    });

    it('provides the provider-familiar coding tool names without legacy aliases', function () {
        $agent = AgentBuilder::base()
            ->withCapability(new UseCodingTools($this->tempDir))
            ->build();

        expect($agent->tools()->names())->toBe(['read', 'bash', 'edit', 'write']);
    });

    it('executes all four aliased tools against one bounded workspace', function () {
        $agent = AgentBuilder::base()
            ->withCapability(new UseCodingTools($this->tempDir))
            ->build();
        $path = $this->tempDir.'/report.md';

        $write = $agent->tools()->get('write')($path, "# Draft\n");
        $read = $agent->tools()->get('read')($path);
        $edit = $agent->tools()->get('edit')($path, '# Draft', '# Verified');
        $bash = $agent->tools()->get('bash')('test -f report.md && printf present');

        expect($write)->toContain('Successfully wrote');
        expect($read)->toContain("1\t# Draft");
        expect($edit)->toContain('Successfully replaced 1 occurrence');
        expect($bash)->toBe('present');
        expect(file_get_contents($path))->toBe("# Verified\n");
    });
});
