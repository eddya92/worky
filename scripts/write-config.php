<?php

declare(strict_types=1);

/**
 * Scrive .worky.json a partire dai campi decisi durante /worky:onboard.
 *
 *   echo '{"test": "composer test"}' | php scripts/write-config.php [dir]
 *
 * Esiste per chiudere la giuntura fra chi scrive la configurazione e chi la
 * rilegge: i nomi delle chiavi vengono dal codice che le usa, non da un
 * modello che interpreta una prosa. `schema_version` è imposto, non chiesto.
 *
 * Nessun autoload di Composer: funziona anche da plugin installato.
 */

require_once __DIR__ . '/../src/MissingConfigException.php';
require_once __DIR__ . '/../src/Config.php';

use Worky\Config;

$projectDir = rtrim($argv[1] ?? (getenv('CLAUDE_PROJECT_DIR') ?: getcwd()), '/');

if (!is_dir($projectDir)) {
    fwrite(STDERR, sprintf("worky: la directory %s non esiste.\n", $projectDir));
    exit(1);
}

try {
    $data = json_decode((string) stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    fwrite(STDERR, sprintf(
        "worky: i campi da scrivere non sono JSON valido (%s).\n",
        $exception->getMessage(),
    ));
    exit(1);
}

if (!is_array($data) || ($data !== [] && array_is_list($data))) {
    fwrite(STDERR, "worky: attesi i campi come oggetto JSON su stdin.\n");
    exit(1);
}

// Imposto, non negoziabile, e primo nel file.
$data = ['schema_version' => 1] + $data;

try {
    $path = Config::write($projectDir, $data);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

fwrite(STDOUT, sprintf("worky: scritto %s\n", $path));
exit(0);
