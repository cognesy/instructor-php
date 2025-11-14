<?php declare(strict_types=1);

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Instructor\StructuredOutput;

class MockUser { public int $age; public string $name; }

it('threads HttpClient through StructuredOutput using OpenAI adapter', function () {
    $http = (new HttpClientBuilder())
        ->withMock(function ($mock) {
            $mock->on()
                ->post('https://api.openai.com/v1/chat/completions')
                ->withJsonSubset(['model' => 'gpt-4o-mini'])
                ->times(1)
                ->replyJson([
                    'choices' => [[ 'message' => ['content' => '{"name":"Lia","age":28}'] ]],
                    'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
                ]);
        })
        ->create();

    $user = (new StructuredOutput)
        ->withHttpClient($http)
        ->using('openai')
        ->with(
            messages: 'Extract user',
            responseModel: MockUser::class,
            model: 'gpt-4o-mini',
        )
        ->get();

    expect($user)->toBeInstanceOf(MockUser::class);
    expect($user->name)->toBe('Lia');
    expect($user->age)->toBe(28);
});

