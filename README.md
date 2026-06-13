# AI Provider for OpenCode

WordPress AI Client provider for OpenCode Zen and [OpenCode Go](https://opencode.ai/zen/go), a low-cost option for AI tokens.

This plugin registers one provider ID, `opencode`, and discovers text-generation models from both OpenCode surfaces.

## Requirements

- WordPress 7.0 or newer, including the bundled WordPress AI Client
- PHP 7.4 or newer
- An OpenCode API key

## Usage

Install and activate this plugin on a WordPress 7.0+ site. Configure the `opencode` provider API key anywhere your AI Client integration stores provider credentials, then select one of the `opencode/*` or `opencode-go/*` model IDs. Use `opencode-go/*` models when you want the lower-cost OpenCode Go token path.

`opencode/*` requests go to `https://opencode.ai/zen/v1/chat/completions`.

`opencode-go/*` requests go to `https://opencode.ai/zen/go/v1/chat/completions`.

## Development

This is intentionally small: it uses the AI Client's OpenAI-compatible chat-completions implementation, discovers local OpenCode state when available, and keeps static fallback metadata for unauthenticated model lookup. More models can be added with the `ai_provider_for_opencode_model_metadata` filter.

Run the local smoke test:

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/smoke.php
```

If your local OpenCode auth state has an `opencode-go` API key, run the live smoke test:

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/live-opencode-go.php
```
