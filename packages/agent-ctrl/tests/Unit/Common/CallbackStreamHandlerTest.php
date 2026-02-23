<?php declare(strict_types=1);

use Cognesy\AgentCtrl\Dto\AgentResponse;
use Cognesy\AgentCtrl\Dto\CallbackStreamHandler;
use Cognesy\AgentCtrl\Dto\StreamError;
use Cognesy\AgentCtrl\Enum\AgentType;

it('delivers onComplete callback only once', function () {
    $calls = 0;
    $handler = new CallbackStreamHandler(
        onComplete: function (AgentResponse $response) use (&$calls): void {
            $calls++;
        },
    );
    $response = new AgentResponse(
        agentType: AgentType::OpenCode,
        text: 'done',
        exitCode: 0,
    );

    $handler->onComplete($response);
    $handler->onComplete($response);

    expect($calls)->toBe(1);
});

it('delegates onError callback', function () {
    $messages = [];
    $handler = new CallbackStreamHandler(
        onError: function (StreamError $error) use (&$messages): void {
            $messages[] = $error->message;
        },
    );

    $handler->onError(new StreamError('stream warning', 'WARN'));

    expect($messages)->toBe(['stream warning']);
});
