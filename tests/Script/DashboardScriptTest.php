<?php

declare(strict_types=1);

namespace Worky\Tests\Script;

use PHPUnit\Framework\TestCase;
use Worky\EventLog;

/**
 * Avvia davvero il server e gli parla: è l'unico modo per sapere che la
 * dashboard funziona anche senza l'autoloader di Composer, come accadrà
 * quando il plugin sarà installato.
 */
final class DashboardScriptTest extends TestCase
{
    private string $dir;
    private int $porta;
    private $processo = null;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/worky-dash-server-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
        $this->porta = random_int(8800, 8999);
    }

    protected function tearDown(): void
    {
        if (is_resource($this->processo)) {
            proc_terminate($this->processo);
            proc_close($this->processo);
        }

        $path = EventLog::path($this->dir);

        if (is_file($path)) {
            unlink($path);
        }

        @rmdir(dirname($path));
        @rmdir($this->dir);
    }

    private function avviaServer(): void
    {
        $router = __DIR__ . '/../../scripts/dashboard.php';
        $descrittori = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

        $this->processo = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $this->porta, $router],
            $descrittori,
            $pipes,
            null,
            ['WORKY_PROJECT_DIR' => $this->dir, 'PATH' => getenv('PATH')],
        );

        // Il server ci mette un istante ad aprire la porta.
        foreach (range(1, 40) as $ignored) {
            $presa = @fsockopen('127.0.0.1', $this->porta, $errno, $errstr, 0.1);

            if ($presa !== false) {
                fclose($presa);

                return;
            }

            usleep(50_000);
        }

        self::markTestSkipped('Il server di sviluppo non si è avviato sulla porta ' . $this->porta);
    }

    private function chiedi(string $percorso): string
    {
        $risposta = @file_get_contents('http://127.0.0.1:' . $this->porta . $percorso);

        self::assertIsString($risposta, "Nessuna risposta da $percorso");

        return $risposta;
    }

    public function testServeGliEventiRegistratiDavvero(): void
    {
        EventLog::append($this->dir, ['event' => 'PreToolUse', 'tool' => 'Bash', 'session' => 'xyz']);

        $this->avviaServer();

        $payload = json_decode($this->chiedi('/events'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('Bash', $payload['eventi'][0]['tool']);
        self::assertSame(1, $payload['sessioni']);
    }

    public function testServeLaPagina(): void
    {
        $this->avviaServer();

        self::assertStringContainsString('id="eventi"', $this->chiedi('/'));
    }
}
