<?php

declare(strict_types=1);

use Cognesy\Agents\Session\Contracts\CanManageAgentSessions;
use Cognesy\Agents\Session\Contracts\CanStoreSessions;
use Cognesy\Agents\Session\Exceptions\SessionConflictException;
use Cognesy\Agents\Session\SessionRepository;
use Cognesy\Agents\Session\Store\FileSessionStore;
use Cognesy\Agents\Session\Store\InMemorySessionStore;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Instructor\Symfony\Delivery\Messenger\ExecuteNativeAgentPromptMessage;
use Cognesy\Instructor\Symfony\Delivery\Messenger\ExecuteNativeAgentPromptMessageHandler;
use Cognesy\Instructor\Symfony\Tests\Support\ScriptedAgentLoopFactory;
use Cognesy\Instructor\Symfony\Tests\Support\SymfonyNativeAgentOverrides;
use Cognesy\Instructor\Symfony\Tests\Support\SymfonyTestApp;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

it('keeps the in-memory session store by default', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            $store = $app->service(CanStoreSessions::class);

            expect($store)->toBeInstanceOf(InMemorySessionStore::class);
        },
        instructorConfig: sessionTestConfig(),
    );
});

it('switches the session store to the configured file adapter', function (): void {
    $directory = sessionPersistenceDirectory('file-store');

    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app) use ($directory): void {
            $store = $app->service(CanStoreSessions::class);
            $sessions = $app->service(CanManageAgentSessions::class);
            $session = $sessions->create(new AgentDefinition(
                name: 'persisted-agent',
                description: 'File-backed agent',
                systemPrompt: 'Be explicit',
            ));

            expect($store)->toBeInstanceOf(FileSessionStore::class)
                ->and(is_dir($directory))->toBeTrue()
                ->and(file_exists($directory.'/'.$session->sessionId()->value.'.json'))->toBeTrue();
        },
        instructorConfig: sessionTestConfig([
            'sessions' => [
                'store' => 'file',
                'file' => [
                    'directory' => $directory,
                ],
            ],
        ]),
    );
});

it('persists and resumes native-agent sessions across boots with the file adapter', function (): void {
    $directory = sessionPersistenceDirectory('resume-flow');
    $loopFactory = ScriptedAgentLoopFactory::fromResponses('persisted-response');

    $sessionId = SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): string {
            $handler = $app->service(ExecuteNativeAgentPromptMessageHandler::class);
            $session = $handler(new ExecuteNativeAgentPromptMessage(
                definition: 'persisted-agent',
                prompt: 'First prompt',
            ));

            expect($session->version())->toBe(2);

            return $session->sessionId()->value;
        },
        instructorConfig: sessionTestConfig([
            'sessions' => [
                'store' => 'file',
                'file' => [
                    'directory' => $directory,
                ],
            ],
        ]),
        containerConfigurators: persistedSessionContainerConfigurators($loopFactory),
    );

    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app) use ($sessionId): void {
            $handler = $app->service(ExecuteNativeAgentPromptMessageHandler::class);
            $session = $handler(new ExecuteNativeAgentPromptMessage(
                definition: 'persisted-agent',
                prompt: 'Second prompt',
                sessionId: $sessionId,
            ));

            $messages = $session->state()->messages()->toArray();

            expect($session->version())->toBe(3)
                ->and($messages)->toHaveCount(4)
                ->and($messages[0]['content'] ?? null)->toBe('First prompt')
                ->and($messages[2]['content'] ?? null)->toBe('Second prompt');
        },
        instructorConfig: sessionTestConfig([
            'sessions' => [
                'store' => 'file',
                'file' => [
                    'directory' => $directory,
                ],
            ],
        ]),
        containerConfigurators: persistedSessionContainerConfigurators($loopFactory),
    );
});

it('surfaces optimistic concurrency conflicts for the file adapter', function (): void {
    $directory = sessionPersistenceDirectory('conflicts');

    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            $repository = $app->service(SessionRepository::class);
            $sessions = $app->service(CanManageAgentSessions::class);
            $created = $sessions->create(new AgentDefinition(
                name: 'conflict-agent',
                description: 'Conflict test agent',
                systemPrompt: 'Be explicit',
            ));

            $first = $repository->load($created->sessionId());
            $second = $repository->load($created->sessionId());

            $repository->save($first->withState($first->state()->withMetadata('write', 'first')));

            expect(static fn () => $repository->save(
                $second->withState($second->state()->withMetadata('write', 'second')),
            ))->toThrow(SessionConflictException::class, 'Version conflict');
        },
        instructorConfig: sessionTestConfig([
            'sessions' => [
                'store' => 'file',
                'file' => [
                    'directory' => $directory,
                ],
            ],
        ]),
    );
});

it('rejects unsupported session store drivers', function (): void {
    $load = static function (): void {
        SymfonyTestApp::boot(instructorConfig: sessionTestConfig([
            'sessions' => [
                'store' => 'database',
            ],
        ]));
    };

    expect($load)->toThrow(InvalidConfigurationException::class);
});

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function sessionTestConfig(array $overrides = []): array
{
    return array_replace_recursive([
        'connections' => [
            'openai' => [
                'driver' => 'openai',
                'api_key' => 'test-key',
                'model' => 'gpt-4o-mini',
            ],
        ],
    ], $overrides);
}

function sessionPersistenceDirectory(string $suffix): string
{
    return sys_get_temp_dir().'/instructor-symfony-session-tests/'.$suffix.'-'.bin2hex(random_bytes(6));
}

/**
 * @return list<Closure(\Symfony\Component\DependencyInjection\ContainerBuilder):void>
 */
function persistedSessionContainerConfigurators(ScriptedAgentLoopFactory $loopFactory): array
{
    return [
        SymfonyNativeAgentOverrides::definition(new AgentDefinition(
            name: 'persisted-agent',
            description: 'Persisted agent',
            systemPrompt: 'Be explicit',
        )),
        SymfonyNativeAgentOverrides::loopFactory($loopFactory),
    ];
}
