<?php

declare(strict_types=1);

namespace Worky\Tests;

use PHPUnit\Framework\TestCase;
use Worky\EventLog;

final class EventLogTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/worky-events-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        $path = EventLog::path($this->dir);

        if (is_file($path)) {
            unlink($path);
        }

        @rmdir(dirname($path));
        @rmdir($this->dir);
    }

    public function testRegistraUnEventoELoRilegge(): void
    {
        EventLog::append($this->dir, ['event' => 'PreToolUse', 'tool' => 'Bash']);

        $events = EventLog::tail($this->dir);

        self::assertCount(1, $events);
        self::assertSame('PreToolUse', $events[0]['event']);
        self::assertSame('Bash', $events[0]['tool']);
    }

    public function testAggiungeUnIstanteAOgniEvento(): void
    {
        EventLog::append($this->dir, ['event' => 'Stop']);

        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            EventLog::tail($this->dir)[0]['ts'],
        );
    }

    public function testCreaLaDirectoryQuandoNonEsiste(): void
    {
        self::assertDirectoryDoesNotExist($this->dir . '/' . EventLog::DIRNAME);

        EventLog::append($this->dir, ['event' => 'SessionStart']);

        self::assertDirectoryExists($this->dir . '/' . EventLog::DIRNAME);
    }

    public function testRestituisceGliUltimiEventiNellOrdineInCuiSonoAccaduti(): void
    {
        foreach (range(1, 10) as $n) {
            EventLog::append($this->dir, ['event' => 'PostToolUse', 'tool' => 'T' . $n]);
        }

        $events = EventLog::tail($this->dir, 3);

        self::assertSame(['T8', 'T9', 'T10'], array_column($events, 'tool'));
    }

    public function testRestituisceUnElencoVuotoQuandoNonCEAncoraNulla(): void
    {
        self::assertSame([], EventLog::tail($this->dir));
    }

    public function testSaltaLeRigheIlleggibiliInvecediFallire(): void
    {
        EventLog::append($this->dir, ['event' => 'PreToolUse', 'tool' => 'Read']);
        file_put_contents(EventLog::path($this->dir), "{rotta\n", FILE_APPEND);
        EventLog::append($this->dir, ['event' => 'PostToolUse', 'tool' => 'Write']);

        self::assertSame(['Read', 'Write'], array_column(EventLog::tail($this->dir), 'tool'));
    }

    public function testNonScriveMaiSuPiuRighePerEvento(): void
    {
        EventLog::append($this->dir, ['event' => 'PreToolUse', 'tool' => "Bash\ncon un a capo"]);

        self::assertCount(1, file(EventLog::path($this->dir)));
    }
}
