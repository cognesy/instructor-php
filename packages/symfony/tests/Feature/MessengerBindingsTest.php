<?php

declare(strict_types=1);

use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Symfony\Delivery\Messenger\ExecuteAgentCtrlPromptMessage;
use Cognesy\Instructor\Symfony\Delivery\Messenger\ExecuteAgentCtrlPromptMessageHandler;
use Cognesy\Instructor\Symfony\Delivery\Messenger\ExecuteNativeAgentPromptMessage;
use Cognesy\Instructor\Symfony\Delivery\Messenger\ExecuteNativeAgentPromptMessageHandler;
use Cognesy\Instructor\Symfony\Delivery\Messenger\RuntimeObservationMessage;
use Cognesy\Instructor\Symfony\DependencyInjection\InstructorSymfonyExtension;
use Cognesy\Instructor\Symfony\Tests\Support\ScriptedAgentLoopFactory;
use Cognesy\Instructor\Symfony\Tests\Support\SymfonyNativeAgentOverrides;
use Cognesy\Instructor\Symfony\Tests\Support\SymfonyTestApp;
use Cognesy\Instructor\Symfony\Tests\Support\SymfonyTestServiceRegistry;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

it('registers messenger handlers and the opt-in observation bridge', function (): void {
    $container = new ContainerBuilder;
    $extension = new InstructorSymfonyExtension;

    $extension->load([[
        'connections' => [
            'openai' => [
                'driver' => 'openai',
                'api_key' => 'test-key',
                'model' => 'gpt-4o-mini',
            ],
        ],
        'delivery' => [
            'messenger' => [
                'enabled' => true,
                'observe_events' => [MessengerObservedRuntimeEvent::class],
            ],
        ],
    ]], $container);

    $agentCtrlHandler = $container->getDefinition(ExecuteAgentCtrlPromptMessageHandler::class);
    $nativeHandler = $container->getDefinition(ExecuteNativeAgentPromptMessageHandler::class);

    expect($agentCtrlHandler->getTag('messenger.message_handler')[0]['handles'] ?? null)
        ->toBe(ExecuteAgentCtrlPromptMessage::class)
        ->and($nativeHandler->getTag('messenger.message_handler')[0]['handles'] ?? null)
        ->toBe(ExecuteNativeAgentPromptMessage::class)
        ->and($container->hasDefinition('instructor.delivery.messenger.observation_bridge'))
        ->toBeTrue();
});

it('dispatches configured runtime observations onto the messenger bus', function (): void {
    $bus = new RecordingMessageBus();
    $serviceId = SymfonyTestServiceRegistry::put($bus);

    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app) use ($bus): void {
            $events = $app->service(CanHandleEvents::class);
            $events->dispatch(new MessengerIgnoredRuntimeEvent('skip'));
            $events->dispatch(new MessengerObservedRuntimeEvent('forward'));

            expect($bus->messages)->toHaveCount(1)
                ->and($bus->messages[0])->toBeInstanceOf(RuntimeObservationMessage::class)
                ->and($bus->messages[0]->eventType)->toBe(MessengerObservedRuntimeEvent::class)
                ->and($bus->messages[0]->event)->toBeInstanceOf(MessengerObservedRuntimeEvent::class);
        },
        instructorConfig: [
            'connections' => [
                'openai' => [
                    'driver' => 'openai',
                    'api_key' => 'test-key',
                    'model' => 'gpt-4o-mini',
                ],
            ],
            'delivery' => [
                'messenger' => [
                    'enabled' => true,
                    'bus_service' => 'test.message_bus',
                    'observe_events' => [MessengerObservedRuntimeEvent::class],
                ],
            ],
        ],
        containerConfigurators: [
            static function (ContainerBuilder $container) use ($serviceId): void {
                $container->setDefinition('test.message_bus', (new Definition(RecordingMessageBus::class))
                    ->setFactory([SymfonyTestServiceRegistry::class, 'get'])
                    ->setArguments([$serviceId])
                    ->setPublic(true));
            },
        ],
    );
});

it('executes native agent prompts through the messenger handler', function (): void {
    $loopFactory = ScriptedAgentLoopFactory::fromResponses('queued-response');

    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            $handler = $app->service(ExecuteNativeAgentPromptMessageHandler::class);
            $session = $handler(new ExecuteNativeAgentPromptMessage(
                definition: 'support-agent',
                prompt: 'Queue this prompt',
            ));

            $messages = $session->state()->messages()->toArray();

            expect($session->definition()->name)->toBe('support-agent')
                ->and($session->version())->toBe(2)
                ->and($messages)->toHaveCount(2)
                ->and($messages[0]['role'] ?? null)->toBe('user')
                ->and($messages[1]['role'] ?? null)->toBe('assistant');
        },
        instructorConfig: [
            'connections' => [
                'openai' => [
                    'driver' => 'openai',
                    'api_key' => 'test-key',
                    'model' => 'gpt-4o-mini',
                ],
            ],
        ],
        containerConfigurators: [
            SymfonyNativeAgentOverrides::definition(new AgentDefinition(
                name: 'support-agent',
                description: 'Support queue agent',
                systemPrompt: 'Be helpful',
            )),
            SymfonyNativeAgentOverrides::loopFactory($loopFactory),
        ],
    );
});

final readonly class MessengerObservedRuntimeEvent
{
    public function __construct(
        public string $message,
    ) {}
}

final readonly class MessengerIgnoredRuntimeEvent
{
    public function __construct(
        public string $message,
    ) {}
}

final class RecordingMessageBus implements MessageBusInterface
{
    /** @var list<object> */
    public array $messages = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->messages[] = $message;

        return Envelope::wrap($message, $stamps);
    }
}
