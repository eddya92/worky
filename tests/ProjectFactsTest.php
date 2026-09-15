<?php

declare(strict_types=1);

namespace Worky\Tests;

use PHPUnit\Framework\TestCase;
use Worky\ProjectFacts;

final class ProjectFactsTest extends TestCase
{
    private function observe(string $fixture): array
    {
        return (new ProjectFacts(__DIR__ . '/fixtures/' . $fixture))->observe();
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
}
