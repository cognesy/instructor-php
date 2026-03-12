<?php

declare(strict_types=1);

use Cognesy\Xprompt\Prompt;
use Cognesy\Xprompt\PromptRegistry;
use Cognesy\Xprompt\Attributes\AsPrompt;

// -- Fixture classes (defined at file scope) ----------------------------------

#[AsPrompt('test.analyze')]
class RegistryTestAnalyze extends Prompt {
    public function body(mixed ...$ctx): string|array|null {
        return 'analyze';
    }
}

#[AsPrompt('test.analyze')]
class RegistryTestAnalyzeCoT extends RegistryTestAnalyze {
    public function body(mixed ...$ctx): string|array|null {
        return 'analyze-cot';
    }
}

class RegistryTestBlock extends Prompt {
    public bool $isBlock = true;
    public function body(mixed ...$ctx): string|array|null {
        return 'block';
    }
}

// -- Tests --------------------------------------------------------------------

it('registers and retrieves a prompt', function () {
    $registry = new PromptRegistry();
    $registry->register('test.analyze', RegistryTestAnalyze::class);
    $prompt = $registry->get('test.analyze');
    expect($prompt)->toBeInstanceOf(RegistryTestAnalyze::class);
    expect($prompt->render())->toBe('analyze');
});

it('registers variant under same name', function () {
    $registry = new PromptRegistry();
    $registry->register('test.analyze', RegistryTestAnalyze::class);
    $registry->register('test.analyze', RegistryTestAnalyzeCoT::class);
    // Default is still the first registered
    expect($registry->get('test.analyze'))->toBeInstanceOf(RegistryTestAnalyze::class);
});

it('applies override to return variant', function () {
    $registry = new PromptRegistry(overrides: [
        'test.analyze' => RegistryTestAnalyzeCoT::class,
    ]);
    $registry->register('test.analyze', RegistryTestAnalyze::class);
    $registry->register('test.analyze', RegistryTestAnalyzeCoT::class);
    $prompt = $registry->get('test.analyze');
    expect($prompt)->toBeInstanceOf(RegistryTestAnalyzeCoT::class);
    expect($prompt->render())->toBe('analyze-cot');
});

it('applies override by short class name', function () {
    $registry = new PromptRegistry(overrides: [
        'test.analyze' => 'RegistryTestAnalyzeCoT',
    ]);
    $registry->register('test.analyze', RegistryTestAnalyze::class);
    $registry->register('test.analyze', RegistryTestAnalyzeCoT::class);
    expect($registry->get('test.analyze'))->toBeInstanceOf(RegistryTestAnalyzeCoT::class);
});

it('throws on unknown prompt name', function () {
    $registry = new PromptRegistry();
    $registry->get('nonexistent');
})->throws(RuntimeException::class, "Unknown prompt: 'nonexistent'");

it('throws on override referencing unregistered variant', function () {
    $registry = new PromptRegistry(overrides: [
        'test.analyze' => 'NonexistentVariant',
    ]);
    $registry->register('test.analyze', RegistryTestAnalyze::class);
    $registry->get('test.analyze');
})->throws(RuntimeException::class);

it('rejects non-Prompt classes', function () {
    $registry = new PromptRegistry();
    $registry->register('bad', \stdClass::class);
})->throws(InvalidArgumentException::class);

it('has() returns true for registered prompts', function () {
    $registry = new PromptRegistry();
    $registry->register('test.analyze', RegistryTestAnalyze::class);
    expect($registry->has('test.analyze'))->toBeTrue();
    expect($registry->has('nonexistent'))->toBeFalse();
});

it('names() excludes blocks by default', function () {
    $registry = new PromptRegistry();
    $registry->register('test.analyze', RegistryTestAnalyze::class);
    $registry->register('test.block', RegistryTestBlock::class);
    $names = $registry->names();
    expect($names)->toContain('test.analyze');
    expect($names)->not->toContain('test.block');
});

it('names(includeBlocks: true) includes blocks', function () {
    $registry = new PromptRegistry();
    $registry->register('test.analyze', RegistryTestAnalyze::class);
    $registry->register('test.block', RegistryTestBlock::class);
    $names = $registry->names(includeBlocks: true);
    expect($names)->toContain('test.analyze');
    expect($names)->toContain('test.block');
});

it('all() iterates name-class pairs', function () {
    $registry = new PromptRegistry();
    $registry->register('test.analyze', RegistryTestAnalyze::class);
    $registry->register('test.block', RegistryTestBlock::class);
    $items = iterator_to_array($registry->all());
    expect($items)->toHaveKey('test.analyze');
    expect($items)->not->toHaveKey('test.block');
});

it('variants() lists registered variants', function () {
    $registry = new PromptRegistry();
    $registry->register('test.analyze', RegistryTestAnalyze::class);
    $registry->register('test.analyze', RegistryTestAnalyzeCoT::class);
    $variants = $registry->variants('test.analyze');
    expect($variants)->toHaveCount(2);
    expect(array_values($variants))->toContain(RegistryTestAnalyze::class);
    expect(array_values($variants))->toContain(RegistryTestAnalyzeCoT::class);
});

it('registerClass() uses AsPrompt attribute name', function () {
    $registry = new PromptRegistry();
    $registry->registerClass(RegistryTestAnalyze::class);
    expect($registry->has('test.analyze'))->toBeTrue();
});

it('registerClass() throws for class without name', function () {
    $registry = new PromptRegistry();
    $registry->registerClass(RegistryTestBlock::class);
})->throws(InvalidArgumentException::class);
