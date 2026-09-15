<?php

declare(strict_types=1);

/**
 * PreToolUse su Bash: prima di aprire una pull request esegue i comandi di
 * qualità dichiarati in .worky.json. Nessun autoload di Composer.
 */

require_once __DIR__ . '/../src/MissingConfigException.php';
require_once __DIR__ . '/../src/Config.php';

use Worky\Config;
use Worky\MissingConfigException;

$input = json_decode((string) stream_get_contents(STDIN), true);

if (!is_array($input) || ($input['tool_name'] ?? '') !== 'Bash') {
    exit(0);
}

$command = $input['tool_input']['command'] ?? '';

if (!is_string($command) || preg_match('/\bgh\s+pr\s+create\b/', $command) !== 1) {
    exit(0);
}

$projectDir = getenv('CLAUDE_PROJECT_DIR') ?: getcwd();

try {
    $config = Config::load($projectDir);
} catch (MissingConfigException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(2);
}

foreach (['test', 'static_analysis'] as $key) {
    $gate = $config[$key] ?? null;

    if (!is_string($gate) || $gate === '') {
        continue;
    }

    $output = [];
    exec(sprintf('cd %s && %s 2>&1', escapeshellarg($projectDir), $gate), $output, $code);

    if ($code !== 0) {
        fwrite(STDERR, sprintf(
            "worky: gate '%s' fallito prima della pull request.\nComando: %s\n%s\n"
            . "Sistema il problema e riprova: la PR non viene aperta con il rosso.\n",
            $key,
            $gate,
            implode("\n", array_slice($output, -30)),
        ));
        exit(2);
    }
}

exit(0);
