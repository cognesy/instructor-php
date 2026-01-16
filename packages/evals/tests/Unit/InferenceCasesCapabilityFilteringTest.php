<?php

use Cognesy\Evals\Executors\Data\InferenceCases;
use Cognesy\Evals\Executors\Data\InferenceCaseParams;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

describe('InferenceCases capability filtering', function () {

    it('filters out JsonSchema mode for Anthropic', function () {
        // Use preserve_keys: false to avoid key collision issues with generators
        $cases = iterator_to_array(InferenceCases::only(
            presets: ['anthropic'],
            modes: [OutputMode::JsonSchema, OutputMode::Tools, OutputMode::Text],
        ), false);

        $modes = array_map(fn($case) => $case->mode, $cases);

        // JsonSchema should be filtered out
        expect($modes)->not->toContain(OutputMode::JsonSchema);
        // Tools and Text should remain
        expect($modes)->toContain(OutputMode::Tools);
        expect($modes)->toContain(OutputMode::Text);
    });

    it('filters out Json mode for Anthropic', function () {
        $cases = iterator_to_array(InferenceCases::only(
            presets: ['anthropic'],
            modes: [OutputMode::Json, OutputMode::MdJson],
        ), false);

        $modes = array_map(fn($case) => $case->mode, $cases);

        expect($modes)->not->toContain(OutputMode::Json);
        expect($modes)->toContain(OutputMode::MdJson);
    });

    it('filters out JsonSchema mode for A21', function () {
        $cases = iterator_to_array(InferenceCases::only(
            presets: ['a21'],
            modes: [OutputMode::JsonSchema, OutputMode::Json],
        ), false);

        $modes = array_map(fn($case) => $case->mode, $cases);

        expect($modes)->not->toContain(OutputMode::JsonSchema);
        expect($modes)->toContain(OutputMode::Json);
    });

    it('filters out JsonSchema mode for Gemini OAI', function () {
        $cases = iterator_to_array(InferenceCases::only(
            presets: ['gemini-oai'],
            modes: [OutputMode::JsonSchema, OutputMode::Json],
        ), false);

        $modes = array_map(fn($case) => $case->mode, $cases);

        expect($modes)->not->toContain(OutputMode::JsonSchema);
        expect($modes)->toContain(OutputMode::Json);
    });

    it('filters out JsonSchema mode for SambaNova', function () {
        $cases = iterator_to_array(InferenceCases::only(
            presets: ['sambanova'],
            modes: [OutputMode::JsonSchema, OutputMode::Json],
        ), false);

        $modes = array_map(fn($case) => $case->mode, $cases);

        expect($modes)->not->toContain(OutputMode::JsonSchema);
        expect($modes)->toContain(OutputMode::Json);
    });

    it('filters out Tools mode for Perplexity', function () {
        $cases = iterator_to_array(InferenceCases::only(
            presets: ['perplexity'],
            modes: [OutputMode::Tools, OutputMode::Json, OutputMode::Text],
        ), false);

        $modes = array_map(fn($case) => $case->mode, $cases);

        expect($modes)->not->toContain(OutputMode::Tools);
        expect($modes)->toContain(OutputMode::Json);
        expect($modes)->toContain(OutputMode::Text);
    });

    it('includes all modes for OpenAI (full capabilities)', function () {
        $cases = iterator_to_array(InferenceCases::only(
            presets: ['openai'],
            stream: [false], // Reduce combinations
        ), false);

        $modes = array_map(fn($case) => $case->mode, $cases);

        expect($modes)->toContain(OutputMode::Tools);
        expect($modes)->toContain(OutputMode::JsonSchema);
        expect($modes)->toContain(OutputMode::Json);
        expect($modes)->toContain(OutputMode::MdJson);
        expect($modes)->toContain(OutputMode::Text);
    });

    it('filters Tools mode for deepseek-r (reasoner) preset', function () {
        // 'deepseek-r' is the reasoner preset in the default config
        $cases = iterator_to_array(InferenceCases::only(
            presets: ['deepseek-r'],
            modes: [OutputMode::Tools, OutputMode::Text],
        ), false);

        $modes = array_map(fn($case) => $case->mode, $cases);

        // Tools should be filtered out for reasoner model
        expect($modes)->not->toContain(OutputMode::Tools);
        expect($modes)->toContain(OutputMode::Text);
    });

    it('includes Tools mode for deepseek (chat) preset', function () {
        // 'deepseek' is the chat preset in the default config
        $cases = iterator_to_array(InferenceCases::only(
            presets: ['deepseek'],
            modes: [OutputMode::Tools, OutputMode::Text],
        ), false);

        $modes = array_map(fn($case) => $case->mode, $cases);

        // Tools should be included for chat model
        expect($modes)->toContain(OutputMode::Tools);
        expect($modes)->toContain(OutputMode::Text);
    });

    it('can disable capability filtering', function () {
        $filteredCases = iterator_to_array(InferenceCases::only(
            presets: ['anthropic'],
            modes: [OutputMode::JsonSchema, OutputMode::Text],
            filterByCapabilities: true,
        ), false);

        $unfilteredCases = iterator_to_array(InferenceCases::only(
            presets: ['anthropic'],
            modes: [OutputMode::JsonSchema, OutputMode::Text],
            filterByCapabilities: false,
        ), false);

        // Unfiltered should have more cases (includes unsupported JsonSchema)
        expect(count($unfilteredCases))->toBeGreaterThan(count($filteredCases));

        $unfilteredModes = array_map(fn($case) => $case->mode, $unfilteredCases);
        expect($unfilteredModes)->toContain(OutputMode::JsonSchema);
    });

    it('generates InferenceCaseParams objects', function () {
        $cases = iterator_to_array(InferenceCases::only(
            presets: ['openai'],
            modes: [OutputMode::Text],
            stream: [false],
        ), false);

        expect($cases)->toHaveCount(1);
        expect($cases[0])->toBeInstanceOf(InferenceCaseParams::class);
        expect($cases[0]->preset)->toBe('openai');
        expect($cases[0]->mode)->toBe(OutputMode::Text);
        expect($cases[0]->isStreamed)->toBeFalse();
    });

});

describe('InferenceCases static methods', function () {

    it('all() returns generator with capability filtering by default', function () {
        $cases = InferenceCases::all();
        expect($cases)->toBeInstanceOf(Generator::class);

        // Consume first few cases to verify it works
        $count = 0;
        foreach ($cases as $case) {
            expect($case)->toBeInstanceOf(InferenceCaseParams::class);
            $count++;
            if ($count >= 5) break;
        }
        expect($count)->toBeGreaterThanOrEqual(1);
    });

    it('except() excludes specified presets', function () {
        $cases = iterator_to_array(InferenceCases::except(
            presets: ['anthropic', 'perplexity', 'deepseek', 'deepseek-r'],
            modes: [OutputMode::Text],
            stream: [false],
        ), false);

        $presets = array_map(fn($case) => $case->preset, $cases);
        expect($presets)->not->toContain('anthropic');
        expect($presets)->not->toContain('perplexity');
        expect($presets)->toContain('openai');
    });

    it('only() includes only specified presets', function () {
        $cases = iterator_to_array(InferenceCases::only(
            presets: ['openai'],
            modes: [OutputMode::Text],
            stream: [false],
        ), false);

        $presets = array_map(fn($case) => $case->preset, $cases);
        expect($presets)->toHaveCount(1);
        expect($presets[0])->toBe('openai');
    });

});
