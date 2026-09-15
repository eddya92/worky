<?php

declare(strict_types=1);

namespace Worky\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Worky\ProjectFacts;

final class ProjectFactsTest extends TestCase
{
    /** @var list<string> */
    private array $dirs = [];

    protected function tearDown(): void
    {
        foreach ($this->dirs as $dir) {
            @unlink($dir . '/composer.json');
            @rmdir($dir);
        }

        $this->dirs = [];
    }

    private function observe(string $fixture): array
    {
        return (new ProjectFacts(__DIR__ . '/fixtures/' . $fixture))->observe();
    }

    /** Osserva un progetto il cui composer.json ha la forma data. */
    private function observeComposer(string $contents): array
    {
        $dir = sys_get_temp_dir() . '/worky-facts-' . bin2hex(random_bytes(6));
        mkdir($dir);
        $this->dirs[] = $dir;
        file_put_contents($dir . '/composer.json', $contents);

        return (new ProjectFacts($dir))->observe();
    }

    public function testOsservaFrameworkStackEVersioni(): void
    {
        $facts = $this->observe('symfony-full');

        self::assertSame('symfony', $facts['framework']);
        self::assertSame('symfony-twig-stimulus', $facts['stack']);
        self::assertSame('8.3', $facts['php']);
        self::assertSame('7.2', $facts['framework_version']);
    }

    public function testOsservaGliStrumentiPresenti(): void
    {
        $tools = $this->observe('symfony-full')['tools'];

        self::assertTrue($tools['phpunit']);
        self::assertTrue($tools['phpstan']);
        self::assertTrue($tools['php_cs_fixer']);
    }

    public function testElencaGliScriptComposerEITargetMake(): void
    {
        $facts = $this->observe('symfony-full');

        self::assertSame(['test', 'lint'], $facts['composer_scripts']);
        self::assertSame(['test', 'fixtures'], $facts['make_targets']);
    }

    public function testMappaSoloIPercorsiCheEsistonoDavvero(): void
    {
        $paths = $this->observe('symfony-full')['paths'];

        self::assertSame('src/Entity', $paths['entity']);
        self::assertSame('templates', $paths['templates']);
    }

    public function testRiconosceIlFrameworkMaLasciaLoStackNulloSenzaPacchetto(): void
    {
        $facts = $this->observe('laravel-basic');

        self::assertSame('laravel', $facts['framework']);
        self::assertNull($facts['stack'], 'Nella v1 non esiste un pacchetto Laravel');
    }

    public function testNonInventaNullaSuUnProgettoVuoto(): void
    {
        $facts = $this->observe('vuoto');

        self::assertNull($facts['framework']);
        self::assertNull($facts['php']);
        self::assertSame([], $facts['composer_scripts']);
        self::assertSame([], $facts['make_targets']);
        self::assertNull($facts['paths']['entity']);
    }

    public function testDichiaraLoStatoDelComposerJsonQuandoEValido(): void
    {
        self::assertSame('ok', $this->observe('symfony-full')['composer_json']);
    }

    public function testDichiaraLoStatoDelComposerJsonQuandoManca(): void
    {
        self::assertSame('assente', $this->observe('vuoto')['composer_json']);
    }

    /** @return iterable<string, array{0: string}> */
    public static function composerMalformati(): iterable
    {
        yield 'json troncato' => ['{"require": {'];
        yield 'scalare valido' => ['"hello"'];
        yield 'lista invece di oggetto' => ['[1, 2, 3]'];
    }

    /**
     * Un composer.json illeggibile non deve essere riportato come assenza di
     * fatti: l'assenza porta l'intervista a non chiedere nulla proprio dove
     * dovrebbe chiedere di piu'.
     */
    #[DataProvider('composerMalformati')]
    public function testDichiaraIlComposerJsonIlleggibileSenzaEsplodere(string $contents): void
    {
        $facts = $this->observeComposer($contents);

        self::assertSame('illeggibile', $facts['composer_json']);
        self::assertNull($facts['framework']);
        self::assertNull($facts['php']);
        self::assertSame([], $facts['composer_scripts']);
    }

    /** @return iterable<string, array{0: string}> */
    public static function composerConCampiDiTipoInatteso(): iterable
    {
        yield 'scripts stringa' => ['{"scripts": "nope"}'];
        yield 'require stringa' => ['{"require": "nope"}'];
        yield 'vincolo php lista' => ['{"require": {"php": ["8.2"]}}'];
        yield 'require lista' => ['{"require": ["symfony/framework-bundle"]}'];
    }

    #[DataProvider('composerConCampiDiTipoInatteso')]
    public function testSopravviveAiCampiDiTipoInatteso(string $contents): void
    {
        $facts = $this->observeComposer($contents);

        self::assertSame('ok', $facts['composer_json'], 'Un oggetto JSON resta leggibile, anche se i campi sorprendono');
        self::assertNull($facts['framework']);
        self::assertNull($facts['php']);
        self::assertSame([], $facts['composer_scripts']);
    }
}
