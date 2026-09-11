<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Tests\Unit\Inference\Drivers\Bedrock;

use Cognesy\Polyglot\Inference\Drivers\Bedrock\BedrockConfiguration;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BedrockConfigurationTest extends TestCase
{
    public function test_validates_supported_regions(): void
    {
        $this->assertTrue(BedrockConfiguration::validateRegion('us-east-1'));
        $this->assertTrue(BedrockConfiguration::validateRegion('us-west-2'));
        $this->assertTrue(BedrockConfiguration::validateRegion('eu-west-1'));
        $this->assertFalse(BedrockConfiguration::validateRegion('invalid-region'));
        $this->assertFalse(BedrockConfiguration::validateRegion(''));
    }

    public function test_detects_limited_access_regions(): void
    {
        $this->assertTrue(BedrockConfiguration::isRegionLimited('ap-southeast-1')); // Singapore
        $this->assertTrue(BedrockConfiguration::isRegionLimited('eu-west-1')); // Ireland
        $this->assertFalse(BedrockConfiguration::isRegionLimited('us-east-1')); // Virginia
        $this->assertFalse(BedrockConfiguration::isRegionLimited('us-west-2')); // Oregon
    }

    public function test_builds_correct_endpoints(): void
    {
        $this->assertEquals(
            'https://bedrock-runtime.us-east-1.amazonaws.com/openai/v1',
            BedrockConfiguration::buildEndpoint('us-east-1')
        );
        $this->assertEquals(
            'https://bedrock-runtime.eu-west-3.amazonaws.com/openai/v1',
            BedrockConfiguration::buildEndpoint('eu-west-3')
        );
    }

    public function test_throws_exception_for_invalid_region(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported AWS region: invalid-region');
        BedrockConfiguration::buildEndpoint('invalid-region');
    }

    public function test_returns_supported_regions_list(): void
    {
        $regions = BedrockConfiguration::getSupportedRegions();
        $this->assertIsArray($regions);
        $this->assertArrayHasKey('us-east-1', $regions);
        $this->assertArrayHasKey('eu-west-3', $regions);
        $this->assertEquals('US East (N. Virginia)', $regions['us-east-1']['name']);
        $this->assertEquals('available', $regions['us-east-1']['status']);
    }

}
