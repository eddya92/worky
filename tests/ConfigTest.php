<?php

declare(strict_types=1);

namespace Worky\Tests;

use PHPUnit\Framework\TestCase;
use Worky\Config;
use Worky\MissingConfigException;

final class ConfigTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/worky-config-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/' . Config::FILENAME);
        @rmdir($this->dir);
    }

    public function testScriveELeggeLaConfigurazione(): void
    {
        Config::write($this->dir, ['schema_version' => 1, 'test' => 'composer test']);

        self::assertSame(
            ['schema_version' => 1, 'test' => 'composer test'],
            Config::load($this->dir),
        );
    }

    public function testScriveJsonLeggibileConAcapoFinale(): void
    {
        $path = Config::write($this->dir, ['schema_version' => 1]);
        $contents = (string) file_get_contents($path);

        self::assertStringEndsWith("}\n", $contents);
        self::assertStringContainsString("\n    \"schema_version\"", $contents);
    }

    public function testSegnalaLaScritturaFallitaInveceDiProdurreUnFileVuoto(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/worky: impossibile scrivere/');

        Config::write($this->dir . '/directory-che-non-esiste', ['schema_version' => 1]);
    }

    public function testSegnalaIDatiNonCodificabiliInveceDiTroncareIlFile(): void
    {
        $this->expectException(\JsonException::class);

        Config::write($this->dir, ['schema_version' => 1, 'test' => "\xB1\x31"]);
    }

    public function testLanciaUnEccezioneConIstruzioniQuandoLaConfigurazioneManca(): void
    {
        $this->expectException(MissingConfigException::class);
        $this->expectExceptionMessageMatches('/worky:onboard/');

        Config::load($this->dir);
    }
}
