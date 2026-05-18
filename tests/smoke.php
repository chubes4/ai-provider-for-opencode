<?php

declare(strict_types=1);

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, $value)
    {
        return $value;
    }
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/autoload.php';

use Chubes4\OpenCodeAiProvider\Models\OpenCodeTextGenerationModel;
use Chubes4\OpenCodeAiProvider\Provider\OpenCodeProvider;

$metadata = OpenCodeProvider::metadata();
assert($metadata->getId() === 'opencode');

$directory = OpenCodeProvider::modelMetadataDirectory();
$models    = $directory->listModelMetadata();
assert(count($models) === 4);
assert($directory->hasModelMetadata('opencode/kimi-k2.6'));
assert($directory->hasModelMetadata('opencode-go/kimi-k2.6'));

assert(OpenCodeProvider::baseUrlForModel('opencode/kimi-k2.6') === OpenCodeProvider::ZEN_BASE_URL);
assert(OpenCodeProvider::baseUrlForModel('opencode-go/kimi-k2.6') === OpenCodeProvider::GO_BASE_URL);

$zenModel = OpenCodeProvider::model('opencode/kimi-k2.6');
$goModel  = OpenCodeProvider::model('opencode-go/kimi-k2.6');
assert($zenModel instanceof OpenCodeTextGenerationModel);
assert($goModel instanceof OpenCodeTextGenerationModel);

fwrite(STDOUT, "OpenCode provider smoke passed.\n");
