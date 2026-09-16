<?php

declare(strict_types=1);

namespace Worky\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Il pacchetto di design vale solo se resta quello che abbiamo deciso: regole
 * di UX e UI valide ovunque, senza identità visiva e senza stack. La palette e
 * la tipografia sono del progetto, non del plugin.
 */
final class DesignSkillTest extends TestCase
{
    private function skill(): string
    {
        $path = __DIR__ . '/../skills/worky-design/SKILL.md';
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function testEsisteEDichiaraIlProprioNome(): void
    {
        self::assertStringContainsString('name: worky-design', $this->skill());
    }

    public function testNonContieneUnaPalette(): void
    {
        self::assertSame(
            0,
            preg_match('/#[0-9a-f]{3}(?:[0-9a-f]{3})?\b/i', $this->skill()),
            'I colori sono del progetto: qui non ci va nessun valore esadecimale',
        );
    }

    public function testNonEeLegatoAUnoStack(): void
    {
        $contents = $this->skill();

        foreach (['Symfony', 'Twig', 'Stimulus', 'React', 'Laravel'] as $stack) {
            self::assertStringNotContainsString(
                $stack,
                $contents,
                "Le regole devono valere anche fuori da $stack: quelle di stack stanno nel loro pacchetto",
            );
        }
    }

    public function testCopreLeDimenticanzeRicorrenti(): void
    {
        $contents = mb_strtolower($this->skill());

        foreach (['vuoto', 'caricamento', 'errore', 'focus', 'distruttiv'] as $argomento) {
            self::assertStringContainsString(
                $argomento,
                $contents,
                "Manca la regola su: $argomento",
            );
        }
    }

    public function testRimandaAlProgettoPerLIdentitaVisiva(): void
    {
        self::assertStringContainsString(
            '## Aspetto',
            $this->skill(),
            'La skill deve dire dove sta scritta l\'identità visiva del progetto',
        );
    }
}
