<?php

use Cognesy\Instructor\Extras\Prompt\Enums\TemplateEngine;
use Cognesy\Instructor\Extras\Prompt\Prompt;
use Cognesy\Instructor\Extras\Prompt\Data\PromptEngineConfig;
use Cognesy\Instructor\Utils\Messages\Messages;
use Cognesy\Instructor\Utils\Messages\Script;

// RECOMMENDED, READING FRIENDLY SYNTAX

it('can use "using->get->with" syntax', function () {
    $prompt = Prompt::using('demo-twig')->get('hello')->with(['name' => 'World']);
    expect($prompt->toText())->toBe('Hello, World!');
    expect($prompt->toMessages()->toArray())->toBe([['role' => 'user', 'content' => 'Hello, World!']]);
});

it('can use short "make->with" syntax', function () {
    $prompt = Prompt::make('demo-twig:hello')->with(['name' => 'World']);
    expect($prompt->toText())->toBe('Hello, World!');
    expect($prompt->toMessages()->toArray())->toBe([['role' => 'user', 'content' => 'Hello, World!']]);
});

it('can render the template using short syntax', function () {
    $prompt = Prompt::text('demo-twig:hello', ['name' => 'World']);
    expect($prompt)->toBe('Hello, World!');
});

it('can render the template to messages using short syntax', function () {
    $messages = Prompt::messages('demo-twig:hello', ['name' => 'World']);
    expect($messages->toArray())->toBe([['role' => 'user', 'content' => 'Hello, World!']]);
});

// OTHER METHOD CHECKS

it('can be instantiated with default settings', function () {
    $prompt = new Prompt();
    expect($prompt)->toBeInstanceOf(Prompt::class);
});

it('can set a custom config', function () {
    $config = new PromptEngineConfig();
    $prompt = new Prompt();
    $prompt->withConfig($config);
    expect($prompt->config())->toBe($config);
});

it('can set template content directly', function () {
    $content = 'template content';
    $prompt = new Prompt();
    $prompt->withTemplateContent($content);
    expect($prompt->template())->toBe($content);
});

it('can set parameters for rendering', function () {
    $params = ['key' => 'value'];
    $prompt = new Prompt();
    $prompt->withValues($params);
    expect($prompt->params())->toBe($params);
});

it('can load a template by name - Blade', function () {
    $prompt = Prompt::using('demo-blade')->withTemplate('hello');
    expect($prompt->template())->toContain('Hello');
});

it('can render string template - Blade', function () {
    $prompt = Prompt::using('demo-blade')
        ->withTemplateContent('Hello, {{ $name }}!')
        ->withValues(['name' => 'World']);
    expect($prompt->toText())->toBe('Hello, World!');
});

it('can find template variables - Blade', function () {
    $prompt = Prompt::using('demo-blade')
        ->withTemplateContent('Hello, {{ $name }}!')
        ->withValues(['name' => 'World']);
    $variables = $prompt->variables();
    expect($variables)->toContain('name');
});

it('can convert template with chat markup to messages', function () {
    $prompt = Prompt::blade()
        ->withTemplateContent('<chat><message role="system">You are helpful assistant.</message><message role="user">Hello, {{ $name }}</message></chat>')
        ->withValues(['name' => 'assistant']);
    $messages = $prompt->toMessages();
    expect($messages)->toBeInstanceOf(Messages::class);
    expect($messages->toString())->toContain('Hello, assistant');
    expect($messages->toArray())->toHaveCount(2);
});

it('can convert template with chat markup to script', function () {
    $prompt = Prompt::blade()
        ->withTemplateContent('<chat><section name="system"/><message role="system">You are helpful assistant.</message><section name="messages"/><message role="user">Hello, {{ $name }}</message></chat>')
        ->withValues(['name' => 'assistant']);
    $script = $prompt->toScript();
    expect($script)->toBeInstanceOf(Script::class)
        ->and($script->toString())->toContain('Hello, assistant')
        ->and($script->hasSection('system'))->toBeTrue()
        ->and($script->hasSection('messages'))->toBeTrue();
});

it('can load a template by name - Twig', function () {
    $prompt = Prompt::using('demo-twig')->withTemplate('hello');
    expect($prompt->template())->toContain('Hello');
});

it('can render string template - Twig', function () {
    $prompt = (new Prompt(library: 'demo-twig'))
        ->withTemplateContent('Hello, {{ name }}!')
        ->withValues(['name' => 'World']);
    expect($prompt->toText())->toBe('Hello, World!');
});

it('can find template variables - Twig', function () {
    $prompt = Prompt::using('demo-twig')
        ->withTemplateContent('Hello, {{ name }}!')
        ->withValues(['name' => 'World']);
    $variables = $prompt->variables();
    expect($variables)->toContain('name');
});

it('can create prompt from "in memory" config', function () {
    $prompt = (new Prompt)
        ->withConfig(new PromptEngineConfig(
            templateEngine: TemplateEngine::Blade,
            resourcePath: '',
            cachePath: '/tmp/any',
            extension: 'blade.php',
            metadata: [],
        ))
        ->withTemplateContent('Hello, {{ $name }}!')
        ->withValues(['name' => 'World']);
    $messages = $prompt->toMessages();
    expect($messages)->toBeInstanceOf(Messages::class);
});

it('can use DSN to load a template', function () {
    $prompt = Prompt::fromDsn('demo-blade:hello')->with(['name' => 'World']);
    expect($prompt->toText())->toBe('Hello, World!');
});
