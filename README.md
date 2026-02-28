### Task Planning Capability

**Purpose:**
The task planning capability allows automated generation and management of complex task sequences, enabling systems to plan, prioritize, and execute multiple implementation steps efficiently.

**Configuration Options:**
- `max_steps` (integer, default: 10): Limits the maximum number of steps in a plan.
- `timeout` (seconds, default: 60): Sets the maximum time allowed for planning.
- `strategy` (string, default: 'greedy'): Determines the planning algorithm (e.g., 'greedy', 'backtracking', 'heuristic').
- `retries` (integer, default: 3): Number of retry attempts if planning fails.

**Example Usage:**

```php
// Initialize planner with options
$planner = new TaskPlanner([
    'max_steps' => 15,
    'timeout' => 120,
    'strategy' => 'heuristic',
]);

// Define goal and context
$goal = 'Refactor the task planning capability docs';
$context = 'Repository with existing documentation files.';

// Generate plan
$plan = $planner->createPlan($goal, $context);

echo $plan;
```

This will produce a detailed sequence of implementation steps to update the documentation effectively.