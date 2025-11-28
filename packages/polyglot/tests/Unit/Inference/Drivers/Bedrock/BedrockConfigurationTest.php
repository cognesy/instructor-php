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

    public function test_validates_model_ids(): void
    {
        // Valid Anthropic models
        $this->assertTrue(BedrockConfiguration::validateModel('anthropic.claude-3-5-sonnet-20241022-v2:0'));
        $this->assertTrue(BedrockConfiguration::validateModel('anthropic.claude-3-haiku-20240307-v1:0'));

        // Valid Meta models
        $this->assertTrue(BedrockConfiguration::validateModel('meta.llama3-1-405b-instruct-v1:0'));

        // Valid Amazon models
        $this->assertTrue(BedrockConfiguration::validateModel('amazon.titan-text-express-v1'));

        // Invalid formats
        $this->assertFalse(BedrockConfiguration::validateModel(''));
        $this->assertFalse(BedrockConfiguration::validateModel('invalid'));
        $this->assertFalse(BedrockConfiguration::validateModel('claude-3-sonnet'));
        $this->assertFalse(BedrockConfiguration::validateModel('anthropic/claude-3-sonnet'));
    }

    public function test_extracts_model_family(): void
    {
        $this->assertEquals('anthropic', BedrockConfiguration::getModelFamily('anthropic.claude-3-5-sonnet-20241022-v2:0'));
        $this->assertEquals('meta', BedrockConfiguration::getModelFamily('meta.llama3-1-405b-instruct-v1:0'));
        $this->assertEquals('amazon', BedrockConfiguration::getModelFamily('amazon.titan-text-express-v1'));
        $this->assertEquals('cohere', BedrockConfiguration::getModelFamily('cohere.command-r-plus-v1:0'));
        $this->assertNull(BedrockConfiguration::getModelFamily('invalid-model'));
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

    public function test_returns_common_models_list(): void
    {
        $models = BedrockConfiguration::getCommonModels();
        $this->assertIsArray($models);
        $this->assertArrayHasKey('anthropic.claude-3-5-sonnet-20241022-v2:0', $models);
        $this->assertEquals('anthropic', $models['anthropic.claude-3-5-sonnet-20241022-v2:0']['family']);
        $this->assertStringContainsString('Claude 3.5 Sonnet', $models['anthropic.claude-3-5-sonnet-20241022-v2:0']['description']);
    }
}