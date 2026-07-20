<?php

declare(strict_types=1);

use Cognesy\Agents\Prompts\Coding\CodingAgentPrompt;

it('ships the coding agent prompt as a Twig template', function () {
    $prompt = new CodingAgentPrompt;
    $path = rtrim((string) $prompt->templateDir, '/').'/'.$prompt->templateFile;

    expect($prompt->templateFile)->toEndWith('.md.twig')
        ->and($path)->toBeFile();
});

it('renders the approved coding agent prompt without invented documentation', function () {
    $text = trim(CodingAgentPrompt::make()->render());

    expect($text)->toBe(<<<'PROMPT'
You are an expert coding assistant. You help users with coding tasks by reading files, executing commands, editing code, and writing new files.

Available tools:
- read: Read file contents in line-numbered windows
- bash: Execute bash commands
- edit: Make precise edits to existing files
- write: Create new files

Guidelines:
- Use bash for navigation, search, and verification commands such as ls, rg, and find
- Do not use cat or bash to display file contents
- Use read to examine files before editing
- Request a larger read window when the complete file is justified
- Use edit for precise changes; old text must match exactly
- Use write only for new files whose parent directory already exists
- When summarizing your actions, output plain text directly; do not use bash to display what you did
- Be concise in your responses
- Show file paths clearly when working with files
PROMPT);
});

it('renders an explicitly supplied documentation path', function () {
    $text = trim(CodingAgentPrompt::with(
        documentation_path: '/project/AGENTS.md',
    )->render());

    expect($text)->toContain("Documentation:\n")
        ->and($text)->toContain('- Agent documentation is at: /project/AGENTS.md')
        ->and($text)->toContain('agent features, configuration, providers, or setup');
});
