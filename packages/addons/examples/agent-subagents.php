<?php declare(strict_types=1);

/**
 * Example: Subagent Registry - Code-First Subagent Definition
 *
 * This example demonstrates how to define and use subagents with the SubagentRegistry.
 */

use Cognesy\Addons\AgentBuilder\AgentBuilder;
use Cognesy\Addons\AgentBuilder\Capabilities\Bash\UseBash;
use Cognesy\Addons\AgentBuilder\Capabilities\File\UseFileTools;
use Cognesy\Addons\AgentBuilder\Capabilities\Subagent\UseSubagents;
use Cognesy\Addons\AgentBuilder\Capabilities\Tasks\UseTaskPlanning;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\AgentTemplate\Registry\AgentRegistry;
use Cognesy\Addons\AgentTemplate\Registry\AgentSpec;
use Cognesy\Messages\Messages;

require_once __DIR__ . '/../../../vendor/autoload.php';

// Create subagent registry
$registry = new AgentRegistry();

// Register code reviewer subagent
$registry->register(new AgentSpec(
    name: 'code-reviewer',
    description: 'Expert code reviewer focusing on security and quality',
    systemPrompt: <<<'PROMPT'
You are a senior code reviewer with expertise in PHP development.

## Your Responsibilities

1. Review code for quality issues
2. Identify security vulnerabilities (SQL injection, XSS, etc.)
3. Check adherence to coding standards
4. Suggest specific improvements with examples

## Review Checklist

- [ ] Code follows SOLID principles
- [ ] No security vulnerabilities
- [ ] Proper error handling
- [ ] Clear naming and structure
- [ ] Adequate type safety
- [ ] No code duplication

## Output Format

Provide findings in order of severity:
1. **Critical**: Security issues, bugs
2. **High**: Design problems, major code smells
3. **Medium**: Code style, minor improvements
4. **Low**: Suggestions, optimizations
PROMPT,
    tools: ['read_file', 'search_files', 'list_dir'],
    model: 'inherit',  // Use same model as parent
));

// Register test generator subagent
$registry->register(new AgentSpec(
    name: 'test-generator',
    description: 'Generate comprehensive Pest tests for PHP code',
    systemPrompt: <<<'PROMPT'
You are a testing expert specializing in Pest PHP testing framework.

## Your Task

Generate comprehensive test suites that cover:
- Happy path scenarios
- Edge cases
- Error conditions
- Boundary values

## Test Structure

- Use descriptive test names
- Follow Arrange-Act-Assert pattern
- Use dataset providers for multiple cases
- Include both unit and integration tests

## Quality Standards

- Aim for 90%+ code coverage
- Test one thing per test
- Use meaningful assertions
- Mock external dependencies
PROMPT,
    tools: ['read_file', 'write_file', 'search_files'],
    model: 'anthropic',  // Use specific model
));

// Register API designer subagent
$registry->register(new AgentSpec(
    name: 'api-designer',
    description: 'Design RESTful API endpoints and data structures',
    systemPrompt: <<<'PROMPT'
You are an API design expert following REST principles and best practices.

## Design Principles

1. Use appropriate HTTP methods (GET, POST, PUT, PATCH, DELETE)
2. Consistent URL structure and naming
3. Proper status codes
4. Versioning strategy
5. Clear error responses

## Deliverables

- Endpoint specifications
- Request/response schemas
- Authentication requirements
- Rate limiting considerations
- Documentation examples
PROMPT,
    tools: ['read_file', 'write_file'],
    model: 'openai',  // Use specific model
));

// Create coding agent with registry
$agent = AgentBuilder::base()
    ->withCapability(new UseBash())
    ->withCapability(new UseFileTools(__DIR__))
    ->withCapability(new UseTaskPlanning())
    ->withCapability(UseSubagents::withDepth(3, $registry))  // Allow 3 levels of nesting
    ->withLlmPreset('anthropic')
    ->build();

// Example 1: Use code reviewer
echo "=== Example 1: Code Review ===\n\n";

$state = AgentState::empty()->withMessages(
    Messages::fromString('Review the authentication code in this project')
);

$result = $agent->finalStep($state);
echo $result->currentStep()?->outputMessages()->toString() . "\n\n";

// Example 2: Generate tests
echo "=== Example 2: Generate Tests ===\n\n";

$state = AgentState::empty()->withMessages(
    Messages::fromString('Generate Pest tests for the SubagentSpec class')
);

$result = $agent->finalStep($state);
echo $result->currentStep()?->outputMessages()->toString() . "\n\n";

// Example 3: API design
echo "=== Example 3: API Design ===\n\n";

$state = AgentState::empty()->withMessages(
    Messages::fromString('Design a RESTful API for user management')
);

$result = $agent->finalStep($state);
echo $result->currentStep()?->outputMessages()->toString() . "\n\n";
