<?php

declare(strict_types=1);

namespace Worky\Tests;

use PHPUnit\Framework\TestCase;

final class HooksRegistrationTest extends TestCase
{
    private function config(): array
    {
        $path = __DIR__ . '/../hooks/hooks.json';
        self::assertFileExists($path);

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testRegistraILintSulleScrittureEIlGateSuBash(): void
    {
        $hooks = $this->config()['hooks'];

        self::assertSame('Write|Edit', $hooks['PostToolUse'][0]['matcher']);
        self::assertSame('Bash', $hooks['PreToolUse'][0]['matcher']);
    }

    public function testRegistraIlDiarioSuOgniEventoCheServeAllaDashboard(): void
    {
        $hooks = $this->config()['hooks'];

        foreach (['PreToolUse', 'PostToolUse', 'SubagentStop', 'Stop', 'SessionStart'] as $event) {
            self::assertArrayHasKey($event, $hooks, "Il diario deve essere registrato su $event");

            $comandi = [];

            foreach ($hooks[$event] as $entry) {
                foreach ($entry['hooks'] as $hook) {
                    $comandi[] = $hook['command'];
                }
            }

            self::assertNotEmpty(
                array_filter($comandi, static fn (string $c): bool => str_contains($c, 'event-log.php')),
                "Nessun diario registrato su $event",
            );
        }
    }

    public function testIlDiarioNonPuoBloccareIlLavoro(): void
    {
        $sorgente = (string) file_get_contents(__DIR__ . '/../hooks/event-log.php');

        self::assertStringNotContainsString(
            'exit(2)',
            $sorgente,
            'Il diario non deve mai uscire con il codice che blocca',
        );
    }

    public function testOgniScriptReferenziatoEsiste(): void
    {
        $hooks = $this->config()['hooks'];
        $found = 0;

        foreach ($hooks as $entries) {
            foreach ($entries as $entry) {
                foreach ($entry['hooks'] as $hook) {
                    self::assertSame('command', $hook['type']);
                    preg_match('/\$\{CLAUDE_PLUGIN_ROOT\}([^"\']+)/', $hook['command'], $matches);
                    self::assertNotEmpty($matches, 'Ogni comando deve usare ${CLAUDE_PLUGIN_ROOT}');
                    self::assertFileExists(__DIR__ . '/..' . $matches[1]);
                    ++$found;
                }
            }
        }

        self::assertSame(7, $found, 'Un hook in piu o in meno va aggiunto qui di proposito, non per caso');
    }
}
