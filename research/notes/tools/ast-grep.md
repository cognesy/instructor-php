# ast-grep

Preferred Tool for Structural Code Refactoring

**ast-grep** is the primary tool for multi-file and codebase-wide structural code edits and refactorings. It should be prioritized over manual editing or simple text-based search/replace for any structural changes.

## When to Use ast-grep

**ALWAYS use ast-grep for:**
- Multi-file refactoring (3+ files)
- Method/function name changes across the codebase
- API migrations (changing method signatures, parameters)
- Structural pattern replacements (e.g., `$obj->oldMethod($args)` → `$obj->newMethod($args)`)
- Consistent code modernization across files
- Complex transformations that preserve syntax structure

**Prefer ast-grep over manual editing for:**
- Any change affecting 2+ files with the same pattern
- Method call transformations
- Class/interface renaming with usage updates
- Consistent code style enforcement

## PHP Pattern Syntax

ast-grep uses meta-variables to capture and reuse parts of matched code:

```yaml
# Basic method call transformation
id: refactor-method-calls
language: php
rule:
  pattern: $OBJ->oldMethodName($ARG1, $ARG2)
fix: $OBJ->newMethodName($ARG1, $ARG2)
```

**Meta-variables:**
- `$OBJ`, `$VAR`, `$EXPR` - Match any expression/variable
- `$ARG1`, `$ARG2`, `$ARGS` - Match method arguments
- `$$$` - Match variadic arguments (any number)

## Usage Examples

**Command-line search:**
```bash
ast-grep --pattern '$OBJ->methodName($ARG1, $ARG2)' --lang php packages/
```

**Rule-based transformation:**
```bash
# Create rule.yml with pattern and fix
ast-grep scan --rule rule.yml packages/ --update-all
```

**Real transformation example:**
```yaml
id: migrate-to-fluent-api
language: php
rule:
  pattern: $OBJ->withMessagesAppendedInSection($SECTION, $MESSAGES)
fix: $OBJ->section($SECTION)->appendMessages($MESSAGES)
```

## Best Practices

1. **Test patterns first** - Use `ast-grep --pattern` to verify matches before transformation
2. **Create temporary test files** - Test complex patterns on simplified examples
3. **Use descriptive rule IDs** - Make rules self-documenting
4. **Verify with tests** - Always run test suite after structural changes
5. **Commit atomically** - Each ast-grep transformation should be a separate commit

## Rule File Structure

```yaml
id: descriptive-rule-name
language: php
rule:
  pattern: # What to match
fix: # What to replace it with
```

**Pro tip:** ast-grep preserves exact syntax structure, making it superior to regex for code transformations.
