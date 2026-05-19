# AI Provider for OpenCode

WordPress AI Client provider for OpenCode Zen and [OpenCode Go](https://opencode.ai/zen/go), a low-cost option for AI tokens.

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

- WordPress 7.0 or newer, including the bundled WordPress AI Client
- PHP 7.4 or newer
- An OpenCode API key

## Usage

Install and activate this plugin on a WordPress 7.0+ site. Configure the `opencode` provider API key anywhere your AI Client integration stores provider credentials, then select one of the `opencode/*` or `opencode-go/*` model IDs. Use `opencode-go/*` models when you want the lower-cost OpenCode Go token path.

`opencode/*` requests go to `https://opencode.ai/zen/v1/chat/completions`.

`opencode-go/*` requests go to `https://opencode.ai/zen/go/v1/chat/completions`.

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
