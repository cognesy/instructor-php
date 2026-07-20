# Goal

Create, execute, and verify a new InstructorPHP example derived from an existing
example's conventions but demonstrating a different use case.

## Paths

- Source example (read-only): `{{SOURCE_EXAMPLE}}`
- InstructorPHP project root (read-only): `{{PROJECT_ROOT}}`
- Writable workspace: `{{WORKSPACE}}`
- Required generated file: `{{WORKSPACE}}/run.php`

## Required workflow

1. Use `read` to inspect the complete source example. Increase `limit` and
   `max_bytes` if its default window is insufficient.
2. Use `bash` for targeted discovery or verification such as `rg`, `php -l`,
   and running the generated example. Do not use `cat` to read files.
3. Use `write` to create `{{WORKSPACE}}/run.php`. The workspace already exists.
4. Make the new example materially different from the source: extract a meeting
   action item with an owner, action, and ISO due date into a typed PHP response
   class. Use deterministic input and assertions for the expected values.
5. Require `{{PROJECT_ROOT}}/examples/boot.php` by absolute path so the temporary
   example can run outside the repository.
6. The initially written example must contain and print this exact declaration:
   `const EXAMPLE_STATUS = 'draft';`
7. Run syntax validation and execute the example with `bash`. If either fails,
   inspect the evidence and use exact `edit` operations to repair the generated
   file. Repeat until it exits successfully and all assertions pass.
8. After the first successful execution, use `edit` to replace the exact status
   declaration with `const EXAMPLE_STATUS = 'verified';` and execute it again.
9. Finish only after the final execution exits successfully and prints
   `Example status: verified`.

## Safety and completion constraints

- Never modify the source example or any file under `{{PROJECT_ROOT}}`.
- Create or edit files only under `{{WORKSPACE}}`.
- Use all four tools: `read`, `bash`, `write`, and `edit`.
- Do not merely describe commands: execute them and use their results.
- In the final response, state the generated path, the final verification
  command, and whether it passed. Do not use a tool just to display the summary.
