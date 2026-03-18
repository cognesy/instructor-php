<?php declare(strict_types=1);

use Cognesy\Config\Env;
use Cognesy\Telemetry\Adapters\Langfuse\LangfuseConfig;
use Cognesy\Telemetry\Adapters\Langfuse\LangfuseExporter;
use Cognesy\Telemetry\Adapters\Langfuse\LangfuseHttpTransport;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;

function exampleLangfuseHub(): Telemetry
{
    return new Telemetry(
        registry: new TraceRegistry(),
        exporter: exampleLangfuseExporter(),
    );
}

function exampleLangfuseExporter(): LangfuseExporter
{
    $baseUrl = exampleLangfuseRequiredEnv('LANGFUSE_BASE_URL');
    $publicKey = exampleLangfuseRequiredEnv('LANGFUSE_PUBLIC_KEY');
    $secretKey = exampleLangfuseRequiredEnv('LANGFUSE_SECRET_KEY');

    return new LangfuseExporter(
        transport: new LangfuseHttpTransport(new LangfuseConfig(
            baseUrl: $baseUrl,
            publicKey: $publicKey,
            secretKey: $secretKey,
        )),
    );
}

function exampleLangfuseRequiredEnv(string $name): string
{
    $value = trim((string) Env::get($name, ''));
    if ($value !== '') {
        return $value;
    }

    throw new RuntimeException("Set {$name} in .env to run this example.");
}
