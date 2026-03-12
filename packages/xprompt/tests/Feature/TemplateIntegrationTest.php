<?php

declare(strict_types=1);

use Cognesy\Xprompt\Tests\Fixtures\Prompts\GreetingPrompt;
use Cognesy\Xprompt\Tests\Fixtures\Prompts\MetadataPrompt;
use Cognesy\Xprompt\Tests\Fixtures\Prompts\BlocksPrompt;

it('renders a template-backed prompt via packages/templates', function () {
    $text = GreetingPrompt::with(name: 'Alice', place: 'Wonderland')->render();
    expect($text)->toBe('Hello, Alice! Welcome to Wonderland.');
});

it('reads front matter metadata from template', function () {
    $prompt = new MetadataPrompt();
    $meta = $prompt->meta();
    expect($meta['description'])->toBe('A test template with metadata');
    expect($meta['model'])->toBe('sonnet');
    expect($meta['version'])->toBe('v1');
});

it('extracts variable names from template', function () {
    $prompt = new MetadataPrompt();
    $vars = $prompt->variables();
    expect($vars)->toContain('topic');
});

it('renders template with front matter correctly', function () {
    $text = MetadataPrompt::with(topic: 'AI safety')->render();
    expect($text)->toContain('AI safety');
    expect($text)->not->toContain('---');
    expect($text)->not->toContain('description:');
});

it('renders blocks and injects them into template', function () {
    $text = BlocksPrompt::with(body_text: 'Main content here', title: 'Review')->render();
    expect($text)->toContain('# Review');
    expect($text)->toContain('Main content here');
    expect($text)->toContain('End of document.');
});

it('passes context to blocks during template rendering', function () {
    $text = BlocksPrompt::with(body_text: 'Content', title: 'Custom Title')->render();
    expect($text)->toContain('# Custom Title');
});

it('returns empty meta for non-template prompt', function () {
    $prompt = new class extends \Cognesy\Xprompt\Prompt {
        public function body(mixed ...$ctx): string|array|null {
            return 'inline';
        }
    };
    expect($prompt->meta())->toBe([]);
});

it('returns empty variables for non-template prompt', function () {
    $prompt = new class extends \Cognesy\Xprompt\Prompt {
        public function body(mixed ...$ctx): string|array|null {
            return 'inline';
        }
    };
    expect($prompt->variables())->toBe([]);
});
