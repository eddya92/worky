<?php

declare(strict_types=1);

namespace Worky;

/**
 * Osserva un progetto e riporta soltanto fatti verificabili.
 *
 * Non deduce comandi: che esista uno script composer "test" è un fatto, che il
 * comando dei test del progetto sia "composer test" è una decisione, e la
 * decisione spetta all'utente durante /worky:onboard.
 */
final class ProjectFacts
{
    /** Pacchetto composer che identifica il framework => nome del framework. */
    private const FRAMEWORKS = [
        'symfony/framework-bundle' => 'symfony',
        'laravel/framework' => 'laravel',
    ];

    /**
     * Framework => pacchetto di convenzioni in skills/worky-stack-<nome>/.
     * Aggiungere un pacchetto significa aggiungere una riga qui.
     */
    private const STACK_PACKS = [
        'symfony' => 'symfony-twig-stimulus',
    ];

    /** Strumento => file di configurazione che ne prova la presenza. */
    private const TOOLS = [
        'phpunit' => ['phpunit.xml.dist', 'phpunit.xml'],
        'phpstan' => ['phpstan.neon', 'phpstan.neon.dist'],
        'php_cs_fixer' => ['.php-cs-fixer.dist.php', '.php-cs-fixer.php'],
    ];

    private const PATHS = [
        'entity' => 'src/Entity',
        'controller' => 'src/Controller',
        'templates' => 'templates',
        'assets' => 'assets/controllers',
    ];

    public function __construct(private readonly string $projectDir)
    {
    }

    public function observe(): array
    {
        $composer = $this->readJson('composer.json');
        $framework = $this->observeFramework($composer);

        $require = $this->requireSection($composer);
        $scripts = $composer['scripts'] ?? [];

        return [
            'framework' => $framework,
            'stack' => $framework === null ? null : (self::STACK_PACKS[$framework] ?? null),
            'php' => $this->versionFrom($require['php'] ?? null),
            'framework_version' => $this->frameworkVersion($composer, $framework),
            'tools' => $this->observeTools(),
            'paths' => $this->observePaths(),
            'composer_scripts' => is_array($scripts) ? array_keys($scripts) : [],
            'make_targets' => $this->observeMakeTargets(),
            'composer_json' => $this->composerJsonStatus(),
        ];
    }

    /**
     * Stato leggibile di composer.json: un file illeggibile non è la stessa
     * cosa di un file assente, e riportarlo come assenza porterebbe
     * l'intervista a non chiedere nulla proprio dove dovrebbe chiedere di più.
     */
    private function composerJsonStatus(): string
    {
        if (!$this->exists('composer.json')) {
            return 'assente';
        }

        $decoded = json_decode((string) file_get_contents($this->path('composer.json')), true);

        if (!is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            return 'illeggibile';
        }

        return 'ok';
    }

    private function requireSection(array $composer): array
    {
        $require = $composer['require'] ?? [];

        return is_array($require) ? $require : [];
    }

    private function observeFramework(array $composer): ?string
    {
        $require = $this->requireSection($composer);

        foreach (self::FRAMEWORKS as $package => $framework) {
            if (isset($require[$package])) {
                return $framework;
            }
        }

        return null;
    }

    private function frameworkVersion(array $composer, ?string $framework): ?string
    {
        if ($framework === null) {
            return null;
        }

        $package = array_search($framework, self::FRAMEWORKS, true);

        return $this->versionFrom($this->requireSection($composer)[$package] ?? null);
    }

    private function observeTools(): array
    {
        $tools = [];

        foreach (self::TOOLS as $tool => $candidates) {
            $tools[$tool] = false;

            foreach ($candidates as $candidate) {
                if ($this->exists($candidate)) {
                    $tools[$tool] = true;
                    break;
                }
            }
        }

        return $tools;
    }

    private function observePaths(): array
    {
        $paths = [];

        foreach (self::PATHS as $key => $relative) {
            $paths[$key] = is_dir($this->path($relative)) ? $relative : null;
        }

        return $paths;
    }

    private function observeMakeTargets(): array
    {
        if (!$this->exists('Makefile')) {
            return [];
        }

        $contents = (string) file_get_contents($this->path('Makefile'));
        preg_match_all('/^([A-Za-z0-9_-]+):/m', $contents, $matches);

        return $matches[1];
    }

    /** Il vincolo arriva da un JSON scritto da altri: può essere di qualunque tipo. */
    private function versionFrom(mixed $constraint): ?string
    {
        if (!is_string($constraint)) {
            return null;
        }

        return preg_match('/(\d+\.\d+)/', $constraint, $matches) === 1 ? $matches[1] : null;
    }

    private function readJson(string $relative): array
    {
        if (!$this->exists($relative)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($this->path($relative)), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function exists(string $relative): bool
    {
        return is_file($this->path($relative));
    }

    private function path(string $relative): string
    {
        return $this->projectDir . '/' . $relative;
    }
}
