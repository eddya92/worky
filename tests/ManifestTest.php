<?php

declare(strict_types=1);

namespace Worky\Tests;

use PHPUnit\Framework\TestCase;

final class ManifestTest extends TestCase
{
    public function testManifestIsValidJsonAndDeclaresThePlugin(): void
    {
        $path = __DIR__ . '/../.claude-plugin/plugin.json';
        self::assertFileExists($path, 'Il manifest del plugin deve esistere');

        $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('worky', $manifest['name']);
        self::assertNotEmpty($manifest['version']);
        self::assertNotEmpty($manifest['description']);
    }

    /**
     * Il manifest ha annunciato a lungo cinque agenti di ruolo che il plugin
     * non contiene e che la spec esclude esplicitamente.
     */
    public function testIlManifestNonAnnunciaAgentiCheIlPluginNonContiene(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(__DIR__ . '/../.claude-plugin/plugin.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $descrizione = strtolower($manifest['description']);

        foreach (['analyst', 'backend', 'frontend', 'reviewer'] as $ruolo) {
            self::assertStringNotContainsString($ruolo, $descrizione);
        }

        self::assertDirectoryDoesNotExist(__DIR__ . '/../agents');
    }

    /**
     * La copia installata sta in cache sotto la versione: se i due manifesti
     * non concordano, un aggiornamento non propaga i file nuovi e la sessione
     * continua a caricare i vecchi, senza dire niente a nessuno.
     */
    public function testIDueManifestiDichiaranoLaStessaVersione(): void
    {
        $plugin = json_decode(
            (string) file_get_contents(__DIR__ . '/../.claude-plugin/plugin.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $marketplace = json_decode(
            (string) file_get_contents(__DIR__ . '/../.claude-plugin/marketplace.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $dichiarate = array_column($marketplace['plugins'], 'version', 'name');

        self::assertSame(
            $plugin['version'],
            $dichiarate[$plugin['name']] ?? null,
            'plugin.json e marketplace.json devono dichiarare la stessa versione',
        );
    }
}
