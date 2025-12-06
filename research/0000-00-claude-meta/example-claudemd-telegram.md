# telegram/CLAUDE.md


## OUTCOME [Context-Based Telegram Bot]


Grammy bot with vertical slice architecture (aligned with macOS + VS Code patterns)


## PATTERN [Architecture]


```
telegram/
├── global/                 ← Shared infrastructure
│   ├── config.ts           ← Runtime config
│   ├── callback.ts         ← Callback IDs
│   ├── message.ts          ← Message utilities
│   └── ...                 ← Other shared utils
├── contexts/{domain}/      ← Vertical slices
│   ├── {action}.ts         ← Feature handlers
│   └── CLAUDE.md           ← Context docs
├── bot.ts                  ← Entry point
└── manifest.ts             ← Deployment config
```


## PATTERN [Registration flow]


```
bot.ts:
  1. bot.command('x', handleCommandX)     ← Commands first
  2. bot.on('message:text', handleText)   ← Then messages
  3. registerFeatureX({ bot, pool })      ← Then callbacks
```


## CONSTRAINT [Naming conventions]


- Files: kebab-case (text-translate.ts)
- Callbacks: SCREAMING_SNAKE (TEXT_TRANSLATE)
- Handlers: handleX / registerX


## CONSTRAINT [Import rules]


- /global/* → Shared utilities
- /contexts/{domain}/* → Context files
- Relative imports only within same context


## DEPENDENCY [External dependencies]


- grammy: Telegram bot framework
- bun: Runtime + HTTP server
- pg: PostgreSQL pool
- /infra/database: Supabase connection
- u/actions/*: AI processing actions