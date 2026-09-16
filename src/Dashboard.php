<?php

declare(strict_types=1);

namespace Worky;

require_once __DIR__ . '/EventLog.php';

/**
 * Serve il diario della sessione a una pagina che lo guarda mentre accade.
 *
 * Tutto in una pagina sola, senza dipendenze esterne: il browser non scarica
 * nulla da internet, e chi la apre vede solo ciò che è successo qui.
 */
final class Dashboard
{
    public static function events(string $projectDir, int $limit = 200): string
    {
        $eventi = EventLog::tail($projectDir, $limit);

        $sessioni = array_filter(array_unique(array_column($eventi, 'session')));

        // Un agente non è una sessione: i subagenti condividono la sessione del
        // padre e si distinguono solo per la propria trascrizione.
        $agenti = array_filter(array_unique(array_column($eventi, 'agente')));

        return (string) json_encode([
            'eventi' => $eventi,
            'sessioni' => count($sessioni),
            'agenti' => count($agenti),
            'aggiornato' => date('c'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function page(): string
    {
        return (string) file_get_contents(__DIR__ . '/../scripts/dashboard.html');
    }
}
