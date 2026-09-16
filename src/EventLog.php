<?php

declare(strict_types=1);

namespace Worky;

/**
 * Il diario di ciò che accade durante una sessione: una riga JSON per evento.
 *
 * Chi scrive è un hook, quindi la scrittura non deve mai fallire in modo
 * rumoroso: un diario rotto non è una buona ragione per fermare il lavoro.
 */
final class EventLog
{
    public const DIRNAME = '.worky';
    public const FILENAME = 'events.jsonl';

    public static function path(string $projectDir): string
    {
        return $projectDir . '/' . self::DIRNAME . '/' . self::FILENAME;
    }

    public static function append(string $projectDir, array $event): void
    {
        $path = self::path($projectDir);
        $directory = dirname($path);

        if (!is_dir($directory) && !@mkdir($directory, 0o777, true) && !is_dir($directory)) {
            return;
        }

        $event['ts'] ??= date('c');

        // JSON_UNESCAPED_UNICODE tiene leggibili gli accenti; gli a capo dentro i
        // valori restano codificati, quindi una riga resta una riga.
        $line = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($line === false) {
            return;
        }

        @file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX);
    }

    /** @return list<array<string, mixed>> dal più vecchio al più recente */
    public static function tail(string $projectDir, int $limit = 200): array
    {
        $path = self::path($projectDir);

        if (!is_file($path)) {
            return [];
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return [];
        }

        $events = [];

        foreach (array_slice($lines, -$limit) as $line) {
            $event = json_decode($line, true);

            if (is_array($event)) {
                $events[] = $event;
            }
        }

        return $events;
    }
}
