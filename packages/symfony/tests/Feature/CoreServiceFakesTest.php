<?php

declare(strict_types=1);

use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Symfony\Tests\Support\EmbeddingsFakeRuntime;
use Cognesy\Instructor\Symfony\Tests\Support\InferenceFakeRuntime;
use Cognesy\Instructor\Symfony\Tests\Support\StructuredOutputFakeRuntime;
use Cognesy\Instructor\Symfony\Tests\Support\SymfonyCoreServiceOverrides;
use Cognesy\Instructor\Symfony\Tests\Support\SymfonyTestApp;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Inference;

it('overrides inference services through the public runtime contract', function (): void {
    $fake = InferenceFakeRuntime::fromResponses('fake-answer');

    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app) use ($fake): void {
            $result = $app->service(Inference::class)
                ->withMessages(Messages::fromString('Ping?'))
                ->get();

            expect($app->service(CanCreateInference::class))->toBe($fake)
                ->and($result)->toBe('fake-answer')
                ->and($fake->recorded())->toHaveCount(1)
                ->and($fake->recorded()[0]->messages()->toArray())->toHaveCount(1);
        },
        instructorConfig: fakeableInstructorConfig(),
        containerConfigurators: [
            SymfonyCoreServiceOverrides::inference($fake),
        ],
    );
});

it('overrides embeddings services through the public runtime contract', function (): void {
    $fake = EmbeddingsFakeRuntime::fromVectors([
        'hello world' => [0.5, 0.4, 0.3],
    ]);

    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app) use ($fake): void {
            $vector = $app->service(Embeddings::class)
                ->withInputs('hello world')
                ->first();

            expect($app->service(CanCreateEmbeddings::class))->toBe($fake)
                ->and($vector?->values())->toBe([0.5, 0.4, 0.3])
                ->and($fake->recorded())->toHaveCount(1)
                ->and($fake->recorded()[0]->inputs())->toBe(['hello world']);
        },
        instructorConfig: fakeableInstructorConfig(),
        containerConfigurators: [
            SymfonyCoreServiceOverrides::embeddings($fake),
        ],
    );
});

it('overrides structured output services through the public runtime contract', function (): void {
    $fake = StructuredOutputFakeRuntime::fromResponses([
        FakeContact::class => ['name' => 'Ada', 'email' => 'ada@example.com'],
    ]);

    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app) use ($fake): void {
            $contact = $app->service(StructuredOutput::class)
                ->withMessages('Extract contact details for Ada.')
                ->withResponseClass(FakeContact::class)
                ->get();

            expect($app->service(CanCreateStructuredOutput::class))->toBe($fake)
                ->and($contact)->toBeInstanceOf(FakeContact::class)
                ->and($contact->name)->toBe('Ada')
                ->and($contact->email)->toBe('ada@example.com')
                ->and($fake->recorded())->toHaveCount(1)
                ->and($fake->recorded()[0]->requestedSchema())->toBe(FakeContact::class);
        },
        instructorConfig: fakeableInstructorConfig(),
        containerConfigurators: [
            SymfonyCoreServiceOverrides::structuredOutput($fake),
        ],
    );
});

function fakeableInstructorConfig(): array
{
    return [
        'connections' => [
            'default' => 'openai',
            'items' => [
                'openai' => [
                    'driver' => 'openai',
                    'api_key' => 'test-key',
                    'model' => 'gpt-4o-mini',
                ],
            ],
        ],
        'embeddings' => [
            'default' => 'openai',
            'connections' => [
                'openai' => [
                    'driver' => 'openai',
                    'model' => 'text-embedding-3-small',
                ],
            ],
        ],
        'http' => [
            'driver' => 'symfony',
        ],
    ];
}

final readonly class FakeContact
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}
