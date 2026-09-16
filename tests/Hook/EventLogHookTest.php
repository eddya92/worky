<?php

declare(strict_types=1);

namespace Worky\Tests\Hook;

use PHPUnit\Framework\TestCase;
use Worky\EventLog;

final class EventLogHookTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/worky-hook-events-' . bin2hex(random_bytes(6));
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

    private function runHook(string $payload): int
    {
        $script = __DIR__ . '/../../hooks/event-log.php';
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(['php', $script], $descriptors, $pipes, null, ['PATH' => getenv('PATH')]);

        fwrite($pipes[0], $payload);
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process);
    }

    private function send(array $payload): int
    {
        $payload['cwd'] = $this->dir;

        return $this->runHook(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function testRegistraUnComandoBashConIlSuoBersaglio(): void
    {
        $code = $this->send([
            'hook_event_name' => 'PreToolUse',
            'tool_name' => 'Bash',
            'tool_input' => ['command' => 'composer test'],
        ]);

        self::assertSame(0, $code);

        $events = EventLog::tail($this->dir);
        self::assertCount(1, $events);
        self::assertSame('PreToolUse', $events[0]['event']);
        self::assertSame('Bash', $events[0]['tool']);
        self::assertSame('composer test', $events[0]['target']);
    }

    public function testRegistraIlPercorsoQuandoLoStrumentoScriveUnFile(): void
    {
        $this->send([
            'hook_event_name' => 'PostToolUse',
            'tool_name' => 'Write',
            'tool_input' => ['file_path' => '/progetto/src/Servizio.php'],
        ]);

        self::assertSame('/progetto/src/Servizio.php', EventLog::tail($this->dir)[0]['target']);
    }

    public function testRegistraLaFineDiUnSubagente(): void
    {
        $this->send(['hook_event_name' => 'SubagentStop', 'session_id' => 'abc123']);

        $event = EventLog::tail($this->dir)[0];

        self::assertSame('SubagentStop', $event['event']);
        self::assertSame('abc123', $event['session']);
        self::assertNull($event['tool']);
    }

    public function testAccorciaUnBersaglioMoltoLungo(): void
    {
        $this->send([
            'hook_event_name' => 'PreToolUse',
            'tool_name' => 'Bash',
            'tool_input' => ['command' => str_repeat('x', 500)],
        ]);

        self::assertLessThanOrEqual(160, mb_strlen(EventLog::tail($this->dir)[0]['target']));
    }

    public function testDistingueLAgenteDalNomeDellaSuaTrascrizione(): void
    {
        $this->send([
            'hook_event_name' => 'SubagentStop',
            'session_id' => 'sessione-padre',
            'transcript_path' => '/Users/tizio/.claude/sessions/9f3c21ab-figlio.jsonl',
        ]);

        $evento = EventLog::tail($this->dir)[0];

        self::assertSame('9f3c21ab-figlio', $evento['agente']);
        self::assertSame('sessione-padre', $evento['session'], 'La sessione resta quella padre');
    }

    public function testLasciaNulloLAgenteSenzaTrascrizione(): void
    {
        $this->send(['hook_event_name' => 'Stop', 'session_id' => 'a']);

        self::assertNull(EventLog::tail($this->dir)[0]['agente']);
    }

    public function testRegistraIlCompitoEIlTipoDiUnSubagenteLanciato(): void
    {
        $this->send([
            'hook_event_name' => 'PreToolUse',
            'tool_name' => 'Task',
            'tool_input' => [
                'description' => 'Implementa il riepilogo ordine',
                'subagent_type' => 'general-purpose',
                'prompt' => 'un prompt lunghissimo che non ci interessa registrare',
            ],
        ]);

        $evento = EventLog::tail($this->dir)[0];

        self::assertSame('Implementa il riepilogo ordine', $evento['target']);
        self::assertSame('general-purpose', $evento['tipo']);
    }

    public function testNonRegistraIlPromptDiUnSubagente(): void
    {
        $this->send([
            'hook_event_name' => 'PreToolUse',
            'tool_name' => 'Task',
            'tool_input' => ['description' => 'Breve', 'prompt' => 'SEGRETO-DA-NON-REGISTRARE'],
        ]);

        self::assertStringNotContainsString(
            'SEGRETO-DA-NON-REGISTRARE',
            (string) file_get_contents(EventLog::path($this->dir)),
        );
    }

    public function testNonBloccaMaiNienteNemmenoConUnPayloadRotto(): void
    {
        self::assertSame(0, $this->runHook('{questo non e json'));
        self::assertSame(0, $this->runHook(''));
    }

    public function testNonBloccaNemmenoQuandoNonPuoScrivere(): void
    {
        $code = $this->runHook(json_encode([
            'hook_event_name' => 'PreToolUse',
            'tool_name' => 'Read',
            'cwd' => '/directory/che/non/esiste/da/nessuna/parte',
        ], JSON_THROW_ON_ERROR));

        self::assertSame(0, $code);
    }
}
