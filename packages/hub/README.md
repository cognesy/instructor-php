# Hub - Example Execution & Tracking

Hub provides example execution with comprehensive status tracking, selective re-execution, and performance analytics.

## Quick Start

```bash
# Run single example (raw output, colors preserved)
composer hub run 1
composer hub run basics/Basic

# List all examples
composer hub list

# Run all examples with tracking
composer hub all

# Check execution status
composer hub status
```

## Core Commands

### Single Example Execution

```bash
# Raw output (default) - preserves colors, streaming, TTY
composer hub run 35                    # Real-time streaming output
composer hub raw 35                    # Alias for raw output

# With tracking (stores execution metadata)
composer hub -- run 35 --track         # Tracked execution

> **Note**: When using options with composer scripts, use `composer hub -- <command> --option`
> to ensure arguments are passed through correctly.
```

### Bulk Execution

```bash
# Run all examples with tracking
composer hub all                       # Start from beginning
composer hub all 50                    # Start from example 50

# Selective execution
composer hub -- all --filter=pending   # Only unexecuted examples
composer hub -- all --filter=errors    # Only failed examples
composer hub -- all --filter=stale     # Only examples with modified files
composer hub -- all --dry-run          # Preview without executing

# Dedicated commands
composer hub errors                    # Re-run failed examples
composer hub stale                     # Run examples with modified files
```

### Status & Analytics

```bash
# Status overview
composer hub status                    # Summary view
composer hub -- status --detailed      # Per-example breakdown
composer hub -- status --errors-only   # Show only failures
composer hub -- status --format=json   # JSON output for automation

# Performance analytics
composer hub stats                     # Performance metrics
composer hub -- stats --slowest=20     # Show 20 slowest examples
```

### Maintenance

```bash
# Clean status data
composer hub -- clean --completed      # Remove successful runs
composer hub -- clean --older-than="1 week"  # Remove old data
composer hub -- clean --all --backup   # Reset all (with backup)
```

## Status Tracking

### Status File Location
- **File**: `.hub/status.json` (git-excluded)
- **Contains**: Execution metadata, timing, errors, attempts
- **Format**: JSON with metadata, examples, and statistics sections

### Execution States
- `pending` - Never executed
- `running` - Currently executing
- `completed` - Successful execution
- `error` - Failed execution
- `interrupted` - Interrupted by Ctrl+C
- `stale` - Source file modified since last run

### Status Data Structure
```json
{
  "metadata": {
    "version": "1.0",
    "lastUpdated": "2024-12-07T15:30:45Z",
    "totalExamples": 213
  },
  "examples": {
    "1": {
      "index": 1,
      "name": "Basic",
      "status": "completed",
      "executionTime": 1.245,
      "lastExecuted": "2024-12-07T15:25:30Z",
      "attempts": 1,
      "errors": [],
      "exitCode": 0
    }
  },
  "statistics": {
    "totalExecuted": 150,
    "completed": 148,
    "errors": 2,
    "averageExecutionTime": 1.234
  }
}
```

## Filtering System

### Filter Modes
- `all` - All examples (default)
- `pending` - Never executed examples
- `errors` - Failed examples only
- `stale` - Examples with modified source files
- `completed` - Successfully executed examples

### Usage Patterns
```bash
# Command variations (all equivalent)
composer hub all --filter=errors
composer hub errors
composer hub -- all --filter=errors

# Multiple filters
composer hub all --filter=pending --dry-run
composer hub all --filter=stale --stop-on-error
```

## Output Formats

### Table Format (Default)
```
Total Examples: 213
Executed:       150
Completed:      148
Errors:         2
Success Rate:   98.67%
```

### JSON Format
```bash
composer hub status --format=json
# Returns structured JSON for automation
```

### CSV Export
```bash
composer hub stats --format=csv > performance.csv
# Exports performance data for analysis
```

## Performance Features

### Execution Timing
- **Per-example timing**: Precise execution duration
- **Aggregate statistics**: Average, median, std deviation
- **Performance ranking**: Slowest/fastest examples
- **Trend analysis**: Execution time over multiple runs

### Error Tracking
- **Error classification**: Fatal errors, timeouts, exceptions
- **Failure patterns**: Frequently failing examples
- **Recovery tracking**: Success after failure count
- **Error details**: Full error messages and stack traces

### Interruption Handling
- **Graceful Ctrl+C**: Preserves execution state
- **Resume capability**: Continue from interruption point
- **Partial results**: Status saved for completed examples
- **Signal handling**: Clean shutdown with state preservation

## Integration & Automation

### CI/CD Integration
```bash
# Run only failing tests in CI
composer hub errors --format=json > failed-tests.json

# Check if any examples are failing
composer hub status --errors-only --format=json | jq '.examples | length'

# Get performance baseline
composer hub stats --format=json > performance-baseline.json
```

### Git Integration
- **Automatic .gitignore**: `.hub/` directory excluded
- **Stale detection**: Modified file tracking
- **Branch isolation**: Status per branch/worktree
- **Hook integration**: Pre-commit validation possible

### IDE Integration
```json
// .vscode/tasks.json
{
  "tasks": [
    {
      "label": "Hub: Run Example",
      "type": "shell",
      "command": "composer hub run ${input:exampleNumber}"
    },
    {
      "label": "Hub: Check Status",
      "type": "shell",
      "command": "composer hub status"
    }
  ]
}
```

## Advanced Usage

### Parallel Development
```bash
# Different status per worktree
git worktree add ../feature-branch feature/new-functionality
cd ../feature-branch
composer hub all --filter=pending  # Independent status tracking
```

### Custom Workflows
```bash
# Development workflow
composer hub all --filter=stale     # Run modified examples
composer hub errors                 # Fix any failures
composer hub status --detailed      # Review results

# Performance monitoring
composer hub stats --slowest=10     # Identify bottlenecks
composer hub run 65 --track         # Profile specific example
composer hub clean --older-than="1 day"  # Cleanup old data
```

### Debugging & Troubleshooting
```bash
# Raw execution for debugging
composer hub run 35                 # Full output with colors

# Tracked execution for analysis
composer hub -- run 35 --track      # Status persistence

# Error investigation
composer hub status --errors-only --detailed
composer hub run <failing-example>  # Debug with full output
```

## Architecture

### DDD Implementation
- **Value Objects**: ExecutionResult, ExecutionError, ExecutionStatus
- **Entities**: ExampleExecutionStatus with business logic
- **Services**: StatusRepository, ExecutionTracker, EnhancedRunner
- **Interfaces**: CanTrackExecution, CanPersistStatus, CanExecuteExample

### File Organization
```
packages/hub/src/
├── Commands/           # Console commands
├── Contracts/          # Domain interfaces
├── Data/              # Value objects & entities
├── Services/          # Application services
└── Exceptions/        # Domain exceptions
```

## Backward Compatibility

All existing workflows continue unchanged:
- `composer hub list` - Example listing
- `composer hub run 1` - Single execution (now with raw output)
- `composer hub all` - Bulk execution (now with tracking)
- `composer hub show 1` - Example details

New capabilities are additive and optional.