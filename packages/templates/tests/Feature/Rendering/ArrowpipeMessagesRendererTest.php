<?php declare(strict_types=1);

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Template\Rendering\ArrowpipeMessagesRenderer;

it('renders variables in message text parts using arrowpipe', function () {
    $messages = new Messages(
        new Message(role: 'user', content: 'Hello <|name|>'),
        new Message(role: 'assistant', content: 'Your id is <|id|>')
    );
    $renderer = new ArrowpipeMessagesRenderer();
    $rendered = $renderer->renderMessages($messages, ['name' => 'Alice', 'id' => 42]);

    $messageList = $rendered->all();
    expect($messageList[0]->content()->toString())->toBe('Hello Alice');
    expect($messageList[1]->content()->toString())->toBe('Your id is 42');
});
