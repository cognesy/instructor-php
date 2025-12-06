The summary points for me are:

What i disocver is Long prompts don't work. Context shifts, prompt breaks. I tried everything, all takes more time than it returned.

I stopped writing prompts. Now I write hooks (triggers when ai read file/path, or write specifit context that i catch with regex or custom logic)

Hooks can be a better alternative to Claude.md or other forms of steering because they:

Use less context

Are guaranteed to be called, the model can't choose to use them or not, they are enforced by the system.

Structure your code in some type of layered architecture that makes it easy to enforce.

In your case this is MVI, but I suspect that any architecture that has good separation via layers or other mechanisms will work and allow you to use hooks to enforce your patterns.

Embed documentation and constraint directly into your code

This idea could stand alone, but you take it further and enforce this with hooks.

This also serves to reduce context usage because Claude can read one file (code) instead of two (code and docs). In reality Claude often needs to read three files (Claude.md, code, docs) so this is a neat improvement.

One downside I could see is that by copying the header block into all files, it will be harder to change your style/constraints/xyz as your codebase grows. This is not that big of a problem.