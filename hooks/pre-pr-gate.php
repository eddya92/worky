<?php

declare(strict_types=1);

/**
 * PreToolUse su Bash: prima di aprire una pull request esegue i comandi di
 * qualità dichiarati in .worky.json. Nessun autoload di Composer.
 *
 * Il gate fallisce chiuso: solo l'uscita con codice 2 blocca un PreToolUse,
 * quindi qualunque errore dello script — un include mancante dopo un
 * aggiornamento parziale, exec() disabilitata, uno scarto di versione —
 * deve bloccare la pull request invece di lasciarla passare in silenzio.
 */

register_shutdown_function(static function (): void {
    $e = error_get_last();
    if ($e !== null && ($e['type'] & (E_ERROR | E_PARSE | E_COMPILE_ERROR)) !== 0) {
        fwrite(STDERR, "worky: il gate non ha potuto eseguire i controlli. PR bloccata per sicurezza.\n");
        exit(2);
    }
});

require_once __DIR__ . '/../src/MissingConfigException.php';
require_once __DIR__ . '/../src/Config.php';

use Worky\Config;
use Worky\MissingConfigException;

try {
    $input = json_decode((string) stream_get_contents(STDIN), true);

    if (!is_array($input) || ($input['tool_name'] ?? '') !== 'Bash') {
        exit(0);
    }

    $command = $input['tool_input']['command'] ?? '';

    // Nessun ancoraggio alla posizione di comando: enumerare tutto ciò che può
    // legittimamente precedere un comando (if, for, while, {, sudo, env, time,
    // xargs, sh -c, un percorso assoluto...) non ha forma chiusa in shell, e su
    // un gate di sicurezza un falso negativo costa molto più di un falso
    // positivo. Si cerca quindi la sequenza di token ovunque compaia: anche una
    // menzione della frase attiva il gate, ed è il prezzo accettato.
    // Il secondo pattern chiude l'aggiramento via API REST invece della porcelain.
    $triggers = [
        '/(?<![\w.-])(?:[\w.\/-]*\/)?gh\s+pr\s+create\b/',
        '/\bgh\s+api\b.*\bpulls\b/',
    ];

    $apreUnaPullRequest = false;

    if (is_string($command)) {
        foreach ($triggers as $trigger) {
            if (preg_match($trigger, $command) === 1) {
                $apreUnaPullRequest = true;
                break;
            }
        }
    }

    if (!$apreUnaPullRequest) {
        exit(0);
    }

    // La directory in cui gira il comando, non quella in cui è partita la
    // sessione: con i worktree git sono diverse, e testare il checkout
    // sbagliato vorrebbe dire aprire una PR su codice mai verificato.
    $projectDir = $input['cwd'] ?? (getenv('CLAUDE_PROJECT_DIR') ?: getcwd());

    try {
        $config = Config::load($projectDir);
    } catch (MissingConfigException $exception) {
        fwrite(STDERR, $exception->getMessage() . "\n");
        exit(2);
    }

    foreach (['test', 'static_analysis'] as $key) {
        $gate = $config[$key] ?? null;

        if (!is_string($gate) || $gate === '') {
            // Saltare in silenzio nasconde una chiave sbagliata: metà gate
            // eseguito e riportato come successo. Il salto va visto.
            fwrite(STDERR, sprintf("worky: gate '%s' non configurato, saltato.\n", $key));
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
} catch (\Throwable $exception) {
    fwrite(STDERR, sprintf(
        "worky: il gate non ha potuto eseguire i controlli (%s). PR bloccata per sicurezza.\n",
        $exception->getMessage(),
    ));
    exit(2);
}
