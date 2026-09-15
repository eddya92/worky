<?php

declare(strict_types=1);

namespace Worky\Tests\Script;

use PHPUnit\Framework\TestCase;

/**
 * observe.php è il punto d'ingresso dell'intervista: un require_once
 * dimenticato qui fallirebbe soltanto a plugin installato. Va eseguito.
 */
final class ObserveScriptTest extends TestCase
{
    public function testStampaJsonValidoSuUnProgettoOsservabile(): void
    {
        $script = __DIR__ . '/../../scripts/observe.php';
        $fixture = __DIR__ . '/../fixtures/symfony-full';
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(['php', $script, $fixture], $descriptors, $pipes);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        self::assertSame(0, $code, $stderr);

        $facts = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($facts);
        self::assertSame('symfony', $facts['framework']);
        self::assertSame('ok', $facts['composer_json']);
    }
}
