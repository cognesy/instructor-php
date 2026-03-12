<?php

declare(strict_types=1);

use Cognesy\Xprompt\Prompt;

// -- Test fixtures (anonymous classes) ----------------------------------------

function makeInlinePrompt(string $text = 'hello'): Prompt {
    return new class($text) extends Prompt {
        public function __construct(private string $text) {}
        public function body(mixed ...$ctx): string|array|null {
            return $this->text;
        }
    };
}

function makeCtxPrompt(): Prompt {
    return new class extends Prompt {
        public function body(mixed ...$ctx): string|array|null {
            return "hello {$ctx['name']}";
        }
    };
}

function makeNullPrompt(): Prompt {
    return new class extends Prompt {
        public function body(mixed ...$ctx): string|array|null {
            return null;
        }
    };
}

// -- Static constructors ------------------------------------------------------

it('creates instance via make()', function () {
    $cls = get_class(makeCtxPrompt());
    expect($cls::make())->toBeInstanceOf(Prompt::class);
});

it('creates instance with context via with()', function () {
    $prompt = get_class(makeCtxPrompt())::with(name: 'world');
    expect((string) $prompt)->toBe('hello world');
});

// -- Rendering ----------------------------------------------------------------

it('renders inline body', function () {
    $prompt = makeInlinePrompt('test');
    expect($prompt->render())->toBe('test');
});

it('renders with context passed to render()', function () {
    $prompt = makeCtxPrompt();
    expect($prompt->render(name: 'alice'))->toBe('hello alice');
});

it('merges bound context with render context', function () {
    // Create a prompt that uses two ctx keys
    $prompt = new class extends Prompt {
        public function body(mixed ...$ctx): string|array|null {
            return "{$ctx['greeting']} {$ctx['name']}";
        }
    };
    $bound = $prompt::with(greeting: 'hi');
    expect($bound->render(name: 'bob'))->toBe('hi bob');
});

it('render context overrides bound context', function () {
    $prompt = makeCtxPrompt();
    $bound = $prompt::with(name: 'alice');
    expect($bound->render(name: 'bob'))->toBe('hello bob');
});

it('implements Stringable', function () {
    $prompt = makeInlinePrompt('stringable');
    expect($prompt)->toBeInstanceOf(Stringable::class);
    expect((string) $prompt)->toBe('stringable');
});

it('__toString matches render with no args', function () {
    $prompt = makeInlinePrompt('same');
    expect((string) $prompt)->toBe($prompt->render());
});

it('returns empty string for null body', function () {
    $prompt = makeNullPrompt();
    expect($prompt->render())->toBe('');
});

// -- Default body delegates to template when templateFile is set --------------

it('returns null from body when no templateFile', function () {
    // Use a prompt with default body() that has no templateFile
    $prompt = new class extends Prompt {
        // Don't override body — use parent default
    };
    expect($prompt->render())->toBe('');
});

// -- Properties ---------------------------------------------------------------

it('has default property values', function () {
    $prompt = makeInlinePrompt();
    expect($prompt->model)->toBe('');
    expect($prompt->isBlock)->toBeFalse();
    expect($prompt->templateFile)->toBe('');
    expect($prompt->templateDir)->toBeNull();
    expect($prompt->blocks)->toBe([]);
});

// -- Config -------------------------------------------------------------------

it('withConfig returns a clone with config applied', function () {
    $prompt = makeInlinePrompt('hello');
    $config = \Cognesy\Template\Config\TemplateEngineConfig::twig('/tmp/prompts');
    $cloned = $prompt->withConfig($config);
    expect($cloned)->not->toBe($prompt);
    expect($cloned->render())->toBe('hello');
});
