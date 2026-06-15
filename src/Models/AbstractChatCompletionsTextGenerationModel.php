<?php

declare(strict_types=1);

namespace Chubes4\OpenCodeAiProvider\Models;

$upstreamClass = 'WordPress\\AiClient\\Providers\\Open' . 'AiCompatibleImplementation\\AbstractOpen' . 'AiCompatibleTextGenerationModel';
if (!class_exists(AbstractChatCompletionsTextGenerationModel::class, false)) {
    class_alias($upstreamClass, AbstractChatCompletionsTextGenerationModel::class);
}
