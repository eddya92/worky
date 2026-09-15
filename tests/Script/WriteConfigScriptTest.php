<?php

declare(strict_types=1);

namespace Worky\Tests\Script;

use PHPUnit\Framework\TestCase;
use Worky\Config;

/**
 * Lo script è il punto in cui l'intervista diventa configurazione: va provato
 * come processo vero, perché è così che verrà eseguito.
 */
final class WriteConfigScriptTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/worky-write-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/' . Config::FILENAME);
        @rmdir($this->dir);
    }

    /** @return array{0: int, 1: string, 2: string} codice, stdout, stderr */
    private function runScript(string $stdin, ?string $dir = null): array
    {
        $script = __DIR__ . '/../../scripts/write-config.php';
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(['php', $script, $dir ?? $this->dir], $descriptors, $pipes);

        fwrite($pipes[0], $stdin);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }

    public function testScriveIcampiDecisiELiRendeRileggibiliDalGate(): void
    {
        [$code, $stdout] = $this->runScript(json_encode([
            'stack' => 'symfony-twig-stimulus',
            'test' => 'make test',
            'static_analysis' => 'vendor/bin/phpstan analyse',
        ], JSON_THROW_ON_ERROR));

        self::assertSame(0, $code);
        self::assertStringContainsString(Config::FILENAME, $stdout);

        $config = Config::load($this->dir);
        self::assertSame('make test', $config['test']);
        self::assertSame('vendor/bin/phpstan analyse', $config['static_analysis']);
        self::assertSame('symfony-twig-stimulus', $config['stack']);
    }

    public function testImponeLoSchemaVersionQualunqueCosaArrivi(): void
    {
        [$code] = $this->runScript('{"schema_version": 7, "test": "composer test"}');

        self::assertSame(0, $code);
        self::assertSame(1, Config::load($this->dir)['schema_version']);
    }

    public function testRifiutaUnInputCheNonEJsonValido(): void
    {
        [$code, , $stderr] = $this->runScript('{non e json');

        self::assertSame(1, $code);
        self::assertStringContainsString('worky:', $stderr);
        self::assertFileDoesNotExist($this->dir . '/' . Config::FILENAME);
    }

    public function testRifiutaUnInputCheNonEUnOggetto(): void
    {
        [$code, , $stderr] = $this->runScript('"composer test"');

        self::assertSame(1, $code);
        self::assertStringContainsString('worky:', $stderr);
        self::assertFileDoesNotExist($this->dir . '/' . Config::FILENAME);
    }

    public function testRifiutaUnaDirectoryCheNonEsiste(): void
    {
        [$code, , $stderr] = $this->runScript('{"test": "composer test"}', $this->dir . '/manca');

        self::assertSame(1, $code);
        self::assertStringContainsString('worky:', $stderr);
    }
}
