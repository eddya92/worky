<?php

declare(strict_types=1);

/**
 * La dashboard: guarda il diario della sessione mentre accade.
 *
 *   php scripts/dashboard.php [directory-del-progetto] [porta]
 *
 * Lo stesso file fa due mestieri. Lanciato da riga di comando avvia il server
 * di sviluppo di PHP; lanciato *dal* server fa da router. Un file solo perché
 * la dashboard è una cosa sola, e perché così non c'è nulla da installare.
 *
 * Nessun autoload di Composer: gira anche da plugin installato.
 */

require_once __DIR__ . '/../src/Dashboard.php';

use Worky\Dashboard;

const PORTA_PREDEFINITA = 8787;

if (PHP_SAPI === 'cli') {
    $projectDir = rtrim($argv[1] ?? getcwd(), '/');
    $porta = (int) ($argv[2] ?? PORTA_PREDEFINITA);

    if (!is_dir($projectDir)) {
        fwrite(STDERR, sprintf("worky: la directory %s non esiste.\n", $projectDir));
        exit(1);
    }

    fwrite(STDOUT, sprintf(
        "worky: dashboard di %s su http://127.0.0.1:%d\nPremi Ctrl-C per fermarla.\n",
        $projectDir,
        $porta,
    ));

    $comando = sprintf(
        'WORKY_PROJECT_DIR=%s %s -S 127.0.0.1:%d %s',
        escapeshellarg($projectDir),
        escapeshellarg(PHP_BINARY),
        $porta,
        escapeshellarg(__FILE__),
    );

    passthru($comando, $esito);
    exit($esito);
}

// Da qui in poi siamo il router del server.
$projectDir = getenv('WORKY_PROJECT_DIR') ?: getcwd();
$percorso = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($percorso === '/events') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo Dashboard::events($projectDir);
    exit;
}

if ($percorso === '/') {
    header('Content-Type: text/html; charset=utf-8');
    echo Dashboard::page();
    exit;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "worky: non c'è niente qui.\n";
