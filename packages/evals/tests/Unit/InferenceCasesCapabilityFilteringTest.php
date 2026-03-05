<?php

use Cognesy\Evals\Executors\Data\InferenceCases;
use Cognesy\Evals\Executors\Data\InferenceCaseParams;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

describe('InferenceCases capability filtering', function () {

    it('filters out JsonSchema mode for Anthropic', function () {
        // Use preserve_keys: false to avoid key collision issues with generators
        $cases = iterator_to_array(InferenceCases::only(
            connections: ['anthropic'],
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
            connections: ['anthropic'],
            modes: [OutputMode::Json, OutputMode::MdJson],
        ), false);

        $modes = array_map(fn($case) => $case->mode, $cases);

        expect($modes)->not->toContain(OutputMode::Json);
        expect($modes)->toContain(OutputMode::MdJson);
    });

    it('filters out JsonSchema mode for A21', function () {
        $cases = iterator_to_array(InferenceCases::only(
            connections: ['a21'],
            modes: [OutputMode::JsonSchema, OutputMode::Json],
        ), false);

        $modes = array_map(fn($case) => $case->mode, $cases);

        expect($modes)->not->toContain(OutputMode::JsonSchema);
        expect($modes)->toContain(OutputMode::Json);
    });

    it('filters out JsonSchema mode for Gemini OAI', function () {
        $cases = iterator_to_array(InferenceCases::only(
            connections: ['gemini-oai'],
            modes: [OutputMode::JsonSchema, OutputMode::Json],
        ), false);

        $modes = array_map(fn($case) => $case->mode, $cases);

        expect($modes)->not->toContain(OutputMode::JsonSchema);
        expect($modes)->toContain(OutputMode::Json);
    });

    it('filters out JsonSchema mode for SambaNova', function () {
        $cases = iterator_to_array(InferenceCases::only(
            connections: ['sambanova'],
            modes: [OutputMode::JsonSchema, OutputMode::Json],
        ), false);

        $modes = array_map(fn($case) => $case->mode, $cases);

        expect($modes)->not->toContain(OutputMode::JsonSchema);
        expect($modes)->toContain(OutputMode::Json);
    });

    it('filters out Tools mode for Perplexity', function () {
        $cases = iterator_to_array(InferenceCases::only(
            connections: ['perplexity'],
            modes: [OutputMode::Tools, OutputMode::Json, OutputMode::Text],
        ), false);

        $modes = array_map(fn($case) => $case->mode, $cases);

        expect($modes)->not->toContain(OutputMode::Tools);
        expect($modes)->toContain(OutputMode::Json);
        expect($modes)->toContain(OutputMode::Text);
    });

    it('includes all modes for OpenAI (full capabilities)', function () {
        $cases = iterator_to_array(InferenceCases::only(
            connections: ['openai'],
            stream: [false], // Reduce combinations
        ), false);

        $modes = array_map(fn($case) => $case->mode, $cases);

        expect($modes)->toContain(OutputMode::Tools);
        expect($modes)->toContain(OutputMode::JsonSchema);
        expect($modes)->toContain(OutputMode::Json);
        expect($modes)->toContain(OutputMode::MdJson);
        expect($modes)->toContain(OutputMode::Text);
    });

    it('filters Tools mode for deepseek-r (reasoner) connection', function () {
        // 'deepseek-r' is the reasoner connection in the default set
        $cases = iterator_to_array(InferenceCases::only(
            connections: ['deepseek-r'],
            modes: [OutputMode::Tools, OutputMode::Text],
        ), false);

        $modes = array_map(fn($case) => $case->mode, $cases);

        // Tools should be filtered out for reasoner model
        expect($modes)->not->toContain(OutputMode::Tools);
        expect($modes)->toContain(OutputMode::Text);
    });

    it('includes Tools mode for deepseek (chat) connection', function () {
        // 'deepseek' is the chat connection in the default set
        $cases = iterator_to_array(InferenceCases::only(
            connections: ['deepseek'],
            modes: [OutputMode::Tools, OutputMode::Text],
        ), false);

        $modes = array_map(fn($case) => $case->mode, $cases);

        // Tools should be included for chat model
        expect($modes)->toContain(OutputMode::Tools);
        expect($modes)->toContain(OutputMode::Text);
    });

    it('can disable capability filtering', function () {
        $filteredCases = iterator_to_array(InferenceCases::only(
            connections: ['anthropic'],
            modes: [OutputMode::JsonSchema, OutputMode::Text],
            filterByCapabilities: true,
        ), false);

        $unfilteredCases = iterator_to_array(InferenceCases::only(
            connections: ['anthropic'],
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
            connections: ['openai'],
            modes: [OutputMode::Text],
            stream: [false],
        ), false);

        expect($cases)->toHaveCount(1);
        expect($cases[0])->toBeInstanceOf(InferenceCaseParams::class);
        expect($cases[0]->connection)->toBe('openai');
        expect($cases[0]->mode)->toBe(OutputMode::Text);
        expect($cases[0]->isStreamed)->toBeFalse();
        expect($cases[0]->llmConfig)->toBeInstanceOf(LLMConfig::class);
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

    it('except() excludes specified connections', function () {
        $cases = iterator_to_array(InferenceCases::except(
            connections: ['anthropic', 'perplexity', 'deepseek', 'deepseek-r'],
            modes: [OutputMode::Text],
            stream: [false],
        ), false);

        $connections = array_map(fn($case) => $case->connection, $cases);
        expect($connections)->not->toContain('anthropic');
        expect($connections)->not->toContain('perplexity');
        expect($connections)->toContain('openai');
    });

    it('only() includes only specified connections', function () {
        $cases = iterator_to_array(InferenceCases::only(
            connections: ['openai'],
            modes: [OutputMode::Text],
            stream: [false],
        ), false);

        $connections = array_map(fn($case) => $case->connection, $cases);
        expect($connections)->toHaveCount(1);
        expect($connections[0])->toBe('openai');
    });

});
