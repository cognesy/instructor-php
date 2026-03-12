<?php

declare(strict_types=1);

use Cognesy\Xprompt\Prompt;
use Cognesy\Xprompt\PromptRegistry;
use Cognesy\Xprompt\Attributes\AsPrompt;

// -- Fixture classes ----------------------------------------------------------

#[AsPrompt('variant.system')]
class VariantDefault extends Prompt {
    public string $model = 'sonnet';
    public function body(mixed ...$ctx): string|array|null {
        return "default: {$ctx['topic']}";
    }
}

#[AsPrompt('variant.system')]
class VariantCoT extends VariantDefault {
    public function body(mixed ...$ctx): string|array|null {
        return "cot: think step by step about {$ctx['topic']}";
    }
}

#[AsPrompt('variant.system')]
class VariantConcise extends VariantDefault {
    public string $model = 'haiku';
    public function body(mixed ...$ctx): string|array|null {
        return "concise: {$ctx['topic']}";
    }
}

// -- Tests --------------------------------------------------------------------

it('default registry returns the first registered class', function () {
    $registry = new PromptRegistry();
    $registry->register('variant.system', VariantDefault::class);
    $registry->register('variant.system', VariantCoT::class);
    $registry->register('variant.system', VariantConcise::class);

    $prompt = $registry->get('variant.system');
    expect($prompt)->toBeInstanceOf(VariantDefault::class);
    expect($prompt->render(topic: 'AI'))->toBe('default: AI');
});

it('override swaps to CoT variant', function () {
    $registry = new PromptRegistry(overrides: [
        'variant.system' => VariantCoT::class,
    ]);
    $registry->register('variant.system', VariantDefault::class);
    $registry->register('variant.system', VariantCoT::class);
    $registry->register('variant.system', VariantConcise::class);

    $prompt = $registry->get('variant.system');
    expect($prompt)->toBeInstanceOf(VariantCoT::class);
    expect($prompt->render(topic: 'AI'))->toBe('cot: think step by step about AI');
});

it('override swaps to Concise variant with different model', function () {
    $registry = new PromptRegistry(overrides: [
        'variant.system' => VariantConcise::class,
    ]);
    $registry->register('variant.system', VariantDefault::class);
    $registry->register('variant.system', VariantConcise::class);

    $prompt = $registry->get('variant.system');
    expect($prompt)->toBeInstanceOf(VariantConcise::class);
    expect($prompt->model)->toBe('haiku');
});

it('removing override reverts to default', function () {
    // First with override
    $registry = new PromptRegistry(overrides: [
        'variant.system' => VariantCoT::class,
    ]);
    $registry->register('variant.system', VariantDefault::class);
    $registry->register('variant.system', VariantCoT::class);
    expect($registry->get('variant.system'))->toBeInstanceOf(VariantCoT::class);

    // Without override
    $registry2 = new PromptRegistry(overrides: []);
    $registry2->register('variant.system', VariantDefault::class);
    $registry2->register('variant.system', VariantCoT::class);
    expect($registry2->get('variant.system'))->toBeInstanceOf(VariantDefault::class);
});

it('lists all variants for a name', function () {
    $registry = new PromptRegistry();
    $registry->register('variant.system', VariantDefault::class);
    $registry->register('variant.system', VariantCoT::class);
    $registry->register('variant.system', VariantConcise::class);

    $variants = $registry->variants('variant.system');
    expect($variants)->toHaveCount(3);
    expect(array_values($variants))->toContain(VariantDefault::class);
    expect(array_values($variants))->toContain(VariantCoT::class);
    expect(array_values($variants))->toContain(VariantConcise::class);
});

it('variant inherits parent properties', function () {
    $default = new VariantDefault();
    $cot = new VariantCoT();
    // CoT inherits model from Default
    expect($cot->model)->toBe('sonnet');
    // Concise overrides model
    $concise = new VariantConcise();
    expect($concise->model)->toBe('haiku');
});
