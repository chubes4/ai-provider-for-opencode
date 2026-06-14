<?php

declare(strict_types=1);

namespace Chubes4\OpenCodeAiProvider\Support;

/**
 * Reads local OpenCode/Kimaki state without exposing credential values.
 */
class OpenCodeLocalState
{
    /**
     * @return array<int, array{provider:string,model:string,name:string}>
     */
    public static function configuredModels(): array
    {
        $models = [];

        foreach (self::configPaths() as $path) {
            $config = self::readJsonFile($path);
            if (!is_array($config)) {
                continue;
            }

            $models = array_merge($models, self::modelsFromConfig($config));
        }

        $models = array_merge($models, self::recentModels());

        $deduped = [];
        foreach ($models as $model) {
            $key = $model['provider'] . '/' . $model['model'];
            $deduped[$key] = $model;
        }

        ksort($deduped);

        return array_values($deduped);
    }

    public static function apiKeyForSurface(string $surface): string
    {
        $auth = self::readJsonFile(self::authPath());
        if (!is_array($auth)) {
            return '';
        }

        $entry = $auth[$surface] ?? null;
        if (!is_array($entry)) {
            return '';
        }

        $key = $entry['key'] ?? $entry['access'] ?? '';

        return is_string($key) ? $key : '';
    }

    /**
     * @return array<int, string>
     */
    private static function configPaths(): array
    {
        $paths = [];
        $envConfig = getenv('OPENCODE_CONFIG');
        if (is_string($envConfig) && '' !== $envConfig) {
            $paths[] = $envConfig;
        }

        $cwd = getcwd();
        if (is_string($cwd) && '' !== $cwd) {
            $paths[] = rtrim($cwd, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'opencode.json';
        }

        $home = self::homeDir();
        if ('' !== $home) {
            $paths[] = $home . '/.kimaki/opencode-config.json';
            $paths[] = $home . '/.config/opencode/opencode.json';
            $paths[] = $home . '/.config/opencode/opencode.jsonc';
            $paths[] = $home . '/.config/opencode/config.json';
            $paths[] = $home . '/.config/opencode/config.jsonc';
        }

        $xdgConfig = getenv('XDG_CONFIG_HOME');
        if (is_string($xdgConfig) && '' !== $xdgConfig) {
            $paths[] = rtrim($xdgConfig, '/') . '/opencode/opencode.json';
            $paths[] = rtrim($xdgConfig, '/') . '/opencode/opencode.jsonc';
            $paths[] = rtrim($xdgConfig, '/') . '/opencode/config.json';
            $paths[] = rtrim($xdgConfig, '/') . '/opencode/config.jsonc';
        }

        return array_values(array_unique($paths));
    }

    private static function authPath(): string
    {
        $xdgData = getenv('XDG_DATA_HOME');
        if (is_string($xdgData) && '' !== $xdgData) {
            return rtrim($xdgData, '/') . '/opencode/auth.json';
        }

        return self::homeDir() . '/.local/share/opencode/auth.json';
    }

    /**
     * @return array<int, array{provider:string,model:string,name:string}>
     */
    private static function modelsFromConfig(array $config): array
    {
        $models = [];
        $providers = $config['provider'] ?? [];
        if (is_array($providers)) {
            foreach ($providers as $providerId => $providerConfig) {
                if (!is_string($providerId) || !is_array($providerConfig)) {
                    continue;
                }

                $providerModels = $providerConfig['models'] ?? [];
                if (!is_array($providerModels)) {
                    continue;
                }

                foreach ($providerModels as $modelId => $modelConfig) {
                    if (!is_string($modelId) || '' === $modelId) {
                        continue;
                    }

                    $name = $modelId;
                    if (is_array($modelConfig) && is_string($modelConfig['name'] ?? null) && '' !== $modelConfig['name']) {
                        $name = $modelConfig['name'];
                    }

                    $models[] = [
                        'provider' => $providerId,
                        'model' => $modelId,
                        'name' => $name,
                    ];
                }
            }
        }

        foreach (['model', 'small_model'] as $key) {
            if (!is_string($config[$key] ?? null)) {
                continue;
            }

            $model = self::parseProviderModel($config[$key]);
            if (null !== $model) {
                $models[] = $model;
            }
        }

        return $models;
    }

    /**
     * @return array<int, array{provider:string,model:string,name:string}>
     */
    private static function recentModels(): array
    {
        $path = self::statePath('model.json');
        $state = self::readJsonFile($path);
        if (!is_array($state) || !is_array($state['recent'] ?? null)) {
            return [];
        }

        $models = [];
        foreach ($state['recent'] as $recent) {
            if (!is_array($recent) || !is_string($recent['providerID'] ?? null) || !is_string($recent['modelID'] ?? null)) {
                continue;
            }

            $models[] = [
                'provider' => $recent['providerID'],
                'model' => $recent['modelID'],
                'name' => $recent['modelID'],
            ];
        }

        return $models;
    }

    private static function statePath(string $file): string
    {
        $xdgState = getenv('XDG_STATE_HOME');
        if (is_string($xdgState) && '' !== $xdgState) {
            return rtrim($xdgState, '/') . '/opencode/' . $file;
        }

        return self::homeDir() . '/.local/state/opencode/' . $file;
    }

    /**
     * @return array{provider:string,model:string,name:string}|null
     */
    private static function parseProviderModel(string $value): ?array
    {
        $parts = explode('/', $value, 2);
        if (2 !== count($parts) || '' === $parts[0] || '' === $parts[1]) {
            return null;
        }

        return [
            'provider' => $parts[0],
            'model' => $parts[1],
            'name' => $parts[1],
        ];
    }

    /**
     * @return mixed
     */
    private static function readJsonFile(string $path)
    {
        if ('' === $path || !is_file($path) || !is_readable($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if (!is_string($raw) || '' === $raw) {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            $decoded = json_decode(self::stripJsonComments($raw), true);
        }

        return JSON_ERROR_NONE === json_last_error() ? $decoded : null;
    }

    private static function stripJsonComments(string $raw): string
    {
        $result = '';
        $length = strlen($raw);
        $inString = false;
        $escaped = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $raw[$i];
            $next = $raw[$i + 1] ?? '';

            if ($inString) {
                $result .= $char;
                if ($escaped) {
                    $escaped = false;
                } elseif ('\\' === $char) {
                    $escaped = true;
                } elseif ('"' === $char) {
                    $inString = false;
                }
                continue;
            }

            if ('"' === $char) {
                $inString = true;
                $result .= $char;
                continue;
            }

            if ('/' === $char && '/' === $next) {
                while ($i < $length && "\n" !== $raw[$i]) {
                    $i++;
                }
                if ($i < $length) {
                    $result .= $raw[$i];
                }
                continue;
            }

            if ('/' === $char && '*' === $next) {
                $i += 2;
                while ($i < $length && !('*' === $raw[$i] && '/' === ($raw[$i + 1] ?? ''))) {
                    $i++;
                }
                $i++;
                continue;
            }

            $result .= $char;
        }

        return preg_replace('/,\s*([}\]])/', '$1', $result) ?? $result;
    }

    private static function homeDir(): string
    {
        $home = getenv('HOME');

        return is_string($home) ? rtrim($home, '/') : '';
    }
}
