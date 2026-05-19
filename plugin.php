<?php

/**
 * Plugin Name: AI Provider for OpenCode
 * Plugin URI: https://github.com/chubes4/ai-provider-for-opencode
 * Description: OpenCode Zen and OpenCode Go provider for the WordPress AI Client.
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Version: 0.1.0
 * Author: Chris Huber
 * Author URI: https://github.com/chubes4
 * License: GPL-2.0-or-later
 * License URI: https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain: ai-provider-for-opencode
 *
 * @package Chubes4\OpenCodeAiProvider
 */

declare(strict_types=1);

namespace Chubes4\OpenCodeAiProvider;

use Chubes4\OpenCodeAiProvider\Provider\OpenCodeProvider;
use WordPress\AiClient\AiClient;

if (!defined('ABSPATH')) {
    return;
}

require_once __DIR__ . '/src/autoload.php';

/**
 * Registers the OpenCode provider with the WordPress AI Client.
 *
 * @return void
 */
function register_provider(): void
{
    if (!class_exists(AiClient::class)) {
        return;
    }

    $registry = AiClient::defaultRegistry();

    if ($registry->hasProvider(OpenCodeProvider::class)) {
        return;
    }

    $registry->registerProvider(OpenCodeProvider::class);
}

add_action('init', __NAMESPACE__ . '\\register_provider', 5);
