<?php

use Cognesy\Instructor\Extras\Prompt\Prompt;
use Cognesy\Instructor\Extras\Prompt\Data\PromptEngineConfig;
use Cognesy\Instructor\Utils\Messages\Messages;

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
    $prompt = Prompt::using('blade')->withTemplate('hello');
    expect($prompt->template())->toContain('Hello');
});

it('can render the template - Blade', function () {
    $prompt = Prompt::using('blade')
        ->withTemplateContent('Hello, {{ $name }}!')
        ->withValues(['name' => 'World']);
    expect($prompt->toText())->toBe('Hello, World!');
});

it('can find template variables - Blade', function () {
    $prompt = Prompt::using('blade')
        ->withTemplateContent('Hello, {{ $name }}!')
        ->withValues(['name' => 'World']);
    $variables = $prompt->variables();
    expect($variables)->toContain('name');
});

it('can convert rendered text to messages', function () {
    $prompt = Prompt::using('blade')
        ->withTemplateContent('Hello, {{ $name }}!')
        ->withValues(['name' => 'World']);
    $messages = $prompt->toMessages();
    expect($messages)->toBeInstanceOf(Messages::class);
});

it('can load a template by name - Twig', function () {
    $prompt = Prompt::using('twig')->withTemplate('hello');
    expect($prompt->template())->toContain('Hello');
});

it('can render the template - Twig', function () {
    $prompt = Prompt::using('twig')
        ->withTemplateContent('Hello, {{ name }}!')
        ->withValues(['name' => 'World']);
    expect($prompt->toText())->toBe('Hello, World!');
});

it('can find template variables - Twig', function () {
    $prompt = Prompt::using('twig')
        ->withTemplateContent('Hello, {{ name }}!')
        ->withValues(['name' => 'World']);
    $variables = $prompt->variables();
    expect($variables)->toContain('name');
});

it('can render the template using short syntax', function () {
    $prompt = Prompt::text(name: 'hello', variables: ['name' => 'World']);
    expect($prompt)->toBe('Hello, World!');
});
