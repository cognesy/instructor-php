---
title: 'Agent with Structured Output Extraction'
docname: 'agent_structured_output'
---

## Overview

Demonstrates how agents can extract structured data from unstructured text using
the `UseStructuredOutputs` capability powered by Instructor. This pattern enables:

- **Form autofill**: Extract lead/contact data from pasted text or web content
- **Data transformation**: Convert unstructured text into validated PHP objects
- **Multi-step workflows**: Chain extraction with API calls using metadata storage
- **Validation with retry**: Automatic retry on validation failures

Key concepts:
- `UseStructuredOutputs`: Capability for LLM-powered data extraction
- `SchemaRegistry`: Pre-registered extraction schemas
- `structured_output`: Tool to extract data into schema
- Metadata storage: Pass extracted data between tool calls
- Custom tools with state access: Read metadata in subsequent tools

## Example

```php
\<\?php
require 'examples/boot.php';

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\Metadata\UseMetadataTools;
use Cognesy\Addons\Agent\Capabilities\StructuredOutput\SchemaDefinition;
use Cognesy\Addons\Agent\Capabilities\StructuredOutput\SchemaRegistry;
use Cognesy\Addons\Agent\Capabilities\StructuredOutput\StructuredOutputPolicy;
use Cognesy\Addons\Agent\Capabilities\StructuredOutput\UseStructuredOutputs;
use Cognesy\Addons\Agent\Core\Collections\Tools;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Tools\BaseTool;
use Cognesy\Messages\Messages;
use Cognesy\Utils\Json\Json;
use Symfony\Component\Validator\Constraints as Assert;

// =============================================================================
// 1. Define the Lead schema (what we want to extract)
// =============================================================================

class Lead
{
    public function __construct(
        public string $name = '',
        #[Assert\Email(message: 'Invalid email format')]
        public string $email = '',
        public ?string $company = null,
        public ?string $phone = null,
        public ?string $role = null,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $country = null,
    ) {}
}

// =============================================================================
// 2. Create a Lead API tool (simulates CRM API call with metadata access)
// =============================================================================

class CreateLeadTool extends BaseTool
{
    public function __construct() {
        parent::__construct(
            name: 'create_lead',
            description: 'Creates a new lead in the CRM system using data from agent metadata.',
        );
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        $metadataKey = $args['metadata_key'] ?? $args[0] ?? 'current_lead';

        // Read lead data from agent metadata
        if ($this->agentState === null) {
            return 'Error: Agent state not available';
        }

        $leadData = $this->agentState->metadata()->get($metadataKey);

        if ($leadData === null) {
            return "Error: No lead data found at metadata key '{$metadataKey}'";
        }

        // Extract lead info for the response
        $name = match (true) {
            is_object($leadData) && property_exists($leadData, 'name') => $leadData->name,
            is_array($leadData) && isset($leadData['name']) => $leadData['name'],
            default => 'Unknown',
        };

        $email = match (true) {
            is_object($leadData) && property_exists($leadData, 'email') => $leadData->email,
            is_array($leadData) && isset($leadData['email']) => $leadData['email'],
            default => 'Unknown',
        };

        // Simulate API call - in real implementation, call actual CRM API
        $leadId = 'LEAD-' . strtoupper(substr(md5((string) time()), 0, 8));

        return "Lead created successfully!\n" .
               "  ID: {$leadId}\n" .
               "  Name: {$name}\n" .
               "  Email: {$email}\n" .
               "  Source: metadata key '{$metadataKey}'";
    }

    #[\Override]
    public function toToolSchema(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'metadata_key' => [
                            'type' => 'string',
                            'description' => 'The metadata key where lead data is stored (e.g., "current_lead")',
                        ],
                    ],
                    'required' => ['metadata_key'],
                ],
            ],
        ];
    }
}

// =============================================================================
// 3. Build the agent with structured output and API capabilities
// =============================================================================

// Register extraction schemas
$schemas = new SchemaRegistry([
    'lead' => new SchemaDefinition(
        class: Lead::class,
        description: 'Business lead with contact information',
        prompt: 'Extract lead information from the text. Look for names, emails, ' .
                'phone numbers, company names, job titles, and addresses.',
    ),
]);

// Build agent
$agent = AgentBuilder::base()
    ->withCapability(new UseStructuredOutputs(
        schemas: $schemas,
        policy: new StructuredOutputPolicy(
            llmPreset: 'openai',
            defaultMaxRetries: 3,
        ),
    ))
    ->withCapability(new UseMetadataTools())
    ->withTools(new Tools(new CreateLeadTool()))
    ->withMaxSteps(10)
    ->build();

// =============================================================================
// 4. Prepare input data (unstructured text with lead information)
// =============================================================================

$inputText = <<<TEXT
Hey, I just got off the phone with a potential client. Here are the details:

His name is John Smith and he works as VP of Engineering at TechCorp Industries.
They're based in San Francisco, California, USA. You can reach him at
john.smith@techcorp.io or call him at +1-555-0123.

He's interested in our enterprise plan and wants a demo next week.
TEXT;

// =============================================================================
// 5. Create task for the agent
// =============================================================================

$task = <<<TASK
Process this lead information and save it to our CRM:

{$inputText}

Steps:
1. Extract the lead information into structured format using 'lead' schema
2. Store the extracted data as 'current_lead' in metadata
3. Call create_lead API with the metadata key 'current_lead'

Report back when complete.
TASK;

$state = AgentState::empty()->withMessages(
    Messages::fromString($task)
);

// =============================================================================
// 6. Execute agent and observe workflow
// =============================================================================

echo "=== Agent Structured Output Demo ===\n\n";
echo "Input text:\n{$inputText}\n\n";
echo "--- Execution ---\n\n";

while ($agent->hasNextStep($state)) {
    $state = $agent->nextStep($state);

    $step = $state->currentStep();
    echo "Step {$state->stepCount()}: [{$step->stepType()->value}]\n";

    if ($step->hasToolCalls()) {
        foreach ($step->toolCalls()->all() as $toolCall) {
            $args = $toolCall->args();
            $argsSummary = match ($toolCall->name()) {
                'structured_output' => "schema={$args['schema']}, store_as=" . ($args['store_as'] ?? 'none'),
                'create_lead' => "metadata_key={$args['metadata_key']}",
                default => json_encode($args),
            };
            echo "  → {$toolCall->name()}({$argsSummary})\n";
        }
    }

    // Show tool execution results
    foreach ($step->toolExecutions()->all() as $execution) {
        $result = $execution->value();
        $resultStr = match (true) {
            is_string($result) => $result,
            is_object($result) && method_exists($result, '__toString') => (string) $result,
            default => Json::encode($result),
        };
        $preview = strlen($resultStr) > 100 ? substr($resultStr, 0, 100) . '...' : $resultStr;
        echo "    ← {$preview}\n";
    }
}

// =============================================================================
// 7. Show final results
// =============================================================================

echo "\n--- Results ---\n\n";

// Get the extracted lead from metadata
$extractedLead = $state->metadata()->get('current_lead');

if ($extractedLead !== null) {
    echo "Extracted Lead (from metadata):\n";
    if (is_object($extractedLead)) {
        foreach (get_object_vars($extractedLead) as $key => $value) {
            if ($value !== null && $value !== '') {
                echo "  {$key}: {$value}\n";
            }
        }
    } elseif (is_array($extractedLead)) {
        foreach ($extractedLead as $key => $value) {
            if ($value !== null && $value !== '') {
                echo "  {$key}: {$value}\n";
            }
        }
    }
} else {
    echo "No lead data in metadata.\n";
}

echo "\nAgent Response:\n";
echo $state->currentStep()?->outputMessages()->toString() ?? 'No response';
echo "\n\n";

echo "Stats:\n";
echo "  Steps: {$state->stepCount()}\n";
echo "  Status: {$state->status()->value}\n";
echo "  Tokens: {$state->usage()->total()}\n";
?>
```

## Expected Output

```
=== Agent Structured Output Demo ===

Input text:
Hey, I just got off the phone with a potential client. Here are the details:

His name is John Smith and he works as VP of Engineering at TechCorp Industries.
They're based in San Francisco, California, USA. You can reach him at
john.smith@techcorp.io or call him at +1-555-0123.

He's interested in our enterprise plan and wants a demo next week.

--- Execution ---

Step 1: [tool_use]
  → structured_output(schema=lead, store_as=current_lead)
    ← Extracted lead (stored as 'current_lead'): {"name":"John Smith","email":"john.smith@techcorp.io"...
Step 2: [tool_use]
  → create_lead(metadata_key=current_lead)
    ← Lead created successfully!
  ID: LEAD-A1B2C3D4
  Name: John Smith
  Email: john.smith@techcorp.io
  Source: met...
Step 3: [response]

--- Results ---

Extracted Lead (from metadata):
  name: John Smith
  email: john.smith@techcorp.io
  company: TechCorp Industries
  phone: +1-555-0123
  role: VP of Engineering
  city: San Francisco
  country: USA

Agent Response:
I've processed the lead information and saved it to your CRM:

1. **Extracted Data**: Successfully extracted all lead details from the text
2. **Stored**: Saved as 'current_lead' in agent metadata
3. **CRM Created**: Lead ID LEAD-A1B2C3D4

**Lead Summary:**
- Name: John Smith
- Role: VP of Engineering
- Company: TechCorp Industries
- Email: john.smith@techcorp.io
- Phone: +1-555-0123
- Location: San Francisco, USA

The lead is now in your CRM and ready for follow-up.

Stats:
  Steps: 3
  Status: finished
  Tokens: 1847
```

## Key Points

- **Schema-based extraction**: Pre-defined `Lead` class with validation constraints
- **Validation with retry**: Email validation via `#[Assert\Email]` with automatic retry
- **Metadata as scratchpad**: `store_as` parameter stores extracted data for other tools
- **Custom tools with state access**: `CreateLeadTool` extends `BaseTool` to access `$this->agentState`
- **Multi-step workflow**: Extract → Store → API call in sequence
- **LLM configuration**: `StructuredOutputPolicy` sets provider, model, retries
- **Schema registry**: Multiple schemas can be registered for different extraction needs
- **Use cases**: CRM form autofill, document processing, email parsing, web scraping
