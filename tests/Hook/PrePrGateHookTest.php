<?php

declare(strict_types=1);

namespace Worky\Tests\Hook;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Worky\Config;

final class PrePrGateHookTest extends TestCase
{
    private string $dir;

    /** @var list<string> */
    private array $dirs = [];

    protected function setUp(): void
    {
        $this->dir = $this->newDir();
    }

    protected function tearDown(): void
    {
        foreach ($this->dirs as $dir) {
            $this->removeDir($dir);
        }

        $this->dirs = [];
    }

    private function newDir(): string
    {
        $dir = sys_get_temp_dir() . '/worky-gate-' . bin2hex(random_bytes(6));
        mkdir($dir);
        $this->dirs[] = $dir;

        return $dir;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : @unlink($path);
        }

        @rmdir($dir);
    }

    private function script(): string
    {
        return __DIR__ . '/../../hooks/pre-pr-gate.php';
    }

    /** @return array{0: int, 1: string} codice di uscita e stderr */
    private function runHook(array $payload, ?string $envDir = null, ?string $script = null): array
    {
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(
            ['php', $script ?? $this->script()],
            $descriptors,
            $pipes,
            null,
            ['CLAUDE_PROJECT_DIR' => $envDir ?? $this->dir, 'PATH' => getenv('PATH')],
        );

        fwrite($pipes[0], json_encode($payload, JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stderr];
    }

    private function bashPayload(string $command, ?string $cwd = null): array
    {
        $payload = ['tool_name' => 'Bash', 'tool_input' => ['command' => $command]];

        if ($cwd !== null) {
            $payload['cwd'] = $cwd;
        }

        return $payload;
    }

    public function testIgnoraIComandiCheNonCreanoUnaPullRequest(): void
    {
        Config::write($this->dir, ['schema_version' => 1, 'test' => 'false']);

        [$code] = $this->runHook($this->bashPayload('git status'));

        self::assertSame(0, $code);
    }

    public function testLasciaPassareQuandoTuttiIComandiDelGatePassano(): void
    {
        Config::write($this->dir, ['schema_version' => 1, 'test' => 'true', 'static_analysis' => 'true']);

        [$code] = $this->runHook($this->bashPayload('gh pr create --fill'));

        self::assertSame(0, $code);
    }

    public function testBloccaQuandoITestFalliscono(): void
    {
        Config::write($this->dir, ['schema_version' => 1, 'test' => 'false', 'static_analysis' => 'true']);

        [$code, $stderr] = $this->runHook($this->bashPayload('gh pr create --fill'));

        self::assertSame(2, $code);
        self::assertStringContainsString('test', $stderr);
    }

    public function testBloccaQuandoLAnalisiStaticaFallisce(): void
    {
        Config::write($this->dir, ['schema_version' => 1, 'test' => 'true', 'static_analysis' => 'false']);

        [$code, $stderr] = $this->runHook($this->bashPayload('gh pr create --fill'));

        self::assertSame(2, $code);
        self::assertStringContainsString('static_analysis', $stderr);
    }

    public function testBloccaQuandoLaConfigurazioneManca(): void
    {
        [$code, $stderr] = $this->runHook($this->bashPayload('gh pr create --fill'));

        self::assertSame(2, $code);
        self::assertStringContainsString('/worky:onboard', $stderr);
    }

    public function testSegnalaSuStderrIComandiNonConfiguratiCheSalta(): void
    {
        Config::write($this->dir, ['schema_version' => 1, 'test' => 'true', 'static_analysis' => null]);

        [$code, $stderr] = $this->runHook($this->bashPayload('gh pr create --fill'));

        self::assertSame(0, $code);
        self::assertStringContainsString("worky: gate 'static_analysis' non configurato, saltato", $stderr);
    }

    /**
     * Inversione deliberata della policy precedente (il vecchio test si chiamava
     * testNonAttivaIlGatePerMenzioniDelFrase).
     *
     * Il pattern non àncora più "gh pr create" alla posizione di comando, perché
     * enumerare tutto ciò che può legittimamente precedere un comando non ha
     * forma chiusa in shell. Di conseguenza anche una semplice menzione della
     * frase attiva il gate: è il prezzo accettato per non mancare mai
     * un'invocazione vera. Su un gate di sicurezza un falso negativo — una PR
     * aperta senza controlli — costa molto più di un falso positivo, che costa
     * solo l'esecuzione dei test.
     */
    public function testAttivaIlGateAnchePerUnaSempliceMenzioneDellaFrase(): void
    {
        Config::write($this->dir, ['schema_version' => 1, 'test' => 'false']);

        [$code, $stderr] = $this->runHook($this->bashPayload('echo "ricorda: gh pr create dopo il merge"'));

        self::assertSame(2, $code);
        self::assertStringContainsString('test', $stderr);
    }

    /** @return iterable<string, array{0: string}> */
    public static function comandiCheApronoUnaPullRequest(): iterable
    {
        yield 'invocazione diretta' => ['gh pr create --fill'];
        yield 'in catena' => ['make lint && gh pr create --fill'];
        yield 'in una subshell' => ['$(gh pr create --fill)'];
        yield 'dentro un if' => ['if true; then gh pr create; fi'];
        yield 'dentro un for' => ['for b in a; do gh pr create; done'];
        yield 'dentro un while' => ['while read x; do gh pr create; done'];
        yield 'dentro un gruppo' => ['{ gh pr create; }'];
        yield 'gruppo dopo un push' => ['git push && { gh pr create --fill; }'];
        yield 'con sudo' => ['sudo gh pr create'];
        yield 'con command' => ['command gh pr create'];
        yield 'con env' => ['env GH_TOKEN=x gh pr create'];
        yield 'con time' => ['time gh pr create'];
        yield 'con xargs' => ['xargs gh pr create'];
        yield 'con assegnazione e sudo' => ['GH_TOKEN=x sudo gh pr create'];
        yield 'con percorso assoluto' => ['/usr/bin/gh pr create'];
        yield 'annidata in sh -c' => ['sh -c "gh pr create"'];
        yield 'via API invece della porcelain' => ['gh api repos/acme/app/pulls -f title=x'];
        yield 'via API con method esplicito' => ['gh api --method POST /repos/acme/app/pulls'];
    }

    #[DataProvider('comandiCheApronoUnaPullRequest')]
    public function testAttivaIlGatePerOgniFormaDiApertura(string $command): void
    {
        Config::write($this->dir, ['schema_version' => 1, 'test' => 'false']);

        [$code, $stderr] = $this->runHook($this->bashPayload($command));

        self::assertSame(2, $code, "Il gate deve attivarsi per: $command");
        self::assertStringContainsString('test', $stderr);
    }

    /** @return iterable<string, array{0: string}> */
    public static function comandiCheNonApronoUnaPullRequest(): iterable
    {
        yield 'elenco delle pull request' => ['gh pr list'];
        yield 'sottocomando piu lungo' => ['gh pr createfoo'];
        yield 'gh dentro unaltra parola' => ['highlight pr create'];
        yield 'parola che inizia per gh' => ['ghost pr create'];
        yield 'menzione senza il verbo' => ['echo gherkin'];
        yield 'api su un altro endpoint' => ['gh api repos/acme/app/issues'];
    }

    #[DataProvider('comandiCheNonApronoUnaPullRequest')]
    public function testNonAttivaIlGatePerIComandiChePullRequestNonNeAprono(string $command): void
    {
        Config::write($this->dir, ['schema_version' => 1, 'test' => 'false']);

        [$code] = $this->runHook($this->bashPayload($command));

        self::assertSame(0, $code, "Il gate non deve attivarsi per: $command");
    }

    public function testUsaLaDirectoryDelComandoEnonQuellaDellaSessione(): void
    {
        // Scenario worktree: la sessione è partita nel checkout principale,
        // ma il comando — e quindi la PR — vive nel worktree.
        $sessione = $this->newDir();
        Config::write($sessione, ['schema_version' => 1, 'test' => 'true']);
        Config::write($this->dir, ['schema_version' => 1, 'test' => 'false']);

        [$code, $stderr] = $this->runHook(
            $this->bashPayload('gh pr create --fill', $this->dir),
            $sessione,
        );

        self::assertSame(2, $code, 'Il cwd del payload deve prevalere su CLAUDE_PROJECT_DIR');
        self::assertStringContainsString('test', $stderr);
    }

    public function testRipiegaSullaVariabileDAmbienteQuandoIlPayloadNonPortaIlCwd(): void
    {
        $sessione = $this->newDir();
        Config::write($sessione, ['schema_version' => 1, 'test' => 'false']);

        [$code] = $this->runHook($this->bashPayload('gh pr create --fill'), $sessione);

        self::assertSame(2, $code);
    }

    public function testBloccaLaPullRequestQuandoIlGateStessoNonPuoEseguire(): void
    {
        // Un plugin aggiornato a metà: lo script c'è, le sue dipendenze no.
        // Qualunque errore del gate deve chiudere, non aprire.
        Config::write($this->dir, ['schema_version' => 1, 'test' => 'true']);
        mkdir($this->dir . '/hooks');
        $orfano = $this->dir . '/hooks/pre-pr-gate.php';
        copy($this->script(), $orfano);

        [$code, $stderr] = $this->runHook($this->bashPayload('gh pr create --fill'), null, $orfano);

        self::assertSame(2, $code, 'Un gate che non riesce a eseguire deve bloccare la PR');
        self::assertStringContainsString('worky:', $stderr);
        self::assertStringContainsString('bloccata', $stderr);
    }
}
