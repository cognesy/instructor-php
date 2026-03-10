<?php declare(strict_types=1);

use Cognesy\Events\Event;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated;
use Cognesy\Instructor\Events\Response\ResponseValidationFailed;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted;
use Cognesy\Logging\Factories\SymfonyLoggingFactory;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class SymfonyRegressionLogger extends AbstractLogger
{
    /** @var array<int, array{level: string, message: string, context: array}> */
    public array $records = [];

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}

function symfonyRegressionContainer(?Request $request = null): ContainerBuilder
{
    $container = new ContainerBuilder();
    $stack = new RequestStack();
    if ($request !== null) {
        $stack->push($request);
    }
    $container->set('request_stack', $stack);
    $container->setParameter('kernel.debug', true);

    return $container;
}

it('uses actual instructor event classes in the default setup templates', function () {
    $logger = new SymfonyRegressionLogger();
    $pipeline = SymfonyLoggingFactory::defaultSetup(symfonyRegressionContainer(), $logger);

    $pipeline(new StructuredOutputStarted(['responseClass' => 'DemoResponse', 'model' => 'gpt-4.1']));
    $pipeline(new ResponseValidationFailed(['responseClass' => 'DemoResponse', 'error' => 'invalid']));
    $pipeline(new HttpRequestSent(['method' => 'POST', 'url' => 'https://api.example.com']));

    expect(array_column($logger->records, 'message'))->toBe([
        'Starting DemoResponse generation with gpt-4.1',
        'Validation failed for DemoResponse: invalid',
        'HTTP POST https://api.example.com',
    ]);
});

it('keeps fallback request_id stable within one request when the header is absent', function () {
    $logger = new SymfonyRegressionLogger();
    $pipeline = SymfonyLoggingFactory::create(
        symfonyRegressionContainer(Request::create('https://example.test/demo')),
        $logger,
        ['level' => 'debug'],
    );

    $pipeline(new Event(['a' => 1]));
    $pipeline(new Event(['a' => 2]));

    expect($logger->records)->toHaveCount(2)
        ->and($logger->records[0]['context']['framework']['request_id'])
        ->toBe($logger->records[1]['context']['framework']['request_id']);
});

it('uses a fresh fallback request_id when the current request changes', function () {
    $logger = new SymfonyRegressionLogger();
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('https://example.test/first'));

    $container = new ContainerBuilder();
    $container->set('request_stack', $requestStack);

    $pipeline = SymfonyLoggingFactory::create($container, $logger, ['level' => 'debug']);

    $pipeline(new Event(['a' => 1]));

    $requestStack->pop();
    $requestStack->push(Request::create('https://example.test/second'));

    $pipeline(new Event(['a' => 2]));

    expect($logger->records)->toHaveCount(2)
        ->and($logger->records[0]['context']['framework']['request_id'])
        ->not->toBe($logger->records[1]['context']['framework']['request_id']);
});

it('excludes partial response events in the production setup using the actual event class', function () {
    $logger = new SymfonyRegressionLogger();
    $pipeline = SymfonyLoggingFactory::productionSetup(symfonyRegressionContainer(), $logger);

    $event = new PartialResponseGenerated(['step' => 1]);
    $event->logLevel = LogLevel::WARNING;
    $pipeline($event);

    expect($logger->records)->toBe([]);
});
