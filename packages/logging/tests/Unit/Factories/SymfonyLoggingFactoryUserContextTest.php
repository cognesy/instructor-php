<?php declare(strict_types=1);

namespace Cognesy\Logging\Tests\Unit\Factories\SymfonyLoggingFactoryUserContextTest;

use Cognesy\Events\Event;
use Cognesy\Logging\Factories\SymfonyLoggingFactory;
use Psr\Log\AbstractLogger;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class InMemoryLogger extends AbstractLogger
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

final readonly class TestTokenStorage
{
    public function __construct(private mixed $token) {}

    public function getToken(): mixed
    {
        return $this->token;
    }
}

final readonly class TestToken
{
    public function __construct(
        private mixed $user,
        private array $roles = ['ROLE_USER'],
    ) {}

    public function getUser(): mixed
    {
        return $this->user;
    }

    public function getRoleNames(): array
    {
        return $this->roles;
    }
}

final readonly class TestUser
{
    public function __construct(
        private int $id,
        private string $identifier,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }
}

function buildAndRunSymfonyPipelineWithUser(mixed $user): array
{
    $container = new ContainerBuilder();
    $container->set('security.token_storage', new TestTokenStorage(new TestToken($user, ['ROLE_USER'])));

    $logger = new InMemoryLogger();
    $pipeline = SymfonyLoggingFactory::create($container, $logger, [
        'channel' => 'tests',
        'level' => 'debug',
    ]);

    $pipeline(new Event(['scope' => 'test']));

    return $logger->records;
}

it('enriches user context for object users with identifier methods', function () {
    $records = buildAndRunSymfonyPipelineWithUser(new TestUser(7, 'john@example.com'));

    expect($records)->toHaveCount(1);
    expect($records[0]['context']['user'])->toBe([
        'user_id' => 7,
        'username' => 'john@example.com',
        'user_type' => TestUser::class,
        'roles' => ['ROLE_USER'],
    ]);
});

it('does not crash for string principals and preserves minimal user context', function () {
    $records = buildAndRunSymfonyPipelineWithUser('anon');

    expect($records)->toHaveCount(1);
    expect($records[0]['context']['user'])->toBe([
        'user_id' => null,
        'username' => 'anon',
        'user_type' => 'string',
        'roles' => ['ROLE_USER'],
    ]);
});

it('returns empty user context for unsupported principal values', function () {
    $records = buildAndRunSymfonyPipelineWithUser(['unexpected']);

    expect($records)->toHaveCount(1);
    expect($records[0]['context']['user'])->toBe([]);
});

it('returns empty user context when token user is null', function () {
    $records = buildAndRunSymfonyPipelineWithUser(null);

    expect($records)->toHaveCount(1);
    expect($records[0]['context']['user'])->toBe([]);
});
