<?php

declare(strict_types=1);

namespace Worky\Tests;

use PHPUnit\Framework\TestCase;

final class ManifestTest extends TestCase
{
    public function testManifestIsValidJsonAndDeclaresThePlugin(): void
    {
        $path = __DIR__ . '/../.claude-plugin/plugin.json';
        self::assertFileExists($path, 'Il manifest del plugin deve esistere');

        $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('worky', $manifest['name']);
        self::assertNotEmpty($manifest['version']);
        self::assertNotEmpty($manifest['description']);
    }
}
