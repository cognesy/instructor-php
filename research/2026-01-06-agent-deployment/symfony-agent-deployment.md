# Deploying Instructor Agents in Symfony Applications

This document covers practical patterns for embedding AI agents in Symfony applications using Messenger, Doctrine, and Mercure for real-time updates.

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Messenger Integration](#messenger-integration)
3. [Entity Design & Doctrine](#entity-design--doctrine)
4. [Agent Execution Handler](#agent-execution-handler)
5. [Status Communication & Mercure](#status-communication--mercure)
6. [Lifecycle Management](#lifecycle-management)
7. [Worker Configuration](#worker-configuration)
8. [Scaling & Parallel Execution](#scaling--parallel-execution)
9. [Event-Driven Awakening](#event-driven-awakening)
10. [Long-Running Jobs](#long-running-jobs)
11. [Complete Implementation Example](#complete-implementation-example)

---

## Architecture Overview

### Core Components

```
┌─────────────────────────────────────────────────────────────────┐
│                     Symfony Application                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────────┐   │
│  │  Controller  │───▶│ AgentService │───▶│   MessageBus     │   │
│  └──────────────┘    └──────────────┘    └────────┬─────────┘   │
│                                                    │             │
│  ┌──────────────┐    ┌──────────────┐             │             │
│  │   Admin UI   │◀───│   Doctrine   │◀────────────┤             │
│  └──────────────┘    │  (entities)  │             │             │
│         ▲            └──────────────┘             ▼             │
│         │                                ┌──────────────────┐   │
│  ┌──────┴───────┐                        │  Messenger       │   │
│  │    Mercure   │◀───────────────────────┤  Workers         │   │
│  │     Hub      │                        │  (supervisor)    │   │
│  └──────────────┘                        │                  │   │
│                                          │  ┌────────────┐  │   │
│                                          │  │   Agent    │  │   │
│                                          │  │  Handler   │  │   │
│                                          │  └────────────┘  │   │
│                                          └──────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

### Technology Stack

| Component | Symfony Ecosystem |
|-----------|-------------------|
| Async Jobs | Symfony Messenger |
| Persistence | Doctrine ORM |
| Real-time | Mercure |
| Workers | symfony/messenger:consume |
| Rate Limiting | symfony/rate-limiter |
| Logging | Monolog |
| Metrics | prometheus/client_php |

---

## Messenger Integration

### Message Classes

```php
<?php

namespace App\Message;

/**
 * Message to start agent execution.
 */
final readonly class ExecuteAgent
{
    public function __construct(
        public string $executionId,
    ) {}
}

/**
 * Message to continue agent from checkpoint.
 */
final readonly class ContinueAgentExecution
{
    public function __construct(
        public string $executionId,
        public int $fromStep = 0,
    ) {}
}

/**
 * Message for chunked long-running execution.
 */
final readonly class ExecuteAgentChunk
{
    public function __construct(
        public string $executionId,
        public int $chunkNumber = 0,
        public int $stepsPerChunk = 10,
    ) {}
}

/**
 * Message for event-triggered execution.
 */
final readonly class TriggerAgentEvent
{
    public function __construct(
        public string $eventType,
        public array $payload = [],
        public ?int $userId = null,
    ) {}
}
```

### Messenger Configuration

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        # Serialization for message persistence
        serializer:
            default_serializer: messenger.transport.symfony_serializer
            symfony_serializer:
                format: json
                context: {}

        # Failure handling
        failure_transport: failed

        transports:
            # Default agent queue
            agents:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    queue_name: agents
                retry_strategy:
                    max_retries: 0  # Agents handle retries internally
                    delay: 1000

            # Long-running agent queue
            agents_long:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    queue_name: agents_long
                retry_strategy:
                    max_retries: 0
                    delay: 1000

            # Priority queue for premium users
            agents_priority:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    queue_name: agents_priority
                retry_strategy:
                    max_retries: 0
                    delay: 1000

            # Failed message storage
            failed:
                dsn: 'doctrine://default?queue_name=failed'

            # Async events (non-blocking)
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    queue_name: async

        routing:
            App\Message\ExecuteAgent: agents
            App\Message\ContinueAgentExecution: agents
            App\Message\ExecuteAgentChunk: agents_long
            App\Message\TriggerAgentEvent: async
            App\Message\AgentStatusChanged: async

        # Bus configuration
        default_bus: command.bus

        buses:
            command.bus:
                middleware:
                    - doctrine_ping_connection
                    - doctrine_close_connection
```

### Transport DSN Examples

```bash
# .env

# Redis (recommended for production)
MESSENGER_TRANSPORT_DSN=redis://localhost:6379/messages

# RabbitMQ
MESSENGER_TRANSPORT_DSN=amqp://guest:guest@localhost:5672/%2f/messages

# Doctrine (simple setup)
MESSENGER_TRANSPORT_DSN=doctrine://default

# Amazon SQS
MESSENGER_TRANSPORT_DSN=sqs://access_key:secret_key@default/messages?region=eu-west-1
```

---

## Entity Design & Doctrine

### AgentExecution Entity

```php
<?php

namespace App\Entity;

use App\Repository\AgentExecutionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AgentExecutionRepository::class)]
#[ORM\Table(name: 'agent_executions')]
#[ORM\Index(columns: ['user_id', 'status'], name: 'idx_user_status')]
#[ORM\Index(columns: ['status', 'created_at'], name: 'idx_status_created')]
#[ORM\HasLifecycleCallbacks]
class AgentExecution
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_AWAITING_EVENT = 'awaiting_event';
    public const STATUS_AWAITING_INPUT = 'awaiting_input';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 64)]
    private string $agentType;

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $input = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $output = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $stateSnapshot = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $stepCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $tokenUsage = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $pausedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, AgentLog> */
    #[ORM\OneToMany(mappedBy: 'execution', targetEntity: AgentLog::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $logs;

    /** @var Collection<int, AgentSignal> */
    #[ORM\OneToMany(mappedBy: 'execution', targetEntity: AgentSignal::class, cascade: ['persist', 'remove'])]
    private Collection $signals;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->logs = new ArrayCollection();
        $this->signals = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Getters and setters...

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getAgentType(): string
    {
        return $this->agentType;
    }

    public function setAgentType(string $agentType): self
    {
        $this->agentType = $agentType;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ], true);
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function canBePaused(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function canBeResumed(): bool
    {
        return in_array($this->status, [
            self::STATUS_PAUSED,
            self::STATUS_AWAITING_INPUT,
        ], true);
    }

    public function getInput(): ?array
    {
        return $this->input;
    }

    public function setInput(?array $input): self
    {
        $this->input = $input;
        return $this;
    }

    public function getOutput(): ?array
    {
        return $this->output;
    }

    public function setOutput(?array $output): self
    {
        $this->output = $output;
        return $this;
    }

    public function getStateSnapshot(): ?array
    {
        return $this->stateSnapshot;
    }

    public function setStateSnapshot(?array $stateSnapshot): self
    {
        $this->stateSnapshot = $stateSnapshot;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function setMetadataValue(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    public function getStepCount(): int
    {
        return $this->stepCount;
    }

    public function setStepCount(int $stepCount): self
    {
        $this->stepCount = $stepCount;
        return $this;
    }

    public function incrementStepCount(): self
    {
        $this->stepCount++;
        return $this;
    }

    public function getTokenUsage(): int
    {
        return $this->tokenUsage;
    }

    public function setTokenUsage(int $tokenUsage): self
    {
        $this->tokenUsage = $tokenUsage;
        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): self
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function markAsStarted(): self
    {
        $this->startedAt = new \DateTimeImmutable();
        $this->status = self::STATUS_RUNNING;
        return $this;
    }

    public function getPausedAt(): ?\DateTimeImmutable
    {
        return $this->pausedAt;
    }

    public function markAsPaused(): self
    {
        $this->pausedAt = new \DateTimeImmutable();
        $this->status = self::STATUS_PAUSED;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function markAsCompleted(?array $output = null): self
    {
        $this->completedAt = new \DateTimeImmutable();
        $this->status = self::STATUS_COMPLETED;
        if ($output !== null) {
            $this->output = $output;
        }
        return $this;
    }

    public function markAsFailed(string $error): self
    {
        $this->completedAt = new \DateTimeImmutable();
        $this->status = self::STATUS_FAILED;
        $this->setMetadataValue('error', $error);
        return $this;
    }

    public function markAsCancelled(): self
    {
        $this->completedAt = new \DateTimeImmutable();
        $this->status = self::STATUS_CANCELLED;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, AgentLog> */
    public function getLogs(): Collection
    {
        return $this->logs;
    }

    public function addLog(AgentLog $log): self
    {
        if (!$this->logs->contains($log)) {
            $this->logs->add($log);
            $log->setExecution($this);
        }
        return $this;
    }

    /** @return Collection<int, AgentSignal> */
    public function getSignals(): Collection
    {
        return $this->signals;
    }

    public function addSignal(AgentSignal $signal): self
    {
        if (!$this->signals->contains($signal)) {
            $this->signals->add($signal);
            $signal->setExecution($this);
        }
        return $this;
    }

    public function getUnprocessedSignal(): ?AgentSignal
    {
        foreach ($this->signals as $signal) {
            if (!$signal->isProcessed()) {
                return $signal;
            }
        }
        return null;
    }

    public function getDurationSeconds(): ?float
    {
        if ($this->startedAt === null) {
            return null;
        }

        $end = $this->completedAt ?? new \DateTimeImmutable();
        return (float) $end->getTimestamp() - $this->startedAt->getTimestamp();
    }
}
```

### AgentLog Entity

```php
<?php

namespace App\Entity;

use App\Repository\AgentLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AgentLogRepository::class)]
#[ORM\Table(name: 'agent_logs')]
#[ORM\Index(columns: ['execution_id', 'created_at'], name: 'idx_execution_created')]
class AgentLog
{
    public const LEVEL_DEBUG = 'debug';
    public const LEVEL_INFO = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR = 'error';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AgentExecution::class, inversedBy: 'logs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AgentExecution $execution;

    #[ORM\Column(length: 16)]
    private string $level = self::LEVEL_INFO;

    #[ORM\Column(length: 64)]
    private string $eventType;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $context = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function info(AgentExecution $execution, string $eventType, string $message, ?array $context = null): self
    {
        $log = new self();
        $log->execution = $execution;
        $log->level = self::LEVEL_INFO;
        $log->eventType = $eventType;
        $log->message = $message;
        $log->context = $context;
        return $log;
    }

    public static function error(AgentExecution $execution, string $eventType, string $message, ?array $context = null): self
    {
        $log = new self();
        $log->execution = $execution;
        $log->level = self::LEVEL_ERROR;
        $log->eventType = $eventType;
        $log->message = $message;
        $log->context = $context;
        return $log;
    }

    // Getters and setters...

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExecution(): AgentExecution
    {
        return $this->execution;
    }

    public function setExecution(AgentExecution $execution): self
    {
        $this->execution = $execution;
        return $this;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function setLevel(string $level): self
    {
        $this->level = $level;
        return $this;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): self
    {
        $this->eventType = $eventType;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getContext(): ?array
    {
        return $this->context;
    }

    public function setContext(?array $context): self
    {
        $this->context = $context;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
```

### AgentSignal Entity

```php
<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'agent_signals')]
#[ORM\Index(columns: ['execution_id', 'processed'], name: 'idx_execution_processed')]
class AgentSignal
{
    public const TYPE_PAUSE = 'pause';
    public const TYPE_RESUME = 'resume';
    public const TYPE_CANCEL = 'cancel';
    public const TYPE_INPUT = 'input';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AgentExecution::class, inversedBy: 'signals')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AgentExecution $execution;

    #[ORM\Column(length: 32)]
    private string $signalType;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $payload = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $processed = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function pause(AgentExecution $execution): self
    {
        $signal = new self();
        $signal->execution = $execution;
        $signal->signalType = self::TYPE_PAUSE;
        return $signal;
    }

    public static function cancel(AgentExecution $execution): self
    {
        $signal = new self();
        $signal->execution = $execution;
        $signal->signalType = self::TYPE_CANCEL;
        return $signal;
    }

    public static function input(AgentExecution $execution, array $payload): self
    {
        $signal = new self();
        $signal->execution = $execution;
        $signal->signalType = self::TYPE_INPUT;
        $signal->payload = $payload;
        return $signal;
    }

    // Getters and setters...

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExecution(): AgentExecution
    {
        return $this->execution;
    }

    public function setExecution(AgentExecution $execution): self
    {
        $this->execution = $execution;
        return $this;
    }

    public function getSignalType(): string
    {
        return $this->signalType;
    }

    public function setSignalType(string $signalType): self
    {
        $this->signalType = $signalType;
        return $this;
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function setPayload(?array $payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    public function isProcessed(): bool
    {
        return $this->processed;
    }

    public function markAsProcessed(): self
    {
        $this->processed = true;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
```

### Repository

```php
<?php

namespace App\Repository;

use App\Entity\AgentExecution;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AgentExecution>
 */
class AgentExecutionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgentExecution::class);
    }

    /**
     * @return AgentExecution[]
     */
    public function findByUser(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.user = :user')
            ->setParameter('user', $user)
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AgentExecution[]
     */
    public function findPendingByUser(User $user): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.user = :user')
            ->andWhere('e.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', [
                AgentExecution::STATUS_PENDING,
                AgentExecution::STATUS_RUNNING,
                AgentExecution::STATUS_PAUSED,
            ])
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countActiveByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.user = :user')
            ->andWhere('e.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', [
                AgentExecution::STATUS_PENDING,
                AgentExecution::STATUS_RUNNING,
                AgentExecution::STATUS_PAUSED,
                AgentExecution::STATUS_AWAITING_EVENT,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return AgentExecution[]
     */
    public function findStuck(\DateTimeImmutable $threshold): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.status = :status')
            ->andWhere('e.updatedAt < :threshold')
            ->setParameter('status', AgentExecution::STATUS_RUNNING)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AgentExecution[]
     */
    public function findAwaitingEvent(string $eventType, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.status = :status')
            ->andWhere("JSON_GET_TEXT(e.metadata, 'awaiting_event') = :eventType")
            ->setParameter('status', AgentExecution::STATUS_AWAITING_EVENT)
            ->setParameter('eventType', $eventType);

        if ($user !== null) {
            $qb->andWhere('e.user = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }
}
```

---

## Agent Execution Handler

### Message Handler

```php
<?php

namespace App\MessageHandler;

use App\Entity\AgentExecution;
use App\Entity\AgentLog;
use App\Entity\AgentSignal;
use App\Message\AgentStatusChanged;
use App\Message\ExecuteAgent;
use App\Service\AgentBuilderService;
use App\Service\AgentStateSerializer;
use App\Service\MercurePublisher;
use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Enums\AgentStatus;
use Cognesy\Addons\Agent\Events\AgentStepCompleted as AgentStepCompletedEvent;
use Cognesy\Addons\Agent\Events\ToolCallCompleted;
use Cognesy\Events\EventBus;
use Cognesy\Messages\Messages;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class ExecuteAgentHandler
{
    private const SIGNAL_CHECK_INTERVAL = 5;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AgentBuilderService $agentBuilder,
        private readonly AgentStateSerializer $stateSerializer,
        private readonly MercurePublisher $mercure,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(ExecuteAgent $message): void
    {
        $execution = $this->entityManager->find(AgentExecution::class, $message->executionId);

        if ($execution === null) {
            $this->logger->error('Execution not found', ['id' => $message->executionId]);
            return;
        }

        if ($execution->getStatus() === AgentExecution::STATUS_CANCELLED) {
            $this->logger->info('Execution already cancelled', ['id' => $message->executionId]);
            return;
        }

        $this->run($execution);
    }

    private function run(AgentExecution $execution): void
    {
        $execution->markAsStarted();
        $this->entityManager->flush();
        $this->publishStatusUpdate($execution);

        try {
            $agent = $this->agentBuilder->build($execution, $this->createEventBus($execution));
            $state = $this->initializeState($execution);

            $stepNumber = $execution->getStepCount();

            foreach ($agent->iterator($state) as $currentState) {
                $stepNumber++;

                // Log step
                $this->logStep($execution, $currentState, $stepNumber);

                // Publish progress via Mercure
                $this->publishStepProgress($execution, $currentState, $stepNumber);

                // Check for signals periodically
                if ($stepNumber % self::SIGNAL_CHECK_INTERVAL === 0) {
                    $this->entityManager->refresh($execution);
                    $signal = $this->checkSignals($execution);

                    if ($signal === AgentSignal::TYPE_PAUSE) {
                        $this->pauseExecution($execution, $currentState);
                        return;
                    }

                    if ($signal === AgentSignal::TYPE_CANCEL) {
                        $this->cancelExecution($execution);
                        return;
                    }
                }

                // Update progress
                $execution->setStepCount($stepNumber);
                $execution->setTokenUsage($currentState->usage()->total());
                $this->entityManager->flush();

                $state = $currentState;
            }

            // Completed
            $this->completeExecution($execution, $state);

        } catch (\Throwable $e) {
            $this->failExecution($execution, $e);
            $this->logger->error('Agent execution failed', [
                'execution_id' => (string) $execution->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function createEventBus(AgentExecution $execution): EventBus
    {
        $eventBus = new EventBus();

        $eventBus->onEvent(AgentStepCompletedEvent::class, function ($event) use ($execution) {
            $execution->addLog(AgentLog::info(
                $execution,
                'step_completed',
                'Step completed',
                $event->payload()
            ));
        });

        $eventBus->onEvent(ToolCallCompleted::class, function ($event) use ($execution) {
            $execution->addLog(AgentLog::info(
                $execution,
                'tool_completed',
                "Tool {$event->payload()['toolName']} completed",
                $event->payload()
            ));
        });

        return $eventBus;
    }

    private function initializeState(AgentExecution $execution): AgentState
    {
        if ($execution->getStateSnapshot() !== null) {
            return $this->stateSerializer->deserialize($execution->getStateSnapshot());
        }

        $prompt = $execution->getInput()['prompt'] ?? '';
        return AgentState::empty()->withMessages(Messages::fromString($prompt));
    }

    private function checkSignals(AgentExecution $execution): ?string
    {
        $signal = $execution->getUnprocessedSignal();

        if ($signal !== null) {
            $signal->markAsProcessed();
            $this->entityManager->flush();
            return $signal->getSignalType();
        }

        return null;
    }

    private function pauseExecution(AgentExecution $execution, AgentState $state): void
    {
        $execution->markAsPaused();
        $execution->setStateSnapshot($this->stateSerializer->serialize($state));
        $execution->addLog(AgentLog::info($execution, 'paused', 'Execution paused'));
        $this->entityManager->flush();
        $this->publishStatusUpdate($execution);
    }

    private function cancelExecution(AgentExecution $execution): void
    {
        $execution->markAsCancelled();
        $execution->addLog(AgentLog::info($execution, 'cancelled', 'Execution cancelled'));
        $this->entityManager->flush();
        $this->publishStatusUpdate($execution);
    }

    private function completeExecution(AgentExecution $execution, AgentState $state): void
    {
        $output = $state->currentStep()?->outputMessages()->toString();

        $execution->markAsCompleted(['response' => $output]);
        $execution->addLog(AgentLog::info($execution, 'completed', 'Execution completed', [
            'steps' => $execution->getStepCount(),
            'tokens' => $state->usage()->total(),
        ]));
        $this->entityManager->flush();
        $this->publishStatusUpdate($execution);
    }

    private function failExecution(AgentExecution $execution, \Throwable $e): void
    {
        $execution->markAsFailed($e->getMessage());
        $execution->addLog(AgentLog::error($execution, 'failed', $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
        ]));
        $this->entityManager->flush();
        $this->publishStatusUpdate($execution);
    }

    private function logStep(AgentExecution $execution, AgentState $state, int $stepNumber): void
    {
        $step = $state->currentStep();

        $execution->addLog(AgentLog::info(
            $execution,
            'step_completed',
            "Step {$stepNumber} completed",
            [
                'step_type' => $step?->stepType()->value,
                'has_tool_calls' => $step?->hasToolCalls(),
                'tool_names' => $step?->toolCalls()->names() ?? [],
                'tokens_used' => $step?->usage()->total(),
            ]
        ));
    }

    private function publishStepProgress(AgentExecution $execution, AgentState $state, int $stepNumber): void
    {
        $step = $state->currentStep();

        $this->mercure->publish(
            topics: [
                "agent/{$execution->getId()}",
                "user/{$execution->getUser()->getId()}/agents",
            ],
            data: [
                'type' => 'step.completed',
                'execution_id' => (string) $execution->getId(),
                'step_number' => $stepNumber,
                'step_type' => $step?->stepType()->value,
                'has_tool_calls' => $step?->hasToolCalls(),
                'tool_names' => $step?->toolCalls()->names() ?? [],
                'tokens_used' => $state->usage()->total(),
            ]
        );
    }

    private function publishStatusUpdate(AgentExecution $execution): void
    {
        $this->mercure->publish(
            topics: [
                "agent/{$execution->getId()}",
                "user/{$execution->getUser()->getId()}/agents",
            ],
            data: [
                'type' => 'status.changed',
                'execution_id' => (string) $execution->getId(),
                'status' => $execution->getStatus(),
                'step_count' => $execution->getStepCount(),
                'token_usage' => $execution->getTokenUsage(),
                'completed_at' => $execution->getCompletedAt()?->format('c'),
            ]
        );
    }
}
```

### Agent Builder Service

```php
<?php

namespace App\Service;

use App\Entity\AgentExecution;
use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\Bash\UseBash;
use Cognesy\Addons\Agent\Capabilities\File\UseFileTools;
use Cognesy\Addons\Agent\Capabilities\Metadata\UseMetadataTools;
use Cognesy\Addons\Agent\Capabilities\Subagent\UseSubagents;
use Cognesy\Addons\Agent\Capabilities\Tasks\UseTaskPlanning;
use Cognesy\Addons\Agent\Registry\AgentRegistry;
use Cognesy\Events\EventBus;
use Cognesy\Utils\Sandbox\Config\ExecutionPolicy;

final class AgentBuilderService
{
    public function __construct(
        private readonly string $workspacesPath,
        private readonly AgentRegistry $agentRegistry,
    ) {}

    public function build(AgentExecution $execution, ?EventBus $eventBus = null): Agent
    {
        $workspacePath = $this->getWorkspacePath($execution);
        $this->ensureWorkspaceExists($workspacePath);

        $builder = AgentBuilder::base();

        // Apply capabilities based on agent type
        $this->applyCapabilities($builder, $execution->getAgentType(), $workspacePath);

        // Apply execution limits
        $this->applyLimits($builder, $execution);

        if ($eventBus !== null) {
            $builder->withEvents($eventBus);
        }

        return $builder->build();
    }

    private function applyCapabilities(AgentBuilder $builder, string $agentType, string $workspacePath): void
    {
        match ($agentType) {
            'code-assistant' => $this->applyCodeAssistantCapabilities($builder, $workspacePath),
            'research' => $this->applyResearchCapabilities($builder, $workspacePath),
            'code-review' => $this->applyCodeReviewCapabilities($builder, $workspacePath),
            default => throw new \InvalidArgumentException("Unknown agent type: {$agentType}"),
        };
    }

    private function applyCodeAssistantCapabilities(AgentBuilder $builder, string $workspacePath): void
    {
        $bashPolicy = ExecutionPolicy::in($workspacePath)
            ->withTimeout(120)
            ->withNetwork(false)
            ->withReadablePaths($workspacePath, '/usr/share')
            ->withWritablePaths($workspacePath);

        $builder
            ->withCapability(new UseBash(policy: $bashPolicy))
            ->withCapability(new UseFileTools($workspacePath))
            ->withCapability(new UseTaskPlanning())
            ->withCapability(new UseMetadataTools())
            ->withCapability(UseSubagents::withDepth(2, $this->agentRegistry));
    }

    private function applyResearchCapabilities(AgentBuilder $builder, string $workspacePath): void
    {
        $builder
            ->withCapability(new UseFileTools($workspacePath))
            ->withCapability(new UseTaskPlanning())
            ->withCapability(new UseMetadataTools())
            ->withCapability(UseSubagents::withDepth(3, $this->agentRegistry, summaryMaxChars: 12000));
    }

    private function applyCodeReviewCapabilities(AgentBuilder $builder, string $workspacePath): void
    {
        $builder
            ->withCapability(new UseFileTools($workspacePath))
            ->withCapability(new UseTaskPlanning());
    }

    private function applyLimits(AgentBuilder $builder, AgentExecution $execution): void
    {
        $limits = $this->getLimitsForAgentType($execution->getAgentType());

        $builder
            ->withMaxSteps($limits['max_steps'])
            ->withMaxTokens($limits['max_tokens'])
            ->withTimeout($limits['timeout']);
    }

    private function getLimitsForAgentType(string $agentType): array
    {
        return match ($agentType) {
            'code-assistant' => ['max_steps' => 50, 'max_tokens' => 50000, 'timeout' => 300],
            'research' => ['max_steps' => 100, 'max_tokens' => 100000, 'timeout' => 1800],
            'code-review' => ['max_steps' => 30, 'max_tokens' => 30000, 'timeout' => 180],
            default => ['max_steps' => 20, 'max_tokens' => 20000, 'timeout' => 120],
        };
    }

    private function getWorkspacePath(AgentExecution $execution): string
    {
        return sprintf(
            '%s/%s/%s',
            $this->workspacesPath,
            $execution->getUser()->getId(),
            $execution->getId()
        );
    }

    private function ensureWorkspaceExists(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
```

### State Serializer

```php
<?php

namespace App\Service;

use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Messages\Messages;

final class AgentStateSerializer
{
    public function serialize(AgentState $state): array
    {
        return [
            'messages' => $state->messages()->toArray(),
            'metadata' => $state->metadata()->toArray(),
            'step_count' => $state->stepCount(),
            'usage' => [
                'input' => $state->usage()->input(),
                'output' => $state->usage()->output(),
            ],
            'serialized_at' => (new \DateTimeImmutable())->format('c'),
        ];
    }

    public function deserialize(array $data): AgentState
    {
        return AgentState::empty()
            ->withMessages(Messages::fromArray($data['messages'] ?? []))
            ->withMetadata($data['metadata'] ?? []);
    }
}
```

---

## Status Communication & Mercure

### Mercure Publisher Service

```php
<?php

namespace App\Service;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class MercurePublisher
{
    public function __construct(
        private readonly HubInterface $hub,
    ) {}

    /**
     * @param string|string[] $topics
     */
    public function publish(string|array $topics, array $data, bool $private = true): void
    {
        $topics = is_array($topics) ? $topics : [$topics];

        $update = new Update(
            topics: $topics,
            data: json_encode($data, JSON_THROW_ON_ERROR),
            private: $private,
        );

        $this->hub->publish($update);
    }

    public function publishToExecution(string $executionId, array $data): void
    {
        $this->publish("agent/{$executionId}", $data);
    }

    public function publishToUser(int $userId, array $data): void
    {
        $this->publish("user/{$userId}/agents", $data);
    }
}
```

### Mercure Configuration

```yaml
# config/packages/mercure.yaml
mercure:
    hubs:
        default:
            url: '%env(MERCURE_URL)%'
            public_url: '%env(MERCURE_PUBLIC_URL)%'
            jwt:
                secret: '%env(MERCURE_JWT_SECRET)%'
                publish: ['*']
                subscribe: ['*']
```

```bash
# .env
MERCURE_URL=http://localhost:3000/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost:3000/.well-known/mercure
MERCURE_JWT_SECRET=your-jwt-secret
```

### JWT Token Generator for Subscriptions

```php
<?php

namespace App\Service;

use App\Entity\User;
use Firebase\JWT\JWT;

final class MercureTokenGenerator
{
    public function __construct(
        private readonly string $jwtSecret,
    ) {}

    /**
     * Generate JWT token for user to subscribe to their agent updates.
     */
    public function generateSubscriptionToken(User $user, ?string $executionId = null): string
    {
        $topics = [
            "user/{$user->getId()}/agents",
        ];

        if ($executionId !== null) {
            $topics[] = "agent/{$executionId}";
        }

        $payload = [
            'mercure' => [
                'subscribe' => $topics,
            ],
            'exp' => time() + 3600, // 1 hour
        ];

        return JWT::encode($payload, $this->jwtSecret, 'HS256');
    }
}
```

### API Endpoint for Mercure Token

```php
<?php

namespace App\Controller\Api;

use App\Service\MercureTokenGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/agents')]
#[IsGranted('ROLE_USER')]
class AgentMercureController extends AbstractController
{
    public function __construct(
        private readonly MercureTokenGenerator $tokenGenerator,
    ) {}

    #[Route('/mercure-token', methods: ['GET'])]
    public function getMercureToken(): JsonResponse
    {
        $user = $this->getUser();
        $token = $this->tokenGenerator->generateSubscriptionToken($user);

        return $this->json([
            'token' => $token,
            'hub_url' => $_ENV['MERCURE_PUBLIC_URL'],
        ]);
    }

    #[Route('/{executionId}/mercure-token', methods: ['GET'])]
    public function getExecutionMercureToken(string $executionId): JsonResponse
    {
        $user = $this->getUser();
        $token = $this->tokenGenerator->generateSubscriptionToken($user, $executionId);

        return $this->json([
            'token' => $token,
            'hub_url' => $_ENV['MERCURE_PUBLIC_URL'],
            'topic' => "agent/{$executionId}",
        ]);
    }
}
```

---

## Lifecycle Management

### Agent Manager Service

```php
<?php

namespace App\Service;

use App\Entity\AgentExecution;
use App\Entity\AgentSignal;
use App\Entity\User;
use App\Message\ContinueAgentExecution;
use App\Message\ExecuteAgent;
use App\Repository\AgentExecutionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

final class AgentManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly AgentExecutionRepository $executionRepository,
        private readonly AgentRateLimiter $rateLimiter,
    ) {}

    /**
     * Start a new agent execution.
     */
    public function start(User $user, string $agentType, array $input, array $options = []): AgentExecution
    {
        // Check rate limits
        if (!$this->rateLimiter->canStart($user)) {
            throw new RateLimitExceededException('Agent rate limit exceeded');
        }

        $execution = new AgentExecution();
        $execution->setUser($user);
        $execution->setAgentType($agentType);
        $execution->setInput($input);
        $execution->setMetadata($options['metadata'] ?? []);

        $this->entityManager->persist($execution);
        $this->entityManager->flush();

        // Record rate limit hit
        $this->rateLimiter->hit($user);

        // Dispatch to appropriate queue
        $queue = $this->selectQueue($agentType, $user);
        $this->messageBus->dispatch(
            new ExecuteAgent((string) $execution->getId()),
            [new TransportNamesStamp([$queue])]
        );

        return $execution;
    }

    /**
     * Pause a running execution.
     */
    public function pause(AgentExecution $execution): void
    {
        if (!$execution->canBePaused()) {
            throw new \InvalidArgumentException('Execution cannot be paused');
        }

        $signal = AgentSignal::pause($execution);
        $this->entityManager->persist($signal);
        $this->entityManager->flush();
    }

    /**
     * Resume a paused execution.
     */
    public function resume(AgentExecution $execution): void
    {
        if (!$execution->canBeResumed()) {
            throw new \InvalidArgumentException('Execution cannot be resumed');
        }

        $execution->setStatus(AgentExecution::STATUS_PENDING);
        $this->entityManager->flush();

        $queue = $this->selectQueue($execution->getAgentType(), $execution->getUser());
        $this->messageBus->dispatch(
            new ContinueAgentExecution((string) $execution->getId()),
            [new TransportNamesStamp([$queue])]
        );
    }

    /**
     * Cancel an execution (graceful).
     */
    public function cancel(AgentExecution $execution): void
    {
        if ($execution->isFinished()) {
            return;
        }

        if ($execution->getStatus() === AgentExecution::STATUS_PENDING) {
            $execution->markAsCancelled();
            $this->entityManager->flush();
            return;
        }

        $signal = AgentSignal::cancel($execution);
        $this->entityManager->persist($signal);
        $this->entityManager->flush();
    }

    /**
     * Force kill an execution (immediate).
     */
    public function forceKill(AgentExecution $execution): void
    {
        $execution->markAsCancelled();
        $execution->setMetadataValue('force_killed', true);
        $this->entityManager->flush();
    }

    /**
     * Send input to an execution awaiting input.
     */
    public function sendInput(AgentExecution $execution, array $input): void
    {
        if ($execution->getStatus() !== AgentExecution::STATUS_AWAITING_INPUT) {
            throw new \InvalidArgumentException('Execution is not awaiting input');
        }

        $signal = AgentSignal::input($execution, $input);
        $this->entityManager->persist($signal);
        $this->entityManager->flush();

        $this->resume($execution);
    }

    private function selectQueue(string $agentType, User $user): string
    {
        // Premium users get priority queue
        if ($user->isPremium()) {
            return 'agents_priority';
        }

        // Long-running agent types
        if (in_array($agentType, ['research', 'migration'], true)) {
            return 'agents_long';
        }

        return 'agents';
    }
}
```

### Agent Controller

```php
<?php

namespace App\Controller\Api;

use App\Entity\AgentExecution;
use App\Repository\AgentExecutionRepository;
use App\Service\AgentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/agents')]
#[IsGranted('ROLE_USER')]
class AgentController extends AbstractController
{
    public function __construct(
        private readonly AgentManager $agentManager,
        private readonly AgentExecutionRepository $executionRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $executions = $this->executionRepository->findByUser($this->getUser());

        return $this->json([
            'data' => array_map([$this, 'serializeExecution'], $executions),
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $request->toArray();

        try {
            $execution = $this->agentManager->start(
                user: $this->getUser(),
                agentType: $data['agent_type'] ?? 'code-assistant',
                input: $data['input'] ?? [],
                options: $data['options'] ?? [],
            );

            return $this->json(
                $this->serializeExecution($execution),
                Response::HTTP_CREATED
            );
        } catch (RateLimitExceededException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_TOO_MANY_REQUESTS);
        }
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(AgentExecution $execution): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $execution);

        return $this->json($this->serializeExecution($execution, withLogs: true));
    }

    #[Route('/{id}/pause', methods: ['POST'])]
    public function pause(AgentExecution $execution): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $execution);

        try {
            $this->agentManager->pause($execution);
            return $this->json(['message' => 'Pause signal sent']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/resume', methods: ['POST'])]
    public function resume(AgentExecution $execution): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $execution);

        try {
            $this->agentManager->resume($execution);
            return $this->json(['message' => 'Execution resumed']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/cancel', methods: ['POST'])]
    public function cancel(AgentExecution $execution): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $execution);

        $this->agentManager->cancel($execution);
        return $this->json(['message' => 'Cancel signal sent']);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function forceKill(AgentExecution $execution): JsonResponse
    {
        $this->denyAccessUnlessGranted('delete', $execution);

        $this->agentManager->forceKill($execution);
        return $this->json(['message' => 'Execution force killed']);
    }

    #[Route('/{id}/input', methods: ['POST'])]
    public function sendInput(Request $request, AgentExecution $execution): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $execution);

        $data = $request->toArray();

        try {
            $this->agentManager->sendInput($execution, $data['input'] ?? []);
            return $this->json(['message' => 'Input sent']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/logs', methods: ['GET'])]
    public function logs(Request $request, AgentExecution $execution): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $execution);

        $logs = $execution->getLogs();
        $afterId = $request->query->getInt('after_id', 0);

        if ($afterId > 0) {
            $logs = $logs->filter(fn($log) => $log->getId() > $afterId);
        }

        return $this->json([
            'data' => array_map(fn($log) => [
                'id' => $log->getId(),
                'level' => $log->getLevel(),
                'event_type' => $log->getEventType(),
                'message' => $log->getMessage(),
                'context' => $log->getContext(),
                'created_at' => $log->getCreatedAt()->format('c'),
            ], $logs->toArray()),
        ]);
    }

    private function serializeExecution(AgentExecution $execution, bool $withLogs = false): array
    {
        $data = [
            'id' => (string) $execution->getId(),
            'agent_type' => $execution->getAgentType(),
            'status' => $execution->getStatus(),
            'step_count' => $execution->getStepCount(),
            'token_usage' => $execution->getTokenUsage(),
            'input' => $execution->getInput(),
            'output' => $execution->getOutput(),
            'started_at' => $execution->getStartedAt()?->format('c'),
            'completed_at' => $execution->getCompletedAt()?->format('c'),
            'created_at' => $execution->getCreatedAt()->format('c'),
            'duration_seconds' => $execution->getDurationSeconds(),
        ];

        if ($withLogs) {
            $data['logs'] = array_map(fn($log) => [
                'id' => $log->getId(),
                'level' => $log->getLevel(),
                'event_type' => $log->getEventType(),
                'message' => $log->getMessage(),
                'context' => $log->getContext(),
                'created_at' => $log->getCreatedAt()->format('c'),
            ], $execution->getLogs()->slice(0, 100));
        }

        return $data;
    }
}
```

---

## Worker Configuration

### Supervisor Configuration

```ini
; /etc/supervisor/conf.d/messenger-workers.conf

; Default agent workers
[program:messenger-agents]
command=php /var/www/app/bin/console messenger:consume agents --time-limit=3600 --memory-limit=512M
user=www-data
numprocs=5
autostart=true
autorestart=true
process_name=%(program_name)s_%(process_num)02d
startsecs=0
stdout_logfile=/var/log/supervisor/messenger-agents.log
stderr_logfile=/var/log/supervisor/messenger-agents-error.log

; Long-running agent workers
[program:messenger-agents-long]
command=php /var/www/app/bin/console messenger:consume agents_long --time-limit=7200 --memory-limit=1024M
user=www-data
numprocs=3
autostart=true
autorestart=true
process_name=%(program_name)s_%(process_num)02d
startsecs=0
stdout_logfile=/var/log/supervisor/messenger-agents-long.log
stderr_logfile=/var/log/supervisor/messenger-agents-long-error.log

; Priority agent workers
[program:messenger-agents-priority]
command=php /var/www/app/bin/console messenger:consume agents_priority --time-limit=3600 --memory-limit=512M
user=www-data
numprocs=3
autostart=true
autorestart=true
process_name=%(program_name)s_%(process_num)02d
startsecs=0
stdout_logfile=/var/log/supervisor/messenger-agents-priority.log
stderr_logfile=/var/log/supervisor/messenger-agents-priority-error.log

; Async event workers
[program:messenger-async]
command=php /var/www/app/bin/console messenger:consume async --time-limit=3600 --memory-limit=256M
user=www-data
numprocs=2
autostart=true
autorestart=true
process_name=%(program_name)s_%(process_num)02d
startsecs=0
stdout_logfile=/var/log/supervisor/messenger-async.log
stderr_logfile=/var/log/supervisor/messenger-async-error.log

; Worker group
[group:messenger]
programs=messenger-agents,messenger-agents-long,messenger-agents-priority,messenger-async
```

### Systemd Configuration (Alternative)

```ini
; /etc/systemd/system/messenger-agents@.service

[Unit]
Description=Symfony Messenger Agent Worker %i
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/app
ExecStart=/usr/bin/php bin/console messenger:consume agents --time-limit=3600 --memory-limit=512M
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
# Enable multiple workers
sudo systemctl enable messenger-agents@{1..5}
sudo systemctl start messenger-agents@{1..5}
```

### Console Command for Development

```php
<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:workers:start',
    description: 'Start all messenger workers for development',
)]
class StartWorkersCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('queues', null, InputOption::VALUE_OPTIONAL, 'Queues to consume', 'agents,agents_long,async');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queues = explode(',', $input->getOption('queues'));
        $processes = [];

        foreach ($queues as $queue) {
            $output->writeln("<info>Starting worker for queue: {$queue}</info>");

            $process = new Process([
                'php',
                'bin/console',
                'messenger:consume',
                $queue,
                '--time-limit=3600',
                '-vv',
            ]);
            $process->setTimeout(null);
            $process->start();
            $processes[] = $process;
        }

        $output->writeln('<info>All workers started. Press Ctrl+C to stop.</info>');

        while (true) {
            foreach ($processes as $process) {
                echo $process->getIncrementalOutput();
                echo $process->getIncrementalErrorOutput();
            }
            usleep(100000);
        }

        return Command::SUCCESS;
    }
}
```

---

## Scaling & Parallel Execution

### Rate Limiter

```php
<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\AgentExecutionRepository;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class AgentRateLimiter
{
    private array $limits = [
        'free' => ['max_concurrent' => 1, 'per_hour' => 10],
        'pro' => ['max_concurrent' => 5, 'per_hour' => 100],
        'enterprise' => ['max_concurrent' => 20, 'per_hour' => 1000],
    ];

    public function __construct(
        private readonly AgentExecutionRepository $executionRepository,
        private readonly RateLimiterFactory $agentLimiter,
    ) {}

    public function canStart(User $user): bool
    {
        $tier = $user->getSubscriptionTier() ?? 'free';
        $limits = $this->limits[$tier] ?? $this->limits['free'];

        // Check concurrent limit
        $concurrent = $this->executionRepository->countActiveByUser($user);
        if ($concurrent >= $limits['max_concurrent']) {
            return false;
        }

        // Check hourly rate limit
        $limiter = $this->agentLimiter->create("user_{$user->getId()}");
        if (!$limiter->consume()->isAccepted()) {
            return false;
        }

        return true;
    }

    public function hit(User $user): void
    {
        $limiter = $this->agentLimiter->create("user_{$user->getId()}");
        $limiter->consume();
    }

    public function remaining(User $user): array
    {
        $tier = $user->getSubscriptionTier() ?? 'free';
        $limits = $this->limits[$tier] ?? $this->limits['free'];

        $concurrent = $this->executionRepository->countActiveByUser($user);
        $limiter = $this->agentLimiter->create("user_{$user->getId()}");
        $limit = $limiter->consume(0);

        return [
            'concurrent' => [
                'used' => $concurrent,
                'limit' => $limits['max_concurrent'],
                'remaining' => max(0, $limits['max_concurrent'] - $concurrent),
            ],
            'hourly' => [
                'limit' => $limits['per_hour'],
                'remaining' => $limit->getRemainingTokens(),
                'retry_after' => $limit->getRetryAfter()?->getTimestamp(),
            ],
        ];
    }
}
```

### Rate Limiter Configuration

```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        agent_limiter:
            policy: 'sliding_window'
            limit: 100
            interval: '1 hour'
```

---

## Event-Driven Awakening

### Event Trigger Handler

```php
<?php

namespace App\MessageHandler;

use App\Entity\AgentExecution;
use App\Entity\AgentSignal;
use App\Message\ContinueAgentExecution;
use App\Message\TriggerAgentEvent;
use App\Repository\AgentExecutionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class TriggerAgentEventHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AgentExecutionRepository $executionRepository,
        private readonly MessageBusInterface $messageBus,
    ) {}

    public function __invoke(TriggerAgentEvent $message): int
    {
        $executions = $this->executionRepository->findAwaitingEvent(
            eventType: $message->eventType,
            userId: $message->userId,
        );

        $triggeredCount = 0;

        foreach ($executions as $execution) {
            // Create signal with payload
            $signal = new AgentSignal();
            $signal->setExecution($execution);
            $signal->setSignalType($message->eventType);
            $signal->setPayload($message->payload);
            $this->entityManager->persist($signal);

            // Update status and dispatch continuation
            $execution->setStatus(AgentExecution::STATUS_PENDING);
            $this->messageBus->dispatch(
                new ContinueAgentExecution((string) $execution->getId())
            );

            $triggeredCount++;
        }

        $this->entityManager->flush();

        return $triggeredCount;
    }
}
```

### Event Trigger Service

```php
<?php

namespace App\Service;

use App\Entity\AgentExecution;
use App\Entity\User;
use App\Message\ExecuteAgent;
use App\Message\TriggerAgentEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

final class AgentEventTrigger
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {}

    /**
     * Start an agent that waits for a specific event.
     */
    public function startAwaitingEvent(
        User $user,
        string $agentType,
        array $input,
        string $awaitEventType,
    ): AgentExecution {
        $execution = new AgentExecution();
        $execution->setUser($user);
        $execution->setAgentType($agentType);
        $execution->setStatus(AgentExecution::STATUS_AWAITING_EVENT);
        $execution->setInput($input);
        $execution->setMetadataValue('awaiting_event', $awaitEventType);

        $this->entityManager->persist($execution);
        $this->entityManager->flush();

        // Dispatch to long queue since it will wait
        $this->messageBus->dispatch(
            new ExecuteAgent((string) $execution->getId()),
            [new TransportNamesStamp(['agents_long'])]
        );

        return $execution;
    }

    /**
     * Trigger an event to awaken waiting agents.
     */
    public function triggerEvent(string $eventType, array $payload = [], ?int $userId = null): void
    {
        $this->messageBus->dispatch(
            new TriggerAgentEvent($eventType, $payload, $userId)
        );
    }

    /**
     * Schedule an agent to run at a specific time.
     */
    public function scheduleAt(
        User $user,
        string $agentType,
        array $input,
        \DateTimeImmutable $runAt,
    ): AgentExecution {
        $execution = new AgentExecution();
        $execution->setUser($user);
        $execution->setAgentType($agentType);
        $execution->setStatus(AgentExecution::STATUS_PENDING);
        $execution->setInput($input);
        $execution->setMetadataValue('scheduled_for', $runAt->format('c'));

        $this->entityManager->persist($execution);
        $this->entityManager->flush();

        // Use Symfony Scheduler or delay stamp
        $this->messageBus->dispatch(
            new ExecuteAgent((string) $execution->getId()),
            [new \Symfony\Component\Messenger\Stamp\DelayStamp(
                ($runAt->getTimestamp() - time()) * 1000
            )]
        );

        return $execution;
    }
}
```

### Webhook Controller

```php
<?php

namespace App\Controller\Webhook;

use App\Service\AgentEventTrigger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/webhooks')]
class AgentWebhookController extends AbstractController
{
    public function __construct(
        private readonly AgentEventTrigger $eventTrigger,
    ) {}

    #[Route('/agent-trigger', methods: ['POST'])]
    public function trigger(Request $request): JsonResponse
    {
        // Verify webhook signature here...

        $data = $request->toArray();
        $eventType = $data['event_type'] ?? null;

        if ($eventType === null) {
            return $this->json(['error' => 'event_type required'], 400);
        }

        $this->eventTrigger->triggerEvent(
            eventType: $eventType,
            payload: $data['payload'] ?? [],
            userId: $data['user_id'] ?? null,
        );

        return $this->json(['message' => 'Event triggered']);
    }
}
```

---

## Long-Running Jobs

### Chunked Execution Handler

```php
<?php

namespace App\MessageHandler;

use App\Entity\AgentExecution;
use App\Message\ExecuteAgentChunk;
use App\Service\AgentBuilderService;
use App\Service\AgentStateSerializer;
use App\Service\MercurePublisher;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Messages\Messages;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

#[AsMessageHandler]
final class ExecuteAgentChunkHandler
{
    private const MAX_CHUNKS = 50;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AgentBuilderService $agentBuilder,
        private readonly AgentStateSerializer $stateSerializer,
        private readonly MercurePublisher $mercure,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(ExecuteAgentChunk $message): void
    {
        $execution = $this->entityManager->find(AgentExecution::class, $message->executionId);

        if ($execution === null || $execution->getStatus() === AgentExecution::STATUS_CANCELLED) {
            return;
        }

        $this->executeChunk($execution, $message->chunkNumber, $message->stepsPerChunk);
    }

    private function executeChunk(AgentExecution $execution, int $chunkNumber, int $stepsPerChunk): void
    {
        if ($chunkNumber === 0) {
            $execution->markAsStarted();
            $this->entityManager->flush();
        }

        $agent = $this->agentBuilder->build($execution);
        $state = $this->loadState($execution);

        $stepsInChunk = 0;
        $shouldContinue = true;

        foreach ($agent->iterator($state) as $currentState) {
            $stepsInChunk++;
            $state = $currentState;

            $execution->incrementStepCount();
            $execution->setTokenUsage($state->usage()->total());

            // Checkpoint every step in chunked mode
            $this->saveCheckpoint($execution, $state, $chunkNumber);

            if ($stepsInChunk >= $stepsPerChunk) {
                break;
            }

            if (!$agent->hasNextStep($state)) {
                $shouldContinue = false;
                break;
            }
        }

        $this->entityManager->flush();

        if ($shouldContinue && $chunkNumber < self::MAX_CHUNKS) {
            // Schedule next chunk with small delay
            $this->messageBus->dispatch(
                new ExecuteAgentChunk(
                    (string) $execution->getId(),
                    $chunkNumber + 1,
                    $stepsPerChunk
                ),
                [
                    new TransportNamesStamp(['agents_long']),
                    new DelayStamp(1000), // 1 second delay
                ]
            );
        } else {
            $this->completeExecution($execution, $state);
        }
    }

    private function loadState(AgentExecution $execution): AgentState
    {
        if ($execution->getStateSnapshot() !== null) {
            return $this->stateSerializer->deserialize($execution->getStateSnapshot());
        }

        return AgentState::empty()->withMessages(
            Messages::fromString($execution->getInput()['prompt'] ?? '')
        );
    }

    private function saveCheckpoint(AgentExecution $execution, AgentState $state, int $chunkNumber): void
    {
        $execution->setStateSnapshot($this->stateSerializer->serialize($state));
        $execution->setMetadataValue('last_checkpoint', (new \DateTimeImmutable())->format('c'));
        $execution->setMetadataValue('chunk_number', $chunkNumber);
    }

    private function completeExecution(AgentExecution $execution, AgentState $state): void
    {
        $output = $state->currentStep()?->outputMessages()->toString();
        $execution->markAsCompleted(['response' => $output]);
        $this->entityManager->flush();

        $this->mercure->publishToExecution((string) $execution->getId(), [
            'type' => 'status.changed',
            'status' => $execution->getStatus(),
        ]);
    }
}
```

### Stuck Agent Recovery Command

```php
<?php

namespace App\Command;

use App\Entity\AgentExecution;
use App\Message\ContinueAgentExecution;
use App\Repository\AgentExecutionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:agents:recover-stuck',
    description: 'Recover agents stuck in running state',
)]
class RecoverStuckAgentsCommand extends Command
{
    public function __construct(
        private readonly AgentExecutionRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('timeout', 't', InputOption::VALUE_OPTIONAL, 'Timeout in minutes', 30);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $timeoutMinutes = (int) $input->getOption('timeout');
        $threshold = new \DateTimeImmutable("-{$timeoutMinutes} minutes");

        $stuckExecutions = $this->repository->findStuck($threshold);

        $io->info(sprintf('Found %d stuck executions', count($stuckExecutions)));

        foreach ($stuckExecutions as $execution) {
            $io->text("Recovering: {$execution->getId()}");

            if ($execution->getStateSnapshot() !== null) {
                // Has checkpoint - restart from last state
                $execution->setStatus(AgentExecution::STATUS_PENDING);
                $this->entityManager->flush();

                $this->messageBus->dispatch(
                    new ContinueAgentExecution((string) $execution->getId())
                );

                $io->success('  -> Restarted from checkpoint');
            } else {
                // No checkpoint - mark as failed
                $execution->markAsFailed('Execution timed out without checkpoint');
                $this->entityManager->flush();

                $io->warning('  -> Marked as failed (no checkpoint)');
            }
        }

        return Command::SUCCESS;
    }
}
```

### Cleanup Command

```php
<?php

namespace App\Command;

use App\Entity\AgentExecution;
use App\Entity\AgentLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:agents:cleanup',
    description: 'Clean up old agent executions and logs',
)]
class CleanupAgentsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Delete executions older than N days', 30)
            ->addOption('logs-days', 'l', InputOption::VALUE_OPTIONAL, 'Delete logs older than N days', 7);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Delete old logs first
        $logsDays = (int) $input->getOption('logs-days');
        $logsThreshold = new \DateTimeImmutable("-{$logsDays} days");

        $logsDeleted = $this->entityManager->createQueryBuilder()
            ->delete(AgentLog::class, 'l')
            ->where('l.createdAt < :threshold')
            ->setParameter('threshold', $logsThreshold)
            ->getQuery()
            ->execute();

        $io->success("Deleted {$logsDeleted} logs older than {$logsDays} days");

        // Delete old completed executions
        $days = (int) $input->getOption('days');
        $threshold = new \DateTimeImmutable("-{$days} days");

        $executionsDeleted = $this->entityManager->createQueryBuilder()
            ->delete(AgentExecution::class, 'e')
            ->where('e.createdAt < :threshold')
            ->andWhere('e.status IN (:statuses)')
            ->setParameter('threshold', $threshold)
            ->setParameter('statuses', [
                AgentExecution::STATUS_COMPLETED,
                AgentExecution::STATUS_FAILED,
                AgentExecution::STATUS_CANCELLED,
            ])
            ->getQuery()
            ->execute();

        $io->success("Deleted {$executionsDeleted} executions older than {$days} days");

        return Command::SUCCESS;
    }
}
```

### Scheduler Configuration

```yaml
# config/packages/scheduler.yaml
framework:
    scheduler:
        schedules:
            default:
                tasks:
                    # Recover stuck agents every 15 minutes
                    - command: 'app:agents:recover-stuck --timeout=30'
                      frequency: '*/15 * * * *'

                    # Daily cleanup
                    - command: 'app:agents:cleanup --days=30 --logs-days=7'
                      frequency: '0 3 * * *'
```

---

## Complete Implementation Example

### Service Configuration

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    App\:
        resource: '../src/'
        exclude:
            - '../src/DependencyInjection/'
            - '../src/Entity/'
            - '../src/Kernel.php'

    # Agent services
    App\Service\AgentBuilderService:
        arguments:
            $workspacesPath: '%kernel.project_dir%/var/agent-workspaces'
            $agentRegistry: '@App\Service\AgentRegistryFactory'

    App\Service\AgentRegistryFactory:
        factory: ['@App\Service\AgentRegistryFactory', 'create']

    App\Service\MercureTokenGenerator:
        arguments:
            $jwtSecret: '%env(MERCURE_JWT_SECRET)%'
```

### Agent Registry Factory

```php
<?php

namespace App\Service;

use Cognesy\Addons\Agent\Registry\AgentRegistry;
use Cognesy\Addons\Agent\Registry\AgentSpec;

final class AgentRegistryFactory
{
    public static function create(): AgentRegistry
    {
        $registry = new AgentRegistry();

        // Register built-in agent types
        $registry->register(new AgentSpec(
            name: 'explorer',
            description: 'Read-only exploration and analysis',
            systemPrompt: 'You are a code explorer. Analyze and explain code without making changes.',
            tools: ['read_file', 'search_files', 'list_dir'],
        ));

        $registry->register(new AgentSpec(
            name: 'coder',
            description: 'Full coding capabilities with file editing',
            systemPrompt: 'You are a code assistant. Help with coding tasks.',
            tools: ['read_file', 'write_file', 'edit_file', 'search_files', 'bash', 'todo_write'],
        ));

        $registry->register(new AgentSpec(
            name: 'researcher',
            description: 'Deep research and analysis',
            systemPrompt: 'You are a research assistant. Thoroughly investigate topics.',
            tools: ['read_file', 'search_files', 'metadata_write', 'metadata_read'],
        ));

        return $registry;
    }
}
```

### Routes Configuration

```yaml
# config/routes/api.yaml
api_agents:
    resource: '../src/Controller/Api/AgentController.php'
    type: attribute
    prefix: /api

api_agents_mercure:
    resource: '../src/Controller/Api/AgentMercureController.php'
    type: attribute
    prefix: /api

webhooks:
    resource: '../src/Controller/Webhook/'
    type: attribute
```

### Security Configuration

```yaml
# config/packages/security.yaml
security:
    firewalls:
        api:
            pattern: ^/api
            stateless: true
            jwt: ~

        webhooks:
            pattern: ^/webhooks
            stateless: true
            # Custom webhook authenticator

    access_control:
        - { path: ^/api/agents, roles: ROLE_USER }
        - { path: ^/webhooks, roles: PUBLIC_ACCESS }
```

### Frontend Integration (JavaScript)

```javascript
// assets/js/agent-client.js
class AgentClient {
    constructor(apiBase, mercureHubUrl) {
        this.apiBase = apiBase;
        this.mercureHubUrl = mercureHubUrl;
        this.eventSource = null;
    }

    async start(agentType, input, options = {}) {
        const response = await fetch(`${this.apiBase}/agents`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.getToken()}`,
            },
            body: JSON.stringify({ agent_type: agentType, input, options }),
        });

        return response.json();
    }

    async pause(executionId) {
        return this.postAction(executionId, 'pause');
    }

    async resume(executionId) {
        return this.postAction(executionId, 'resume');
    }

    async cancel(executionId) {
        return this.postAction(executionId, 'cancel');
    }

    async postAction(executionId, action) {
        const response = await fetch(`${this.apiBase}/agents/${executionId}/${action}`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${this.getToken()}` },
        });
        return response.json();
    }

    subscribe(executionId, callbacks = {}) {
        const { onStep, onStatus, onError } = callbacks;

        // Get Mercure token
        fetch(`${this.apiBase}/agents/${executionId}/mercure-token`, {
            headers: { 'Authorization': `Bearer ${this.getToken()}` },
        })
        .then(r => r.json())
        .then(({ token, hub_url, topic }) => {
            const url = new URL(hub_url);
            url.searchParams.append('topic', topic);

            this.eventSource = new EventSource(url, {
                headers: { 'Authorization': `Bearer ${token}` },
            });

            this.eventSource.addEventListener('message', (event) => {
                const data = JSON.parse(event.data);

                if (data.type === 'step.completed' && onStep) {
                    onStep(data);
                } else if (data.type === 'status.changed' && onStatus) {
                    onStatus(data);
                }
            });

            this.eventSource.onerror = (error) => {
                if (onError) onError(error);
            };
        });
    }

    unsubscribe() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
    }

    getToken() {
        return localStorage.getItem('auth_token');
    }
}

// Usage
const client = new AgentClient('/api', 'http://localhost:3000/.well-known/mercure');

// Start agent
const execution = await client.start('code-assistant', {
    prompt: 'Review the authentication code for security issues',
});

// Subscribe to updates
client.subscribe(execution.id, {
    onStep: (data) => console.log('Step:', data.step_number),
    onStatus: (data) => {
        console.log('Status:', data.status);
        if (['completed', 'failed', 'cancelled'].includes(data.status)) {
            client.unsubscribe();
        }
    },
});
```

---

## Summary

| Component | Symfony Implementation |
|-----------|------------------------|
| Async Jobs | Symfony Messenger |
| Message Handlers | `#[AsMessageHandler]` attribute |
| Persistence | Doctrine ORM entities |
| Real-time Updates | Mercure Hub |
| Workers | supervisor/systemd |
| Rate Limiting | symfony/rate-limiter |
| Scheduling | symfony/scheduler |
| Event Triggering | MessageBus dispatch |

### Key Differences from Laravel

| Aspect | Laravel | Symfony |
|--------|---------|---------|
| Queue System | Laravel Queue + Horizon | Symfony Messenger |
| ORM | Eloquent | Doctrine |
| Real-time | Laravel Echo + Pusher/Soketi | Mercure |
| Rate Limiting | RateLimiter facade | RateLimiterFactory |
| Workers | Horizon dashboard | supervisor/systemd |
| Scheduling | Task Scheduling | Symfony Scheduler |
| Events | Laravel Events | Messenger async messages |

### Checklist

- [ ] Install required packages: `messenger`, `doctrine`, `mercure`, `rate-limiter`
- [ ] Create database migrations for entities
- [ ] Configure Messenger transports
- [ ] Set up Mercure hub
- [ ] Configure supervisor for workers
- [ ] Implement rate limiting
- [ ] Set up scheduled cleanup tasks
- [ ] Configure webhook authentication
- [ ] Set up monitoring/logging
