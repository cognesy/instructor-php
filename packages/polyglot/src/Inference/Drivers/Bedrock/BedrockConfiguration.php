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

    public static function getSupportedRegions(): array
    {
        return self::SUPPORTED_REGIONS;
    }

}
