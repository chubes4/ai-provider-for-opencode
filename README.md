# AI Provider for OpenCode

WordPress AI Client provider for OpenCode Zen and OpenCode Go.

This plugin registers one provider ID, `opencode`, and exposes text-generation models for both OpenCode surfaces:

- `opencode/kimi-k2.6`
- `opencode/kimi-k2.5`
- `opencode/qwen3.6-plus`
- `opencode/qwen3.5-plus`
- `opencode/glm-5.1`
- `opencode/glm-5`
- `opencode-go/kimi-k2.6`
- `opencode-go/kimi-k2.5`
- `opencode-go/qwen3.6-plus`
- `opencode-go/qwen3.5-plus`
- `opencode-go/glm-5.1`
- `opencode-go/glm-5`
- `opencode-go/deepseek-v4-pro`
- `opencode-go/deepseek-v4-flash`

## Requirements

- WordPress 6.9 or newer
- PHP 7.4 or newer
- WordPress AI Client
- An OpenCode API key

## Usage

Install and activate this plugin alongside the WordPress AI Client. Configure the `opencode` provider API key anywhere your AI Client integration stores provider credentials, then select one of the `opencode/*` or `opencode-go/*` model IDs.

`opencode/*` requests go to `https://opencode.ai/zen/v1/chat/completions`.

`opencode-go/*` requests go to `https://opencode.ai/zen/go/v1/chat/completions`.

## Sandbox Runtime

This plugin is intended to be the API-key provider surface for Sandbox Runtime minions. The runner should pass a connector or credential reference from the parent site, resolve it on the parent side, and inject only the scoped sandbox credential needed for the run.

Do not pass raw API keys in task payloads or artifact metadata.

## Development

This is intentionally small: it uses the AI Client's OpenAI-compatible chat-completions implementation and hardcoded model metadata. More models can be added with the `ai_provider_for_opencode_model_metadata` filter.

Run the local smoke test:

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/smoke.php
```

If your local OpenCode auth state has an `opencode-go` API key, run the live smoke test:

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/live-opencode-go.php
```
