<?php

declare(strict_types=1);

use Cognesy\Xprompt\Prompt;
use Cognesy\Xprompt\Tests\Fixtures\Prompts\ComposedPrompt;
use Cognesy\Xprompt\Tests\Fixtures\Prompts\HeaderBlock;
use Cognesy\Xprompt\Tests\Fixtures\Prompts\GreetingPrompt;

it('composes multiple prompts into a single string', function () {
    $text = ComposedPrompt::with(name: 'Alice', place: 'Wonderland')->render();
    expect($text)->toContain('# Document');
    expect($text)->toContain('Hello, Alice! Welcome to Wonderland.');
});

it('skips null elements in composition (conditional)', function () {
    $text = ComposedPrompt::with(name: 'Alice', place: 'here', include_footer: false)->render();
    expect($text)->not->toContain('End of document');
});

it('includes conditional elements when flag is set', function () {
    $text = ComposedPrompt::with(name: 'Alice', place: 'here', include_footer: true)->render();
    expect($text)->toContain('End of document');
});

it('composes inline and template-backed prompts together', function () {
    $prompt = new class extends Prompt {
        public function body(mixed ...$ctx): array {
            return [
                'Preamble text.',
                GreetingPrompt::make(),
                'Closing text.',
            ];
        }
    };
    $text = $prompt->render(name: 'Bob', place: 'Earth');
    expect($text)->toContain('Preamble text.');
    expect($text)->toContain('Hello, Bob! Welcome to Earth.');
    expect($text)->toContain('Closing text.');
});

it('handles deeply nested composition', function () {
    $inner = new class extends Prompt {
        public function body(mixed ...$ctx): string|array|null {
            return "inner:{$ctx['v']}";
        }
    };
    $middle = new class extends Prompt {
        public static ?Prompt $innerInstance = null;
        public function body(mixed ...$ctx): array {
            return ['middle', static::$innerInstance];
        }
    };
    $middle::$innerInstance = $inner;

    $outer = new class extends Prompt {
        public static ?Prompt $middleInstance = null;
        public function body(mixed ...$ctx): array {
            return ['outer', static::$middleInstance];
        }
    };
    $outer::$middleInstance = $middle;

    $text = $outer->render(v: 'deep');
    expect($text)->toContain('outer');
    expect($text)->toContain('middle');
    expect($text)->toContain('inner:deep');
});
