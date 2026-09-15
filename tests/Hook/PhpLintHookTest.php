<?php

declare(strict_types=1);

namespace Worky\Tests\Hook;

use PHPUnit\Framework\TestCase;

final class PhpLintHookTest extends TestCase
{
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
    }

    /** @return array{0: int, 1: string} codice di uscita e stderr */
    private function runHook(array $payload): array
    {
        $script = __DIR__ . '/../../hooks/php-lint.php';
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(['php', $script], $descriptors, $pipes);

        fwrite($pipes[0], json_encode($payload, JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stderr];
    }

    private function tempFile(string $contents, string $extension): string
    {
        $path = sys_get_temp_dir() . '/worky-lint-' . bin2hex(random_bytes(6)) . '.' . $extension;
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    public function testBloccaQuandoIlFilePhpHaUnErroreDiSintassi(): void
    {
        $file = $this->tempFile("<?php\nfunction rotta( {\n", 'php');

        [$code, $stderr] = $this->runHook(['tool_name' => 'Write', 'tool_input' => ['file_path' => $file]]);

        self::assertSame(2, $code);
        self::assertStringContainsString('worky:', $stderr);
        self::assertStringContainsString($file, $stderr);
    }

    public function testLasciaPassareUnFilePhpValido(): void
    {
        $file = $this->tempFile("<?php\n\necho 'ok';\n", 'php');

        [$code] = $this->runHook(['tool_name' => 'Write', 'tool_input' => ['file_path' => $file]]);

        self::assertSame(0, $code);
    }

    public function testIgnoraIFileCheNonSonoPhp(): void
    {
        $file = $this->tempFile("questo non e php {{{", 'twig');

        [$code] = $this->runHook(['tool_name' => 'Write', 'tool_input' => ['file_path' => $file]]);

        self::assertSame(0, $code);
    }

    public function testIgnoraUnPayloadSenzaPercorso(): void
    {
        [$code] = $this->runHook(['tool_name' => 'Write', 'tool_input' => []]);

        self::assertSame(0, $code);
    }
}
