<?php

declare(strict_types=1);

/**
 * Registra ogni evento della sessione nel diario del progetto.
 *
 * Al contrario del gate, questo hook non deve MAI bloccare niente: esce sempre
 * con codice 0, anche quando non capisce l'input o non riesce a scrivere. Un
 * diario che si rompe non è una buona ragione per fermare il lavoro.
 *
 * Nessun autoload: gira anche in un plugin installato, che non ha vendor/.
 */

require_once __DIR__ . '/../src/EventLog.php';

use Worky\EventLog;

const BERSAGLIO_MAX = 160;

try {
    $input = json_decode((string) stream_get_contents(STDIN), true);

    if (!is_array($input)) {
        exit(0);
    }

    $toolInput = is_array($input['tool_input'] ?? null) ? $input['tool_input'] : [];

    // Il bersaglio è ciò su cui lo strumento ha agito: un comando, un file, o —
    // quando si lancia un subagente — il compito che gli è stato dato. Il suo
    // prompt invece non si registra mai: è lungo e spesso contiene lavoro in
    // corso che non ha motivo di finire in un file sul disco.
    $target = $toolInput['command'] ?? $toolInput['file_path'] ?? $toolInput['description'] ?? null;

    if (is_string($target) && mb_strlen($target) > BERSAGLIO_MAX) {
        $target = mb_substr($target, 0, BERSAGLIO_MAX - 1) . '…';
    }

    $projectDir = $input['cwd'] ?? (getenv('CLAUDE_PROJECT_DIR') ?: getcwd());

    if (!is_string($projectDir) || !is_dir($projectDir)) {
        exit(0);
    }

    // Ogni agente ha la propria trascrizione: il nome di quel file è l'unica
    // cosa che distingue un subagente dalla sessione che lo ha lanciato, dato
    // che session_id resta quello del padre.
    $trascrizione = $input['transcript_path'] ?? null;
    $agente = is_string($trascrizione) && $trascrizione !== ''
        ? pathinfo($trascrizione, PATHINFO_FILENAME)
        : null;

    EventLog::append($projectDir, [
        'event' => is_string($input['hook_event_name'] ?? null) ? $input['hook_event_name'] : 'sconosciuto',
        'tool' => is_string($input['tool_name'] ?? null) ? $input['tool_name'] : null,
        'target' => is_string($target) ? $target : null,
        'tipo' => is_string($toolInput['subagent_type'] ?? null) ? $toolInput['subagent_type'] : null,
        'agente' => $agente,
        'session' => is_string($input['session_id'] ?? null) ? $input['session_id'] : null,
    ]);
} catch (\Throwable) {
    // Volutamente silenzioso: vedi l'intestazione.
}

exit(0);
