<?php

declare(strict_types=1);

namespace Worky\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SkillsFrontmatterTest extends TestCase
{
    /** @return iterable<string, array{0: string}> */
    public static function skillFiles(): iterable
    {
        $root = __DIR__ . '/../skills';

        if (!is_dir($root)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if ($file->getFilename() === 'SKILL.md') {
                yield $file->getPathname() => [$file->getPathname()];
            }
        }
    }

    #[DataProvider('skillFiles')]
    public function testOgniSkillHaNomeEDescrizioneNelFrontmatter(string $path): void
    {
        $contents = (string) file_get_contents($path);

        self::assertSame(1, preg_match('/^---\n(.*?)\n---\n/s', $contents, $matches), "Frontmatter mancante in $path");
        self::assertSame(1, preg_match('/^name:\s*\S+/m', $matches[1]), "Campo name mancante in $path");
        self::assertSame(1, preg_match('/^description:\s*\S+/m', $matches[1]), "Campo description mancante in $path");
    }

    public function testIlPacchettoSymfonyEsisteEDichiaraIlProprioNome(): void
    {
        $path = __DIR__ . '/../skills/stacks/symfony-twig-stimulus/SKILL.md';
        self::assertFileExists($path);

        $contents = (string) file_get_contents($path);
        self::assertStringContainsString('name: worky-stack-symfony-twig-stimulus', $contents);
    }

    public function testIlPacchettoNonContieneComandiDiConsegna(): void
    {
        $contents = (string) file_get_contents(
            __DIR__ . '/../skills/stacks/symfony-twig-stimulus/SKILL.md',
        );

        self::assertStringNotContainsString(
            'gh pr create',
            $contents,
            'Il pacchetto di stack deve contenere convenzioni, non procedure di consegna',
        );
    }
}
