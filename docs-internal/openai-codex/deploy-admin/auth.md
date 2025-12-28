# Using an OpenAI API key

You can extend your local Codex usage (CLI and IDE extension) with an API key. API key usage is billed through your OpenAI platform account at the standard API rates, which you can review on the [API pricing page](https://openai.com/api/pricing/).

First, make sure you set up your `OPENAI_API_KEY` environment variable globally. You can get your API key from the [OpenAI dashboard](https://platform.openai.com/api-keys).

Then, you can use the CLI and IDE extension with your API key.

If you’ve previously used the Codex CLI with an API key, update to the latest version, run codex logout, and then run codex to switch back to subscription-based access when you’re ready.

### Use your API key with Codex CLI

You can change which auth method to use with the CLI by changing the preferred_auth_method in the codex config file:

```toml
# ~/.codex/config.toml
preferred_auth_method = "apikey"
```

You can also override it ad-hoc via CLI:

```bash
codex --config preferred_auth_method="apikey"
```

You can go back to ChatGPT auth (default) by running:

```bash
codex --config preferred_auth_method="chatgpt"
```

You can switch back and forth as needed, for example if you use your ChatGPT account but run out of usage credits.

### Use your API key with the IDE extension

When you open the IDE extension, you’ll be prompted to sign in with your ChatGPT account or to use your API key instead. If you wish to use your API key instead, you can select the option to use your API key. Make sure it is configured in your environment variables.