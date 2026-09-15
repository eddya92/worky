<?php

declare(strict_types=1);

namespace Worky;

final class Config
{
    public const FILENAME = '.worky.json';

    /**
     * @throws \JsonException se i dati non sono codificabili
     * @throws \RuntimeException se il file non si riesce a scrivere
     */
    public static function write(string $projectDir, array $data): string
    {
        $path = $projectDir . '/' . self::FILENAME;
        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        // La @ sostituisce un warning ignorabile con un errore che si vede:
        // un .worky.json vuoto o troncato è peggio di una scrittura fallita.
        if (@file_put_contents($path, $json . "\n") === false) {
            throw new \RuntimeException(sprintf(
                'worky: impossibile scrivere %s. Controlla i permessi e che la directory esista.',
                $path,
            ));
        }

        return $path;
    }

    public static function load(string $projectDir): array
    {
        $path = $projectDir . '/' . self::FILENAME;

        if (!is_file($path)) {
            throw new MissingConfigException(sprintf(
                'worky: %s non trovato in %s. Esegui /worky:onboard per configurare il progetto.',
                self::FILENAME,
                $projectDir,
            ));
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (!is_array($data)) {
            throw new MissingConfigException(sprintf(
                'worky: %s non è un JSON valido. Correggilo o rilancia /worky:onboard.',
                $path,
            ));
        }

        return $data;
    }
}
