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

    $arr = $rendered->toArray();
    expect($arr[0]['content'])->toBe('Hello Alice');
    expect($arr[1]['content'])->toBe('Your id is 42');
});
