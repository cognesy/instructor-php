<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Bedrock;

class BedrockConfiguration
{
    /**
     * Supported AWS regions for Amazon Bedrock (2024)
     * @var array<string, array{name: string, status: string}>
     */
    public const SUPPORTED_REGIONS = [
        'us-east-1' => ['name' => 'US East (N. Virginia)', 'status' => 'available'],
        'us-west-2' => ['name' => 'US West (Oregon)', 'status' => 'available'],
        'ap-northeast-1' => ['name' => 'Asia Pacific (Tokyo)', 'status' => 'available'],
        'ap-southeast-1' => ['name' => 'Asia Pacific (Singapore)', 'status' => 'limited'],
        'ap-southeast-2' => ['name' => 'Asia Pacific (Sydney)', 'status' => 'available'],
        'ap-south-1' => ['name' => 'Asia Pacific (Mumbai)', 'status' => 'available'],
        'eu-central-1' => ['name' => 'Europe (Frankfurt)', 'status' => 'available'],
        'eu-west-1' => ['name' => 'Europe (Ireland)', 'status' => 'limited'],
        'eu-west-3' => ['name' => 'Europe (Paris)', 'status' => 'available'],
        'eu-west-2' => ['name' => 'Europe (London)', 'status' => 'available'],
        'sa-east-1' => ['name' => 'South America (São Paulo)', 'status' => 'available'],
        'ca-central-1' => ['name' => 'Canada (Central)', 'status' => 'available'],
        'us-gov-west-1' => ['name' => 'AWS GovCloud (US-West)', 'status' => 'available'],
    ];

    /**
     * Common Bedrock model identifiers
     * @var array<string, array{family: string, description: string}>
     */
    public const COMMON_MODELS = [
        // Anthropic Claude models
        'anthropic.claude-3-5-sonnet-20241022-v2:0' => [
            'family' => 'anthropic',
            'description' => 'Claude 3.5 Sonnet (Latest)'
        ],
        'anthropic.claude-3-5-haiku-20241022-v1:0' => [
            'family' => 'anthropic',
            'description' => 'Claude 3.5 Haiku'
        ],
        'anthropic.claude-3-opus-20240229-v1:0' => [
            'family' => 'anthropic',
            'description' => 'Claude 3 Opus'
        ],
        'anthropic.claude-3-sonnet-20240229-v1:0' => [
            'family' => 'anthropic',
            'description' => 'Claude 3 Sonnet'
        ],
        'anthropic.claude-3-haiku-20240307-v1:0' => [
            'family' => 'anthropic',
            'description' => 'Claude 3 Haiku'
        ],

        // Meta Llama models
        'meta.llama3-1-405b-instruct-v1:0' => [
            'family' => 'meta',
            'description' => 'Llama 3.1 405B Instruct'
        ],
        'meta.llama3-1-70b-instruct-v1:0' => [
            'family' => 'meta',
            'description' => 'Llama 3.1 70B Instruct'
        ],
        'meta.llama3-1-8b-instruct-v1:0' => [
            'family' => 'meta',
            'description' => 'Llama 3.1 8B Instruct'
        ],

        // Amazon Titan models
        'amazon.titan-text-premier-v1:0' => [
            'family' => 'amazon',
            'description' => 'Titan Text Premier'
        ],
        'amazon.titan-text-express-v1' => [
            'family' => 'amazon',
            'description' => 'Titan Text Express'
        ],

        // Cohere models
        'cohere.command-r-plus-v1:0' => [
            'family' => 'cohere',
            'description' => 'Command R Plus'
        ],
        'cohere.command-r-v1:0' => [
            'family' => 'cohere',
            'description' => 'Command R'
        ],
    ];

    public static function validateRegion(string $region): bool
    {
        return array_key_exists($region, self::SUPPORTED_REGIONS);
    }

    public static function isRegionLimited(string $region): bool
    {
        return (self::SUPPORTED_REGIONS[$region]['status'] ?? '') === 'limited';
    }

    public static function buildEndpoint(string $region): string
    {
        if (!self::validateRegion($region)) {
            throw new \InvalidArgumentException("Unsupported AWS region: {$region}");
        }

        return "https://bedrock-runtime.{$region}.amazonaws.com/openai/v1";
    }

    public static function validateModel(string $modelId): bool
    {
        return true;
    }

    public static function getModelFamily(string $modelId): ?string
    {
        if (isset(self::COMMON_MODELS[$modelId])) {
            return self::COMMON_MODELS[$modelId]['family'];
        }

        // Extract family from model ID pattern (provider.model-name)
        if (preg_match('/^([a-z0-9-]+)\./', $modelId, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function getSupportedRegions(): array
    {
        return self::SUPPORTED_REGIONS;
    }

    public static function getCommonModels(): array
    {
        return self::COMMON_MODELS;
    }
}
