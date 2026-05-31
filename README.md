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

## WP AI Gateway topology checks

This provider targets fixed external OpenCode Zen and OpenCode Go upstreams. If another plugin, such as WP AI Gateway or wp-coding-agents, brokers OpenCode runtime traffic, it can call `Chubes4\OpenCodeAiProvider\Diagnostics\OpenCodeGatewayTopology::validate()` before enabling a route.

The diagnostic rejects only the known recursive topology:

- WP AI Gateway provider is `opencode`
- the local OpenCode runtime `baseURL` points at the same gateway
- the opencode provider upstream target also resolves back to that same gateway

Gateway `provider=codex` and gateway `provider=opencode` routes to external OpenCode Zen/Go upstreams are allowed. Provider requests also include route attribution headers, `X-AI-Provider-For-OpenCode-Route` and `X-AI-Provider-For-OpenCode-Upstream`, so gateway logs can distinguish local runtime, gateway-brokered, and external OpenCode Go traffic without logging secrets.

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
