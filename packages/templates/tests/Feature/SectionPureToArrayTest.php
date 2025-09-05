<?php declare(strict_types=1);

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\Script\Section;

it('Section::toArray is pure and does not render templates', function () {
    $section = new Section(
        name: 'main',
        messages: new Messages(
            new Message(role: 'user', content: 'Hi {{ name }}')
        )
    );

    $arr = $section->toArray();
    expect($arr[0]['content'])->toBe('Hi {{ name }}');
});

