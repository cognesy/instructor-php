<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Session;

use Cognesy\Agents\CanControlAgentLoop;
use Cognesy\Agents\Data\AgentBudget;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Session\Actions\ChangeBudget;
use Cognesy\Agents\Session\Actions\ChangeModel;
use Cognesy\Agents\Session\Actions\ChangeSystemPrompt;
use Cognesy\Agents\Session\Actions\ClearSession;
use Cognesy\Agents\Session\Actions\ForkSession;
use Cognesy\Agents\Session\Actions\ResumeSession;
use Cognesy\Agents\Session\Actions\SendMessage;
use Cognesy\Agents\Session\Actions\SuspendSession;
use Cognesy\Agents\Session\Actions\UpdateTask;
use Cognesy\Agents\Session\Actions\WriteMetadata;
use Cognesy\Agents\Session\AgentSession;
use Cognesy\Agents\Session\AgentSessionInfo;
use Cognesy\Agents\Session\SessionId;
use Cognesy\Agents\Session\SessionStatus;
use Cognesy\Agents\Template\Contracts\CanInstantiateAgentLoop;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

function makeActionSession(string $id = 'a1'): AgentSession {
    return new AgentSession(
        header: AgentSessionInfo::fresh(SessionId::from($id), 'agent', 'Agent'),
        definition: new AgentDefinition(name: 'agent', description: 'test', systemPrompt: 'Test'),
        state: AgentState::empty()->withUserMessage('hello')->withMetadata('keep', true),
    );
}

it('suspend and resume actions change session status', function () {
    $session = makeActionSession();

    $suspended = (new SuspendSession())->executeOn($session);
    $resumed = (new ResumeSession())->executeOn($suspended);

    expect($suspended->status())->toBe(SessionStatus::Suspended);
    expect($resumed->status())->toBe(SessionStatus::Active);
});

it('clear session resets messages while keeping metadata', function () {
    $session = makeActionSession();

    $cleared = (new ClearSession())->executeOn($session);

    expect($cleared->state()->messages()->isEmpty())->toBeTrue();
    expect($cleared->state()->metadata()->get('keep'))->toBeTrue();
});

it('change model action updates llm config on state', function () {
    $session = makeActionSession();
    $config = new LLMConfig(model: 'test-model');

    $updated = (new ChangeModel($config))->executeOn($session);

    expect($updated->state()->llmConfig()?->model)->toBe('test-model');
});

it('change budget action updates budget on state', function () {
    $session = makeActionSession();
    $budget = new AgentBudget(maxSteps: 7);

    $updated = (new ChangeBudget($budget))->executeOn($session);

    expect($updated->state()->budget()->maxSteps)->toBe(7);
});

it('change system prompt action updates context system prompt', function () {
    $session = makeActionSession();

    $updated = (new ChangeSystemPrompt('New prompt'))->executeOn($session);

    expect($updated->state()->context()->systemPrompt())->toBe('New prompt');
});

it('write metadata action updates metadata key', function () {
    $session = makeActionSession();

    $updated = (new WriteMetadata('project', 'alpha'))->executeOn($session);

    expect($updated->state()->metadata()->get('project'))->toBe('alpha');
});

it('update task action stores task list in metadata', function () {
    $session = makeActionSession();

    $updated = UpdateTask::fromArray([
        ['content' => 'Write tests', 'status' => 'in_progress', 'activeForm' => 'Writing tests'],
    ])->executeOn($session);

    $tasks = $updated->state()->metadata()->get('tasks');
    expect(is_array($tasks))->toBeTrue();
    expect($tasks[0]['content'])->toBe('Write tests');
});

it('fork session action returns new forked session with parent id', function () {
    $session = makeActionSession('parent-session');

    $forked = (new ForkSession(SessionId::from('forked-session')))->executeOn($session);

    expect($forked->sessionId())->toBe('forked-session');
    expect($forked->info()->parentId())->toBe('parent-session');
    expect($forked->status())->toBe(SessionStatus::Active);
});

it('send message action uses loop factory and stores executed state', function () {
    $session = makeActionSession();

    $loopFactory = new class implements CanInstantiateAgentLoop {
        public function instantiateAgentLoop(AgentDefinition $definition): CanControlAgentLoop {
            return new class implements CanControlAgentLoop {
                public function execute(AgentState $state): AgentState {
                    return $state->withMetadata('loop.executed', true);
                }

                public function iterate(AgentState $state): iterable {
                    yield $state;
                }
            };
        }
    };

    $updated = (new SendMessage('Follow up', $loopFactory))->executeOn($session);

    expect($updated->state()->metadata()->get('loop.executed'))->toBeTrue();
    expect($updated->state()->messages()->toString())->toContain('Follow up');
});
