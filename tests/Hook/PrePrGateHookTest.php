<?php

declare(strict_types=1);

namespace Worky\Tests\Hook;

use PHPUnit\Framework\TestCase;
use Worky\Config;

final class PrePrGateHookTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/worky-gate-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/' . Config::FILENAME);
        @rmdir($this->dir);
    }

    /** @return array{0: int, 1: string} */
    private function runHook(array $payload): array
    {
        $script = __DIR__ . '/../../hooks/pre-pr-gate.php';
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(
            ['php', $script],
            $descriptors,
            $pipes,
            null,
            ['CLAUDE_PROJECT_DIR' => $this->dir, 'PATH' => getenv('PATH')],
        );

        fwrite($pipes[0], json_encode($payload, JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stderr];
    }

    private function bashPayload(string $command): array
    {
        return ['tool_name' => 'Bash', 'tool_input' => ['command' => $command]];
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

    public function testSaltaIComandiNonConfigurati(): void
    {
        Config::write($this->dir, ['schema_version' => 1, 'test' => 'true', 'static_analysis' => null]);

        [$code] = $this->runHook($this->bashPayload('gh pr create --fill'));

        self::assertSame(0, $code);
    }
}
