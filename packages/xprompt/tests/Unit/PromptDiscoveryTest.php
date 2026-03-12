<?php

declare(strict_types=1);

use Cognesy\Xprompt\Discovery\PromptDiscovery;
use Cognesy\Xprompt\Attributes\AsPrompt;
use Cognesy\Xprompt\Prompt;
use Cognesy\Xprompt\PromptRegistry;

// -- Fixture classes ----------------------------------------------------------

#[AsPrompt('discovery.attributed')]
class DiscoveryAttributedPrompt extends Prompt {
    public function body(mixed ...$ctx): string|array|null { return 'attributed'; }
}

class DiscoveryPropertyPrompt extends Prompt {
    public string $promptName = 'discovery.property';
    public function body(mixed ...$ctx): string|array|null { return 'property'; }
}

class DiscoveryPlainPrompt extends Prompt {
    public function body(mixed ...$ctx): string|array|null { return 'plain'; }
}

// -- Name derivation tests ----------------------------------------------------

it('derives snake_case name from simple class name', function () {
    $name = PromptDiscovery::deriveNameFromClass('AnalyzeDocument', []);
    expect($name)->toBe('analyze_document');
});

it('derives dotted name from namespaced class', function () {
    $name = PromptDiscovery::deriveNameFromClass('App\\Prompts\\Reviewer\\AnalyzeDocument', ['App\\Prompts']);
    expect($name)->toBe('reviewer.analyze_document');
});

it('strips matching namespace prefix', function () {
    $name = PromptDiscovery::deriveNameFromClass('My\\App\\Prompts\\Persona', ['My\\App\\Prompts']);
    expect($name)->toBe('persona');
});

it('handles deeply nested namespaces', function () {
    $name = PromptDiscovery::deriveNameFromClass(
        'App\\Prompts\\Review\\Scoring\\DetailedRubric',
        ['App\\Prompts'],
    );
    expect($name)->toBe('review.scoring.detailed_rubric');
});

it('handles class without matching namespace', function () {
    $name = PromptDiscovery::deriveNameFromClass('Foo\\Bar\\Baz', ['Other\\Namespace']);
    expect($name)->toBe('foo.bar.baz');
});

it('converts PascalCase to snake_case correctly', function () {
    expect(PromptDiscovery::deriveNameFromClass('MyHTTPClient', []))
        ->toBe('my_http_client');
    expect(PromptDiscovery::deriveNameFromClass('XMLParser', []))
        ->toBe('xml_parser');
    expect(PromptDiscovery::deriveNameFromClass('SimpleTest', []))
        ->toBe('simple_test');
});

// -- Name resolution priority tests -------------------------------------------

it('resolveName prefers AsPrompt attribute', function () {
    $name = PromptDiscovery::resolveName(DiscoveryAttributedPrompt::class);
    expect($name)->toBe('discovery.attributed');
});

it('resolveName falls back to promptName property', function () {
    $name = PromptDiscovery::resolveName(DiscoveryPropertyPrompt::class);
    expect($name)->toBe('discovery.property');
});

it('resolveName falls back to FQCN convention', function () {
    $name = PromptDiscovery::resolveName(DiscoveryPlainPrompt::class);
    expect($name)->toBe('discovery_plain_prompt');
});

// -- Integration with registry ------------------------------------------------

it('registers discovered class with resolved name', function () {
    $registry = new PromptRegistry();
    $name = PromptDiscovery::resolveName(DiscoveryAttributedPrompt::class);
    $registry->register($name, DiscoveryAttributedPrompt::class);
    expect($registry->has('discovery.attributed'))->toBeTrue();
    expect($registry->get('discovery.attributed')->render())->toBe('attributed');
});
