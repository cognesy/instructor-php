<?php declare(strict_types=1);

use Cognesy\Instructor\Laravel\Facades\AgentCtrl as AgentCtrlFacade;
use Cognesy\Instructor\Laravel\Testing\AgentCtrlFake;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;

final class MinimalConfigRepository
{
    public function __construct(private array $items = []) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

it('routes AgentCtrl facade builder entrypoints through fake root', function () {
    $app = new Container();
    $app->instance('config', new MinimalConfigRepository([
        'instructor' => [
            'agents' => [],
        ],
    ]));
    Container::setInstance($app);
    Facade::setFacadeApplication($app);
    Facade::clearResolvedInstances();

    $fake = AgentCtrlFacade::fake(['fake-response']);
    $builder = AgentCtrlFacade::claudeCode();

    expect($fake)->toBeInstanceOf(AgentCtrlFake::class)
        ->and($builder)->toBe($fake)
        ->and($builder)->toBeInstanceOf(AgentCtrlFake::class);
});

it('records executions and returns fake response via facade entrypoints', function () {
    $app = new Container();
    $app->instance('config', new MinimalConfigRepository([
        'instructor' => [
            'agents' => [],
        ],
    ]));
    Container::setInstance($app);
    Facade::setFacadeApplication($app);
    Facade::clearResolvedInstances();

    AgentCtrlFacade::fake(['queued-fake-response']);
    $builder = AgentCtrlFacade::claudeCode();

    expect($builder)->toBeInstanceOf(AgentCtrlFake::class);

    $response = $builder->execute('run prompt');

    expect($response->text())->toBe('queued-fake-response');
    AgentCtrlFacade::assertExecuted();
    AgentCtrlFacade::assertExecutedWith('run prompt');
});
