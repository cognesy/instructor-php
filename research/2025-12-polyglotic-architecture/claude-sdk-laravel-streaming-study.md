# Streaming Claude Agent SDK → Laravel → Inertia/React

*Technical architecture study for real-time AI response streaming*

---

## Executive Summary

| Challenge | Recommended Solution |
|-----------|---------------------|
| Claude SDK (Python/Node) → Laravel | **FastAPI/Express sidecar with SSE proxy** |
| CLI-only components | **Subprocess with stdout pipe + queue** |
| Laravel → React/Inertia | **Direct SSE endpoint (bypass Inertia)** |
| High-volume streaming | **Redis pub/sub + Laravel Wave** |

---

## Architecture Options

### Option A: HTTP Sidecar (Recommended for most cases)

*Best when: SDK components can run as persistent services*

```
┌─────────────┐    SSE/HTTP     ┌─────────────┐    SSE      ┌─────────────┐
│ Python/Node │ ──────────────► │   Laravel   │ ──────────► │    React    │
│   Sidecar   │                 │   (Proxy)   │             │  (Browser)  │
└─────────────┘                 └─────────────┘             └─────────────┘
```

**Why this works:**
- Claude Agent SDK returns `AsyncIterator` of messages natively
- FastAPI/Express can wrap this as SSE trivially
- Laravel acts as auth gateway and stream proxy
- React uses native `EventSource` API

---

### Option B: Subprocess + Queue (For CLI-only tools)

*Best when: Tool only offers CLI, cannot be wrapped as HTTP service*

```
┌─────────────┐   spawn/pipe   ┌─────────────┐   Redis    ┌─────────────┐
│  CLI Tool   │ ─────────────► │   Laravel   │ ─────────► │    React    │
│  (Python)   │    stdout      │   Worker    │   pub/sub  │  (Browser)  │
└─────────────┘                └─────────────┘            └─────────────┘
```

**Trade-offs:**
- ✅ Works with any CLI tool
- ⚠️ Process management complexity
- ⚠️ Latency from queue hop

---

## Implementation: Python Sidecar with Claude Agent SDK

### FastAPI SSE Server

```python
# sidecar/main.py
from fastapi import FastAPI, Request
from fastapi.responses import StreamingResponse
from claude_agent_sdk import query, ClaudeAgentOptions
import json

app = FastAPI()

@app.post("/stream")
async def stream_claude(request: Request):
    body = await request.json()
    prompt = body.get("prompt")
    
    async def event_generator():
        options = ClaudeAgentOptions(
            max_turns=body.get("max_turns", 10),
            allowed_tools=body.get("allowed_tools", []),
        )
        
        async for message in query(prompt=prompt, options=options):
            # Claude SDK message types: text, tool_use, tool_result, result
            event_data = {
                "type": message.type,
                "content": getattr(message, "content", None),
                "result": getattr(message, "result", None),
            }
            yield f"data: {json.dumps(event_data)}\n\n"
        
        yield "data: [DONE]\n\n"
    
    return StreamingResponse(
        event_generator(),
        media_type="text/event-stream",
        headers={
            "Cache-Control": "no-cache",
            "Connection": "keep-alive",
            "X-Accel-Buffering": "no",  # Nginx: disable buffering
        }
    )
```

### Advanced: ClaudeSDKClient for Bidirectional Sessions

```python
# sidecar/session_stream.py
from claude_agent_sdk import ClaudeSDKClient, ClaudeAgentOptions
from fastapi import WebSocket
import asyncio

async def websocket_session(websocket: WebSocket, session_id: str):
    """Use WebSocket when you need bidirectional communication."""
    await websocket.accept()
    
    options = ClaudeAgentOptions(
        allowed_tools=["Read", "Write", "Bash"],
        permission_mode="acceptEdits",
    )
    
    async with ClaudeSDKClient(options=options) as client:
        while True:
            user_input = await websocket.receive_text()
            await client.query(user_input)
            
            async for msg in client.receive_response():
                await websocket.send_json({
                    "type": msg.type,
                    "content": str(msg),
                })
```

---

## Implementation: Node.js Sidecar Alternative

```typescript
// sidecar/server.ts
import express from 'express';
import { query } from '@anthropic-ai/claude-agent-sdk';

const app = express();
app.use(express.json());

app.post('/stream', async (req, res) => {
  const { prompt, maxTurns = 10 } = req.body;
  
  res.setHeader('Content-Type', 'text/event-stream');
  res.setHeader('Cache-Control', 'no-cache');
  res.setHeader('Connection', 'keep-alive');
  res.setHeader('X-Accel-Buffering', 'no');
  
  try {
    for await (const message of query({
      prompt,
      options: { maxTurns }
    })) {
      const data = JSON.stringify({
        type: message.type,
        content: message.type === 'result' ? message.result : message,
      });
      res.write(`data: ${data}\n\n`);
    }
    res.write('data: [DONE]\n\n');
  } catch (error) {
    res.write(`data: ${JSON.stringify({ type: 'error', error: error.message })}\n\n`);
  }
  
  res.end();
});

app.listen(3001);
```

---

## Implementation: Laravel Proxy Controller

### SSE Proxy (Symfony HttpClient recommended)

```php
<?php
// app/Http/Controllers/ClaudeStreamController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Chunk\ServerSentEvent;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClaudeStreamController extends Controller
{
    private string $sidecarUrl;
    
    public function __construct()
    {
        $this->sidecarUrl = config('services.claude_sidecar.url', 'http://localhost:3001');
    }
    
    public function stream(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'prompt' => 'required|string|max:100000',
            'max_turns' => 'integer|min:1|max:50',
        ]);
        
        return new StreamedResponse(function () use ($validated) {
            $client = new EventSourceHttpClient(HttpClient::create());
            
            $source = $client->request('POST', "{$this->sidecarUrl}/stream", [
                'json' => $validated,
                'buffer' => false,
            ]);
            
            foreach ($client->stream($source) as $chunk) {
                if ($chunk instanceof ServerSentEvent) {
                    $data = $chunk->getData();
                    echo "data: {$data}\n\n";
                    
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                    
                    if ($data === '[DONE]') {
                        break;
                    }
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
```

### Route Configuration

```php
// routes/web.php
use App\Http\Controllers\ClaudeStreamController;

// SSE endpoints must bypass CSRF and session middleware for streaming
Route::post('/api/claude/stream', [ClaudeStreamController::class, 'stream'])
    ->middleware(['auth:sanctum'])  // Use token auth, not session
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
```

---

## Implementation: Subprocess for CLI-Only Tools

### Laravel Process Streaming

```php
<?php
// app/Services/CliStreamService.php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Generator;

class CliStreamService
{
    public function streamCli(string $command, array $args = []): Generator
    {
        $process = Process::path(base_path('tools'))
            ->timeout(300)
            ->start([$command, ...$args]);
        
        // Stream stdout line by line
        foreach ($process->output() as $type => $data) {
            if ($type === 'out') {
                yield $data;
            }
        }
        
        $result = $process->wait();
        
        if (!$result->successful()) {
            throw new \RuntimeException("CLI failed: " . $result->errorOutput());
        }
    }
}
```

### With Redis Pub/Sub for Async Streaming

```php
<?php
// app/Jobs/StreamCliJob.php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Process;

class StreamCliJob implements ShouldQueue
{
    use Queueable;
    
    public function __construct(
        private string $sessionId,
        private string $command,
        private array $args,
    ) {}
    
    public function handle(): void
    {
        $process = Process::start([$this->command, ...$this->args]);
        
        foreach ($process->output() as $type => $data) {
            if ($type === 'out') {
                Redis::publish("stream:{$this->sessionId}", json_encode([
                    'type' => 'chunk',
                    'data' => $data,
                    'timestamp' => now()->toIso8601String(),
                ]));
            }
        }
        
        Redis::publish("stream:{$this->sessionId}", json_encode([
            'type' => 'done',
        ]));
    }
}
```

---

## Implementation: React/Inertia Frontend

### Key Insight: SSE Bypasses Inertia

Inertia manages page state and navigation. **SSE streams go direct to browser**, not through Inertia's request cycle. This is correct—don't try to force SSE through Inertia.

### React Hook for SSE

```tsx
// resources/js/hooks/useClaudeStream.ts
import { useState, useCallback, useRef, useEffect } from 'react';

interface StreamMessage {
  type: 'text' | 'tool_use' | 'tool_result' | 'result' | 'error';
  content?: string;
  result?: string;
}

interface UseClaudeStreamOptions {
  onMessage?: (message: StreamMessage) => void;
  onComplete?: (fullResult: string) => void;
  onError?: (error: Error) => void;
}

export function useClaudeStream(options: UseClaudeStreamOptions = {}) {
  const [isStreaming, setIsStreaming] = useState(false);
  const [messages, setMessages] = useState<StreamMessage[]>([]);
  const [accumulatedText, setAccumulatedText] = useState('');
  const eventSourceRef = useRef<EventSource | null>(null);
  const abortControllerRef = useRef<AbortController | null>(null);
  
  const startStream = useCallback(async (prompt: string, maxTurns = 10) => {
    // Clean up any existing stream
    if (eventSourceRef.current) {
      eventSourceRef.current.close();
    }
    
    setIsStreaming(true);
    setMessages([]);
    setAccumulatedText('');
    
    abortControllerRef.current = new AbortController();
    
    try {
      // POST to get stream URL (with auth)
      const response = await fetch('/api/claude/stream', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'text/event-stream',
        },
        body: JSON.stringify({ prompt, max_turns: maxTurns }),
        signal: abortControllerRef.current.signal,
      });
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      
      const reader = response.body?.getReader();
      const decoder = new TextDecoder();
      let buffer = '';
      
      while (reader) {
        const { done, value } = await reader.read();
        if (done) break;
        
        buffer += decoder.decode(value, { stream: true });
        const lines = buffer.split('\n');
        buffer = lines.pop() || '';
        
        for (const line of lines) {
          if (line.startsWith('data: ')) {
            const data = line.slice(6);
            
            if (data === '[DONE]') {
              setIsStreaming(false);
              options.onComplete?.(accumulatedText);
              return;
            }
            
            try {
              const message: StreamMessage = JSON.parse(data);
              setMessages(prev => [...prev, message]);
              
              if (message.type === 'text' && message.content) {
                setAccumulatedText(prev => prev + message.content);
              } else if (message.type === 'result' && message.result) {
                setAccumulatedText(message.result);
              }
              
              options.onMessage?.(message);
            } catch (e) {
              console.warn('Failed to parse SSE message:', data);
            }
          }
        }
      }
    } catch (error) {
      if (error instanceof Error && error.name !== 'AbortError') {
        options.onError?.(error);
      }
    } finally {
      setIsStreaming(false);
    }
  }, [options, accumulatedText]);
  
  const stopStream = useCallback(() => {
    abortControllerRef.current?.abort();
    eventSourceRef.current?.close();
    setIsStreaming(false);
  }, []);
  
  useEffect(() => {
    return () => {
      eventSourceRef.current?.close();
      abortControllerRef.current?.abort();
    };
  }, []);
  
  return {
    startStream,
    stopStream,
    isStreaming,
    messages,
    accumulatedText,
  };
}
```

### React Component Example

```tsx
// resources/js/Components/ClaudeChat.tsx
import { useState } from 'react';
import { useClaudeStream } from '@/hooks/useClaudeStream';

export function ClaudeChat() {
  const [prompt, setPrompt] = useState('');
  
  const {
    startStream,
    stopStream,
    isStreaming,
    accumulatedText,
    messages,
  } = useClaudeStream({
    onError: (error) => console.error('Stream error:', error),
  });
  
  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (prompt.trim() && !isStreaming) {
      startStream(prompt);
      setPrompt('');
    }
  };
  
  return (
    <div className="flex flex-col h-full">
      {/* Message display */}
      <div className="flex-1 overflow-auto p-4 space-y-2">
        {messages.map((msg, i) => (
          <div key={i} className="text-sm">
            <span className="font-mono text-xs text-gray-500">[{msg.type}]</span>
            <pre className="whitespace-pre-wrap">{msg.content || msg.result}</pre>
          </div>
        ))}
        
        {isStreaming && (
          <div className="animate-pulse text-gray-400">●●●</div>
        )}
      </div>
      
      {/* Input */}
      <form onSubmit={handleSubmit} className="border-t p-4 flex gap-2">
        <input
          type="text"
          value={prompt}
          onChange={(e) => setPrompt(e.target.value)}
          disabled={isStreaming}
          className="flex-1 border rounded px-3 py-2"
          placeholder="Ask Claude..."
        />
        {isStreaming ? (
          <button type="button" onClick={stopStream} className="px-4 py-2 bg-red-500 text-white rounded">
            Stop
          </button>
        ) : (
          <button type="submit" className="px-4 py-2 bg-blue-500 text-white rounded">
            Send
          </button>
        )}
      </form>
    </div>
  );
}
```

---

## Nginx Configuration

```nginx
# Critical for SSE: disable proxy buffering
location /api/claude/stream {
    proxy_pass http://127.0.0.1:8000;
    proxy_http_version 1.1;
    
    # SSE-specific settings
    proxy_buffering off;
    proxy_cache off;
    chunked_transfer_encoding on;
    
    # Keep connection alive
    proxy_read_timeout 86400s;
    proxy_send_timeout 86400s;
    
    # Headers
    proxy_set_header Connection '';
    proxy_set_header X-Accel-Buffering no;
}
```

---

## Decision Matrix

| Scenario | Architecture | Sidecar Tech | Complexity |
|----------|--------------|--------------|------------|
| Claude Agent SDK (agentic) | HTTP Sidecar | FastAPI/Express | Medium |
| Simple Claude API calls | Direct Laravel | Anthropic PHP SDK | Low |
| CLI-only Python tool | Subprocess + Redis | N/A | High |
| Multiple concurrent streams | Redis pub/sub | Any | High |
| Low latency, single user | Direct subprocess | N/A | Medium |

---

## Gotchas and Mitigations

### PHP Output Buffering
```php
// In StreamedResponse callback:
if (ob_get_level() > 0) {
    ob_flush();
}
flush();
```

### Nginx Buffering
- Set `X-Accel-Buffering: no` header
- Configure `proxy_buffering off`

### HTTP/1.1 Connection Limits
- Browsers limit 6 concurrent SSE connections per domain
- Use HTTP/2 or WebSocket for high-volume scenarios

### Auth with EventSource
- `EventSource` doesn't support custom headers
- Options: URL token param, cookies, or use `fetch` with ReadableStream (shown above)

### Laravel Session Locking
```php
// Start session read-only to avoid blocking
session()->save();
// ... then stream
```

---

## Testing Strategy

```bash
# Test sidecar directly
curl -X POST http://localhost:3001/stream \
  -H "Content-Type: application/json" \
  -d '{"prompt": "Say hello"}' \
  --no-buffer

# Test Laravel proxy
curl -X POST http://localhost:8000/api/claude/stream \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"prompt": "Say hello"}' \
  --no-buffer
```

---

## Recommended Stack

| Layer | Technology | Notes |
|-------|------------|-------|
| SDK Runtime | Python 3.10+ | Better async, native SDK support |
| Sidecar Framework | FastAPI | Native async, SSE built-in |
| Inter-service | HTTP/SSE | Simple, debuggable, stateless |
| Laravel → Browser | Native StreamedResponse | No extra dependencies |
| Frontend | Fetch + ReadableStream | Works with auth headers |
| Queue (if needed) | Redis pub/sub | Laravel Horizon compatible |

---

## Files to Create

1. `tools/sidecar/` - Python FastAPI service
2. `app/Http/Controllers/ClaudeStreamController.php`
3. `resources/js/hooks/useClaudeStream.ts`
4. `docker-compose.yml` - Sidecar service definition
5. `config/services.php` - Sidecar URL configuration
