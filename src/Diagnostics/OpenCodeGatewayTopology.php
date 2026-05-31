<?php

declare(strict_types=1);

namespace Chubes4\OpenCodeAiProvider\Diagnostics;

use Chubes4\OpenCodeAiProvider\Provider\OpenCodeProvider;

/**
 * Validates WP AI Gateway/OpenCode routing topology.
 */
final class OpenCodeGatewayTopology
{
    public const CODE_ALLOWED = 'allowed';
    public const CODE_RECURSIVE_GATEWAY_ROUTE = 'recursive_gateway_route';

    /**
     * Validates whether a gateway/provider/client topology is safe.
     *
     * This intentionally checks only the known-bad recursive shape: the
     * gateway is routing to this provider, the OpenCode runtime points at the
     * same gateway, and this provider's upstream target also resolves back to
     * that gateway. External OpenCode Zen/Go targets remain allowed.
     *
     * @param array<string, mixed> $topology Topology values.
     * @return array<string, mixed> Diagnostic result.
     */
    public static function validate(array $topology): array
    {
        $gatewayProvider = self::stringValue($topology, 'gateway_provider');
        if ($gatewayProvider !== OpenCodeProvider::PROVIDER_ID) {
            return self::allowed('gateway_provider_not_opencode');
        }

        $gatewayBaseUrl = self::stringValue($topology, 'gateway_base_url');
        if ($gatewayBaseUrl === '') {
            return self::allowed('gateway_base_url_missing');
        }

        $opencodeRuntimeBaseUrl = self::stringValue($topology, 'opencode_runtime_base_url');
        if ($opencodeRuntimeBaseUrl === '' || !self::sameEndpoint($opencodeRuntimeBaseUrl, $gatewayBaseUrl)) {
            return self::allowed('opencode_runtime_not_using_gateway');
        }

        $opencodeProviderBaseUrl = self::stringValue($topology, 'opencode_provider_base_url');
        if ($opencodeProviderBaseUrl === '') {
            $opencodeProviderBaseUrl = OpenCodeProvider::GO_BASE_URL;
        }

        if (!self::sameEndpoint($opencodeProviderBaseUrl, $gatewayBaseUrl)) {
            return self::allowed('opencode_provider_uses_external_upstream');
        }

        return [
            'ok' => false,
            'severity' => 'error',
            'code' => self::CODE_RECURSIVE_GATEWAY_ROUTE,
            'message' => 'Recursive WP AI Gateway/OpenCode routing detected: OpenCode is using the gateway as its base URL while the gateway opencode provider target resolves back to the same gateway.',
            'details' => [
                'gateway_provider' => $gatewayProvider,
                'gateway_base_url' => self::normalizeEndpoint($gatewayBaseUrl),
                'opencode_runtime_base_url' => self::normalizeEndpoint($opencodeRuntimeBaseUrl),
                'opencode_provider_base_url' => self::normalizeEndpoint($opencodeProviderBaseUrl),
            ],
        ];
    }

    /**
     * Returns whether the topology is safe.
     *
     * @param array<string, mixed> $topology Topology values.
     * @return bool True when safe.
     */
    public static function isAllowed(array $topology): bool
    {
        $diagnostic = self::validate($topology);

        return isset($diagnostic['ok']) && $diagnostic['ok'] === true;
    }

    /**
     * Builds an allowed diagnostic.
     *
     * @param string $reason Reason code.
     * @return array<string, mixed>
     */
    private static function allowed(string $reason): array
    {
        return [
            'ok' => true,
            'severity' => 'none',
            'code' => self::CODE_ALLOWED,
            'reason' => $reason,
        ];
    }

    /**
     * Reads a string value from the topology map.
     *
     * @param array<string, mixed> $topology Topology values.
     * @param string               $key Value key.
     * @return string Value or empty string.
     */
    private static function stringValue(array $topology, string $key): string
    {
        if (!isset($topology[$key]) || !is_string($topology[$key])) {
            return '';
        }

        return trim($topology[$key]);
    }

    /**
     * Returns whether two URLs point to the same endpoint.
     *
     * @param string $a First URL.
     * @param string $b Second URL.
     * @return bool True when equivalent.
     */
    private static function sameEndpoint(string $a, string $b): bool
    {
        $normalizedA = self::normalizeEndpoint($a);
        $normalizedB = self::normalizeEndpoint($b);

        return $normalizedA !== '' && $normalizedA === $normalizedB;
    }

    /**
     * Normalizes a URL enough for loop detection without retaining secrets.
     *
     * @param string $url URL to normalize.
     * @return string Normalized endpoint or empty string.
     */
    private static function normalizeEndpoint(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $scheme = isset($parts['scheme']) && is_string($parts['scheme']) ? strtolower($parts['scheme']) : 'https';
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':' . (string) $parts['port'] : '';
        $path = isset($parts['path']) && is_string($parts['path']) ? '/' . trim($parts['path'], '/') : '';

        if ($path === '/') {
            $path = '';
        }

        return $scheme . '://' . $host . $port . $path;
    }
}
