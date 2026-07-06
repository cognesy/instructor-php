# Documentation generation, QA, and drift detection.

# Docs QA across package docs (optionally limit to given packages).
[group('docs'), doc('Docs QA: anti-drift, links, snippet lint')]
docs-qa *packages:
    bash ./scripts/docs/docs-qa-all.sh {{packages}}

# Detect docs drift — packages where src/ moved ahead of docs.
[group('docs'), doc('Detect docs drift (src newer than docs)')]
docs-drift *args:
    composer docs drift {{args}}

# Generate all documentation targets (Mintlify + MkDocs).
[group('docs'), doc('Generate all docs (Mintlify + MkDocs)')]
docs-gen:
    composer docs gen:mintlify
    composer docs gen:mkdocs

# Generate Mintlify documentation.
[group('docs'), doc('Generate Mintlify docs')]
docs-mintlify:
    composer docs gen:mintlify

# Generate MkDocs documentation.
[group('docs'), doc('Generate MkDocs docs')]
docs-mkdocs:
    composer docs gen:mkdocs

# Generate LLM-friendly docs (llms.txt, llms-full.txt).
[group('docs'), doc('Generate LLM docs (llms.txt)')]
docs-llms:
    composer docs gen:llms

# Generate example documentation.
[group('docs'), doc('Generate example docs')]
docs-examples:
    composer docs gen:examples

# Generate per-package documentation.
[group('docs'), doc('Generate per-package docs')]
docs-packages:
    composer docs gen:packages

# Validate extracted doc code blocks.
[group('docs'), doc('Validate extracted doc code blocks')]
docs-validate:
    composer docs validate

# Clear generated documentation.
[group('docs'), doc('Clear generated documentation')]
docs-clear:
    composer docs clear

# Export codebase to markdown contexts for LLM tools.
[group('docs'), doc('Export codebase to markdown for LLM tools')]
docs-code2md:
    bash ./scripts/docs/code2md.sh
