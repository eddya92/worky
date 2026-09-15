<?php

declare(strict_types=1);

namespace Worky\Tests;

use PHPUnit\Framework\TestCase;

/**
 * L'intervista è prosa, ma tiene insieme due giunture che il codice non può
 * chiudere da solo: chi scrive `.worky.json` e chi lo rilegge, e lo stack
 * dichiarato e il pacchetto di convenzioni che dovrebbe caricare.
 */
final class OnboardCommandTest extends TestCase
{
    private function command(): string
    {
        $path = __DIR__ . '/../commands/onboard.md';
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function testScriveLaConfigurazioneConLoScriptEnonAMano(): void
    {
        self::assertStringContainsString(
            'scripts/write-config.php',
            $this->command(),
            'I nomi delle chiavi devono venire dal codice che le rilegge, non dalla prosa',
        );
    }

    public function testIstruisceACaricareIlPacchettoDelloStackDichiarato(): void
    {
        $contents = $this->command();

        self::assertStringContainsString('worky-stack-', $contents, 'Va registrata la convenzione del prefisso');
        self::assertStringContainsString('worky-stack-symfony-twig-stimulus', $contents);
        self::assertStringContainsString('## Convenzioni di progetto', $contents);
    }

    public function testDichiaraLoStatoDelComposerJsonOsservato(): void
    {
        self::assertStringContainsString('composer_json', $this->command());
        self::assertStringContainsString('illeggibile', $this->command());
    }

    public function testPreapprovaIComandiCheSuggerisceDiProvare(): void
    {
        $contents = $this->command();

        self::assertStringContainsString('Bash(composer:*)', $contents);
        self::assertStringContainsString('Bash(make:*)', $contents);
        self::assertStringContainsString('Bash(vendor/bin/phpunit:*)', $contents);
        self::assertStringNotContainsString(
            'Bash(docker',
            $contents,
            'Pre-approvare docker da una stringa inventata dall\'intervista è una concessione più larga del necessario',
        );
    }
}
