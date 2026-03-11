<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Skills;

use Cognesy\Agents\Capability\Skills\SkillPreprocessor;

describe('SkillPreprocessor', function () {

    it('detects commands in body', function () {
        $preprocessor = new SkillPreprocessor();

        expect($preprocessor->hasCommands('Hello !`echo hi` world'))->toBeTrue();
        expect($preprocessor->hasCommands('No commands here'))->toBeFalse();
    });

    it('replaces command with output', function () {
        $preprocessor = new SkillPreprocessor();

        $result = $preprocessor->process('Version: !`echo 1.0.0`');

        expect($result)->toBe('Version: 1.0.0');
    });

    it('handles multiple commands', function () {
        $preprocessor = new SkillPreprocessor();

        $result = $preprocessor->process('A=!`echo hello` B=!`echo world`');

        expect($result)->toBe('A=hello B=world');
    });

    it('returns error for failed commands', function () {
        $preprocessor = new SkillPreprocessor();

        $result = $preprocessor->process('Result: !`exit 1`');

        expect($result)->toContain('[error:');
    });

    it('returns error for timed out commands', function () {
        $preprocessor = new SkillPreprocessor(timeoutSeconds: 1);

        $result = $preprocessor->process('Result: !`sleep 10`');

        expect($result)->toContain('timed out');
    });

    it('passes through body without commands unchanged', function () {
        $preprocessor = new SkillPreprocessor();

        $body = 'Plain text with no commands';
        $result = $preprocessor->process($body);

        expect($result)->toBe($body);
    });

    it('uses working directory when set', function () {
        $preprocessor = new SkillPreprocessor(workingDirectory: '/tmp');

        $result = $preprocessor->process('Dir: !`pwd`');

        expect($result)->toContain('/tmp');
    });

    it('handles command with spaces and pipes', function () {
        $preprocessor = new SkillPreprocessor();

        $result = $preprocessor->process('Count: !`echo "a b c" | wc -w`');

        expect(trim($result))->toContain('3');
    });
});
