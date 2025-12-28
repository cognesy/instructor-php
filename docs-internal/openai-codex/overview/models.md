# Codex Models

## Recommended models

<div class="not-prose grid gap-6 md:grid-cols-2 xl:grid-cols-3">
  <ModelDetails
    client:load
    name="gpt-5.2-codex"
    slug="gpt-5.2-codex"
    wallpaperUrl="/images/codex/gpt-5.2-codex.png"
    description="Most advanced agentic coding model for real-world engineering."
    data={{
      features: [
        {
          title: "Capability",
          value: "",
          icons: [
            "openai.SparklesFilled",
            "openai.SparklesFilled",
            "openai.SparklesFilled",
            "openai.SparklesFilled",
            "openai.SparklesFilled",
          ],
        },
        {
          title: "Speed",
          value: "",
          icons: [
            "openai.Flash",
            "openai.Flash",
            "openai.Flash",
            "openai.Flash",
          ],
        },
        {
          title: "Codex CLI & SDK",
          value: true,
        },
        { title: "Codex IDE Extension", value: true },
        {
          title: "Codex Cloud",
          value: true,
        },
        { title: "ChatGPT Credits", value: true },
        { title: "API Access", value: false },
      ],
    }}
  />
  <ModelDetails
    client:load
    name="gpt-5.1-codex-max"
    slug="gpt-5.1-codex-max"
    description="Optimized for long-horizon, agentic coding tasks in Codex."
    data={{
      features: [
        {
          title: "Capability",
          value: "",
          icons: [
            "openai.SparklesFilled",
            "openai.SparklesFilled",
            "openai.SparklesFilled",
            "openai.SparklesFilled",
          ],
        },
        {
          title: "Speed",
          value: "",
          icons: [
            "openai.Flash",
            "openai.Flash",
            "openai.Flash",
            "openai.Flash",
          ],
        },
        {
          title: "Codex CLI & SDK",
          value: true,
        },
        { title: "Codex IDE Extension", value: true },
        {
          title: "Codex Cloud",
          value: false,
        },
        { title: "ChatGPT Credits", value: true },
        { title: "API Access", value: true },
      ],
    }}
  />
  <ModelDetails
    client:load
    name="gpt-5.1-codex-mini"
    slug="gpt-5.1-codex-mini"
    description="Smaller, more cost-effective, less-capable version of GPT-5.1-Codex."
    data={{
      features: [
        {
          title: "Capability",
          value: "",
          icons: [
            "openai.SparklesFilled",
            "openai.SparklesFilled",
            "openai.SparklesFilled",
          ],
        },
        {
          title: "Speed",
          value: "",
          icons: [
            "openai.Flash",
            "openai.Flash",
            "openai.Flash",
            "openai.Flash",
            "openai.Flash",
          ],
        },
        {
          title: "Codex CLI & SDK",
          value: true,
        },
        { title: "Codex IDE Extension", value: true },
        {
          title: "Codex Cloud",
          value: false,
        },
        { title: "ChatGPT Credits", value: true },
        { title: "API Access", value: true },
      ],
    }}
  />
</div>

## Configuring models

### Configure your default local model

Both the Codex CLI and Codex IDE Extension use the same [`config.toml` configuration file](/codex/local-config) to set the default model.

To choose your default model, add a `model` entry into your `config.toml`. If no entry is set, your version of the Codex CLI or IDE Extension will pick the model.

```toml
model="gpt-5.2"
```

If you regularly switch between different models in the Codex CLI, and want to control more than just the setting, you can also create [different Codex profiles](/codex/local-config#profiles).

### Choosing temporarily a different local model

In the Codex CLI you can use the `/model` command during an active session to change the model. In the IDE Extension you can use the model selector next to the input box to choose your model.

To start a brand new Codex CLI session with a specific model or to specify the model for `codex exec` you can use the `--model`/`-m` flag:

```bash
codex -m gpt-5.1-codex-mini
```

### Choosing your model for cloud tasks

There is currently no way to control the model for Codex Cloud tasks. It's currently using `gpt-5.1-codex`.

## Alternative models

<div class="not-prose grid gap-4 md:grid-cols-2 xl:grid-cols-3">

{" "}

<ModelDetails
  client:load
  name="gpt-5.2"
  slug="gpt-5.2"
  description="Our best general agentic model for tasks across industries and domains."
  collapsible
  data={{
    features: [
      {
        title: "Capability",
        value: "",
        icons: [
          "openai.SparklesFilled",
          "openai.SparklesFilled",
          "openai.SparklesFilled",
          "openai.SparklesFilled",
          "openai.SparklesFilled",
        ],
      },
      {
        title: "Speed",
        value: "",
        icons: ["openai.Flash", "openai.Flash", "openai.Flash"],
      },
      {
        title: "Codex CLI & SDK",
        value: true,
      },
      { title: "Codex IDE Extension", value: true },
      {
        title: "Codex Cloud",
        value: false,
      },
      { title: "ChatGPT Credits", value: true },
      { title: "API Access", value: true },
    ],
  }}
/>

<ModelDetails
  name="gpt-5.1"
  description="Great for for coding and agentic tasks across domains. Succeeded by GPT-5.2."
  slug="gpt-5.1"
  collapsible
  data={{
    features: [
      {
        title: "Capability",
        value: "",
        icons: [
          "openai.SparklesFilled",
          "openai.SparklesFilled",
          "openai.SparklesFilled",
          "openai.SparklesFilled",
        ],
      },
      {
        title: "Speed",
        value: "",
        icons: ["openai.Flash", "openai.Flash", "openai.Flash"],
      },
      {
        title: "Codex CLI & SDK",
        value: true,
      },
      { title: "Codex IDE Extension", value: true },
      {
        title: "Codex Cloud",
        value: false,
      },
      { title: "ChatGPT Credits", value: true },
      { title: "API Access", value: true },
    ],
  }}
/>
<ModelDetails
  client:load
  name="gpt-5.1-codex"
  slug="gpt-5.1-codex"
  description="Optimized for long-running, agentic coding tasks in Codex. Succeeded by GPT-5.1-Codex-Max."
  collapsible
  data={{
    features: [
      {
        title: "Capability",
        value: "",
        icons: [
          "openai.SparklesFilled",
          "openai.SparklesFilled",
          "openai.SparklesFilled",
          "openai.SparklesFilled",
        ],
      },
      {
        title: "Speed",
        value: "",
        icons: ["openai.Flash", "openai.Flash", "openai.Flash"],
      },
      {
        title: "Codex CLI & SDK",
        value: true,
      },
      { title: "Codex IDE Extension", value: true },
      {
        title: "Codex Cloud",
        value: true,
      },
      { title: "ChatGPT Credits", value: true },
      { title: "API Access", value: true },
    ],
  }}
/>
<ModelDetails
  client:load
  name="gpt-5-codex"
  slug="gpt-5-codex"
  description="Version of GPT-5 tuned for long-running, agentic coding tasks. Succeeded by GPT-5.1-Codex."
  collapsible
  data={{
    features: [
      {
        title: "Capability",
        value: "",
        icons: [
          "openai.SparklesFilled",
          "openai.SparklesFilled",
          "openai.SparklesFilled",
        ],
      },
      {
        title: "Speed",
        value: "",
        icons: ["openai.Flash", "openai.Flash", "openai.Flash"],
      },
      {
        title: "Codex CLI & SDK",
        value: true,
      },
      { title: "Codex IDE Extension", value: true },
      {
        title: "Codex Cloud",
        value: false,
      },
      { title: "ChatGPT Credits", value: true },
      { title: "API Access", value: true },
    ],
  }}
/>

    <ModelDetails
      client:load
      name="gpt-5-codex-mini"
      slug="gpt-5-codex"
      description="Smaller, more cost-effective version of GPT-5-Codex. Succeeded by GPT-5.1-Codex-Mini."
      collapsible
      data={{
        features: [
          {
            title: "Capability",
            value: "",
            icons: [
              "openai.SparklesFilled",
              "openai.SparklesFilled",
            ],
          },
          { title: "Speed", value: "", icons: ["openai.Flash", "openai.Flash", "openai.Flash", "openai.Flash"] },
          {
            title: "Codex CLI & SDK",
            value: true,
          },
          { title: "Codex IDE Extension", value: true },
          {
            title: "Codex Cloud",
            value: false,
          },
          { title: "ChatGPT Credits", value: true },
          { title: "API Access", value: false },
        ],
      }}
    />

    <ModelDetails
      client:load
      name="gpt-5"
      slug="gpt-5"
      description="Reasoning model for coding and agentic tasks across domains. Succeeded by GPT-5.1."
      collapsible
      data={{
        features: [
          {
            title: "Capability",
            value: "",
            icons: [
              "openai.SparklesFilled",
              "openai.SparklesFilled",
              "openai.SparklesFilled",
            ],
          },
          { title: "Speed", value: "", icons: ["openai.Flash", "openai.Flash", "openai.Flash"] },
          {
            title: "Codex CLI & SDK",
            value: true,
          },
          { title: "Codex IDE Extension", value: true },
          {
            title: "Codex Cloud",
            value: false,
          },
          { title: "ChatGPT Credits", value: true },
          { title: "API Access", value: true },
        ],
      }}
    />

  </div>

## Other models

Codex works best with the models listed above.

If you're authenticating Codex with an API key, you can also point Codex at any model and provider that supports either the [Chat Completions](https://platform.openai.com/docs/api-reference/chat) or [Responses APIs](https://platform.openai.com/docs/api-reference/responses) to fit your specific use case.