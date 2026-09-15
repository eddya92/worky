<?php

declare(strict_types=1);

/**
 * Stampa in JSON i fatti osservabili di un progetto.
 *
 *   php scripts/observe.php [dir]
 *
 * Nessun autoload di Composer: funziona anche da plugin installato.
 */

require_once __DIR__ . '/../src/ProjectFacts.php';

use Worky\ProjectFacts;

$projectDir = rtrim($argv[1] ?? (getenv('CLAUDE_PROJECT_DIR') ?: getcwd()), '/');

if (!is_dir($projectDir)) {
    fwrite(STDERR, sprintf("worky: la directory %s non esiste.\n", $projectDir));
    exit(1);
}

$facts = (new ProjectFacts($projectDir))->observe();

fwrite(STDOUT, json_encode($facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
exit(0);
