<?php

use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

describe('DriverCapabilities', function () {

    describe('output mode support', function () {

        it('supports all modes when outputModes is empty', function () {
            $caps = new DriverCapabilities(outputModes: []);

            expect($caps->supportsOutputMode(OutputMode::Tools))->toBeTrue();
            expect($caps->supportsOutputMode(OutputMode::JsonSchema))->toBeTrue();
            expect($caps->supportsOutputMode(OutputMode::Json))->toBeTrue();
            expect($caps->supportsOutputMode(OutputMode::MdJson))->toBeTrue();
            expect($caps->supportsOutputMode(OutputMode::Text))->toBeTrue();
            expect($caps->supportsOutputMode(OutputMode::Unrestricted))->toBeTrue();
        });

        it('restricts to specified output modes', function () {
            $caps = new DriverCapabilities(outputModes: [OutputMode::Tools, OutputMode::MdJson]);

            expect($caps->supportsOutputMode(OutputMode::Tools))->toBeTrue();
            expect($caps->supportsOutputMode(OutputMode::MdJson))->toBeTrue();
            expect($caps->supportsOutputMode(OutputMode::JsonSchema))->toBeFalse();
            expect($caps->supportsOutputMode(OutputMode::Json))->toBeFalse();
            expect($caps->supportsOutputMode(OutputMode::Text))->toBeFalse();
        });

        it('returns output modes via getter', function () {
            $modes = [OutputMode::Tools, OutputMode::Json];
            $caps = new DriverCapabilities(outputModes: $modes);

            expect($caps->getOutputModes())->toBe($modes);
        });

        it('returns empty array when all modes supported', function () {
            $caps = new DriverCapabilities();

            expect($caps->getOutputModes())->toBe([]);
        });

    });

    describe('streaming support', function () {

        it('reports streaming as supported by default', function () {
            $caps = new DriverCapabilities();

            expect($caps->supportsStreaming())->toBeTrue();
        });

        it('reports streaming as disabled when set', function () {
            $caps = new DriverCapabilities(streaming: false);

            expect($caps->supportsStreaming())->toBeFalse();
        });

    });

    describe('tool calling support', function () {

        it('reports tool calling as supported by default', function () {
            $caps = new DriverCapabilities();

            expect($caps->supportsToolCalling())->toBeTrue();
        });

        it('reports tool calling as disabled when set', function () {
            $caps = new DriverCapabilities(toolCalling: false);

            expect($caps->supportsToolCalling())->toBeFalse();
        });

    });

    describe('JSON schema support', function () {

        it('reports JSON schema as supported by default', function () {
            $caps = new DriverCapabilities();

            expect($caps->supportsJsonSchema())->toBeTrue();
        });

        it('reports JSON schema as disabled when set', function () {
            $caps = new DriverCapabilities(jsonSchema: false);

            expect($caps->supportsJsonSchema())->toBeFalse();
        });

    });

    describe('response format with tools support', function () {

        it('reports response format with tools as supported by default', function () {
            $caps = new DriverCapabilities();

            expect($caps->supportsResponseFormatWithTools())->toBeTrue();
        });

        it('reports response format with tools as disabled when set', function () {
            $caps = new DriverCapabilities(responseFormatWithTools: false);

            expect($caps->supportsResponseFormatWithTools())->toBeFalse();
        });

    });

    describe('combined supports() method', function () {

        it('returns true when mode and streaming both supported', function () {
            $caps = new DriverCapabilities(
                outputModes: [OutputMode::Tools, OutputMode::Json],
                streaming: true
            );

            expect($caps->supports(OutputMode::Tools, streaming: true))->toBeTrue();
            expect($caps->supports(OutputMode::Tools, streaming: false))->toBeTrue();
            expect($caps->supports(OutputMode::Json, streaming: true))->toBeTrue();
        });

        it('returns false when mode not supported', function () {
            $caps = new DriverCapabilities(
                outputModes: [OutputMode::Tools],
                streaming: true
            );

            expect($caps->supports(OutputMode::JsonSchema, streaming: true))->toBeFalse();
            expect($caps->supports(OutputMode::JsonSchema, streaming: false))->toBeFalse();
        });

        it('returns false when streaming requested but not supported', function () {
            $caps = new DriverCapabilities(
                outputModes: [OutputMode::Tools],
                streaming: false
            );

            expect($caps->supports(OutputMode::Tools, streaming: true))->toBeFalse();
            expect($caps->supports(OutputMode::Tools, streaming: false))->toBeTrue();
        });

        it('allows non-streaming when streaming is disabled', function () {
            $caps = new DriverCapabilities(streaming: false);

            expect($caps->supports(OutputMode::Tools, streaming: false))->toBeTrue();
        });

    });

    describe('immutability', function () {

        it('is readonly and cannot be modified', function () {
            $caps = new DriverCapabilities(
                outputModes: [OutputMode::Tools],
                streaming: true,
                toolCalling: true,
                jsonSchema: true,
                responseFormatWithTools: true
            );

            // Verify all properties are accessible but readonly
            expect($caps->outputModes)->toBe([OutputMode::Tools]);
            expect($caps->streaming)->toBeTrue();
            expect($caps->toolCalling)->toBeTrue();
            expect($caps->jsonSchema)->toBeTrue();
            expect($caps->responseFormatWithTools)->toBeTrue();
        });

    });

});
