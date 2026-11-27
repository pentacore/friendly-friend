<?php
config()->set('ai.providers.anthropic.key', 'test-key');
App\Ai\Agents\QuoteNormalizerAgent::fake([['lines' => [['speaker' => 'Bo', 'text' => 'x']], 'context' => null, 'said_at' => null, 'said_at_precision' => null]]);
try {
    $r = (new App\Ai\Agents\QuoteNormalizerAgent)->prompt('hi', provider: ['anthropic'], timeout: 15);
    var_dump(get_class($r), $r->toArray());
} catch (Throwable $e) { echo get_class($e), ': ', $e->getMessage(), "\n"; }
