<?php

declare(strict_types=1);

namespace Worky\Tests;

use PHPUnit\Framework\TestCase;
use Worky\Dashboard;
use Worky\EventLog;

final class DashboardTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/worky-dashboard-' . bin2hex(random_bytes(6));
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

    private function decodedEvents(int $limit = 200): array
    {
        return json_decode(Dashboard::events($this->dir, $limit), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testServeGliEventiComeJson(): void
    {
        EventLog::append($this->dir, ['event' => 'PreToolUse', 'tool' => 'Bash', 'session' => 'a']);

        $payload = $this->decodedEvents();

        self::assertSame('Bash', $payload['eventi'][0]['tool']);
    }

    public function testRiportaQuanteSessioniHaVisto(): void
    {
        EventLog::append($this->dir, ['event' => 'PreToolUse', 'tool' => 'Read', 'session' => 'a']);
        EventLog::append($this->dir, ['event' => 'PreToolUse', 'tool' => 'Read', 'session' => 'b']);
        EventLog::append($this->dir, ['event' => 'PostToolUse', 'tool' => 'Read', 'session' => 'a']);

        self::assertSame(2, $this->decodedEvents()['sessioni']);
    }

    public function testNonSiRompeSuUnProgettoSenzaDiario(): void
    {
        $payload = $this->decodedEvents();

        self::assertSame([], $payload['eventi']);
        self::assertSame(0, $payload['sessioni']);
    }

    public function testLaPaginaEHtmlCompletoConIlPuntoDiAggancioDegliEventi(): void
    {
        $page = Dashboard::page();

        self::assertStringStartsWith('<!doctype html>', $page);
        self::assertStringContainsString('id="eventi"', $page);
        self::assertStringContainsString('/events', $page, 'La pagina deve interrogare il suo endpoint');
    }

    public function testLaPaginaNonCaricaNienteDallEsterno(): void
    {
        $page = Dashboard::page();

        self::assertStringNotContainsString('http://', $page);
        self::assertStringNotContainsString('https://', $page);
    }
}
