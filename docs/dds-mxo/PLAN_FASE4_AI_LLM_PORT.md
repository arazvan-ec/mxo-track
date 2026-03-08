# Plan de Ejecución: Fase 4 — AI/LLM Port (Claude + OpenAI)

## Contexto

Extraer `ClaudeApiClient` y `OpenAiApiClient` en puertos `LlmClientInterface` y `EmbeddingClientInterface`. 7 consumidores de Claude + 1 de OpenAI.

## Archivos críticos

| Archivo | API Client | Methods used |
|---------|-----------|-------------|
| `AiAssistantService` | Claude | `completeWithToolLoop()` |
| `PostRouteAnalyzer` | Claude | `complete()`, `extractText()` |
| `ShipmentSkillDetector` | Claude | `complete()`, `extractText()` |
| `WebhookMessageEnricher` | Claude | `complete()`, `extractText()` |
| `DeliveryNoteAiEnricher` | Claude | `complete()`, `extractText()` |
| `DriverBriefingService` | Claude | `complete()`, `extractText()` |
| `ExceptionClassifierService` | Claude | `sendMessage()` (BUG: method no existe) |
| `EmbeddingService` | OpenAI | `embed()` |

## Commits

### Commit 1: Value Objects
- `src/Ai/LlmRequest.php` — `systemPrompt, userMessage, model, maxTokens, temperature`
- `src/Ai/LlmResponse.php` — `content, model, inputTokens, outputTokens, stopReason, rawResponse`
- `src/Ai/ToolDefinition.php` — `name, description, inputSchema`

### Commit 2: Port Interfaces
- `src/Ai/LlmClientInterface.php` — `complete(LlmRequest): LlmResponse`, `completeWithToolLoop(messages, system, tools, executor): LlmResponse`
- `src/Ai/EmbeddingClientInterface.php` — `embed(text): ?list<float>`, `embedBatch(texts): list<list<float>>`

### Commit 3: Adapters
- `src/Ai/ClaudeLlmClient.php` — absorbe ClaudeApiClient
- `src/Ai/OpenAiEmbeddingClient.php` — absorbe OpenAiApiClient
- `src/Ai/NullLlmClient.php`, `src/Ai/NullEmbeddingClient.php`

### Commit 4: Migrate consumers
- 7 servicios migrados a LlmClientInterface
- EmbeddingService migrado a EmbeddingClientInterface

### Commit 5: Wire + deprecate
- services.yaml: aliases
- ClaudeApiClient, OpenAiApiClient: @deprecated
