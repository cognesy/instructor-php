<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Cognesy\Config\Dsn;
use Cognesy\Config\EnvTemplate;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Tell\Contracts\CanResolveTellModel;
use Cognesy\Tell\Contracts\CanResolveTellSecrets;
use Cognesy\Tell\TellRequest;
use RuntimeException;

final readonly class PolyglotTellModelResolver implements CanResolveTellModel
{
    public function __construct(
        private TellPaths $paths,
        private CanResolveTellSecrets $secrets,
    ) {}

    public function resolve(TellRequest $request): LLMConfig
    {
        $config = match ($request->dsn) {
            '' => $this->fromPreset($request),
            default => LLMConfig::fromArray(Dsn::fromString($request->dsn)->toArray()),
        };
        $config = match ($request->model) {
            '' => $config,
            default => $config->withOverrides(['model' => $request->model]),
        };
        if ($request->reasoningEffort !== null) {
            TellReasoningSupport::assertSupported($config->driver, $config->model, $request->reasoningEffort);
            $config = $config->withOverrides([
                'options' => [
                    ...$config->options,
                    ...TellReasoningSupport::options($config->driver, $request->reasoningEffort),
                ],
            ]);
        }
        $this->assertCredentialAvailable($config, $request->connection);

        return $config;
    }

    private function fromPreset(TellRequest $request): LLMConfig
    {
        TellCredentialNames::forProvider($request->connection);

        return LLMConfig::fromPreset(
            preset: $request->connection,
            basePath: $this->connectionDirectory($request),
            template: new EnvTemplate($this->secrets),
        );
    }

    private function connectionDirectory(TellRequest $request): ?string
    {
        $project = rtrim($request->directory, '/\\').'/config/llm/presets';

        return match (true) {
            is_file($project.'/'.$request->connection.'.yaml') => $project,
            is_file($this->paths->connections.'/'.$request->connection.'.yaml') => $this->paths->connections,
            default => null,
        };
    }

    private function assertCredentialAvailable(LLMConfig $config, string $connection): void
    {
        $host = parse_url($config->apiUrl, PHP_URL_HOST);
        $local = $config->driver === 'ollama'
            || in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if ($config->apiKey !== '' || $local) {
            return;
        }
        $variable = TellCredentialNames::forProvider($connection);

        throw new RuntimeException(
            "Missing credential {$variable} for connection '{$connection}'. "
            ."Set it in the process environment, workspace .env, or with `tell auth set {$connection} --stdin`.",
        );
    }
}
