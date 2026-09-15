# worky — Piano di implementazione

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Costruire `worky`, un plugin Claude Code che distribuisce un team di cinque agenti capace di portare una feature dalla richiesta alla pull request con test verdi, su qualunque progetto, partendo dal pacchetto di stack Symfony/Twig/Stimulus.

**Architecture:** Il plugin è un repo git con manifest `.claude-plugin/plugin.json`. La logica deterministica (rilevamento del progetto, gate di qualità) è codice PHP testato con PHPUnit nel repo stesso; la logica agentica (ruoli, pipeline, convenzioni) è markdown in `agents/`, `skills/` e `commands/`. La conoscenza di framework sta in pacchetti sostituibili sotto `skills/stacks/`, selezionati dal rilevamento e registrati in `.worky.json` nel progetto servito.

**Tech Stack:** PHP 8.2+, PHPUnit 11, Composer, git, `gh`. Nessuna dipendenza runtime: gli script eseguiti come hook usano `require_once` espliciti e non l'autoloader di Composer, perché un plugin installato non ha `vendor/`.

**Spec:** `docs/superpowers/specs/2026-09-15-worky-team-design.md`

## Global Constraints

- Nome del plugin: `worky`. Namespace PHP: `Worky\`. Agenti prefissati `worky-`.
- Versione PHP minima: `8.2`. Nessuna dipendenza runtime oltre alla standard library.
- Gli script in `hooks/` NON possono usare `vendor/autoload.php`: usano `require_once` relativi a `__DIR__`.
- Ogni hook che blocca esce con **codice 2** e scrive il motivo su **stderr**, prefissato `worky: `.
- I file `.worky.json` hanno sempre `schema_version: 1`.
- Aggiungere un pacchetto di stack deve richiedere solo: una cartella in `skills/stacks/` e una riga nelle costanti del detector. Nessuna modifica ad agenti, pipeline o hook.
- Tutti i testi rivolti all'utente (descrizioni, messaggi di errore, prompt) sono in italiano.
- Ogni task termina con un commit.

---

### Task 1: Scheletro del plugin e toolchain di test

**Files:**
- Create: `.claude-plugin/plugin.json`
- Create: `composer.json`
- Create: `phpunit.xml.dist`
- Create: `README.md`
- Test: `tests/ManifestTest.php`
- Modify: `.gitignore`

**Interfaces:**
- Consumes: niente (primo task)
- Produces: comando di test del repo `composer test`; autoload PSR-4 `Worky\` → `src/`, `Worky\Tests\` → `tests/`

- [ ] **Step 1: Creare `composer.json`**

```json
{
    "name": "worky/team",
    "description": "Team di agenti Claude Code per lo sviluppo assistito",
    "type": "project",
    "license": "proprietary",
    "require": {
        "php": ">=8.2"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0"
    },
    "autoload": {
        "psr-4": { "Worky\\": "src/" }
    },
    "autoload-dev": {
        "psr-4": { "Worky\\Tests\\": "tests/" }
    },
    "scripts": {
        "test": "phpunit"
    },
    "config": {
        "sort-packages": true
    }
}
```

- [ ] **Step 2: Installare le dipendenze**

Run: `composer install`
Expected: crea `vendor/` e `composer.lock`.

- [ ] **Step 3: Creare `phpunit.xml.dist`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="worky">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 4: Aggiungere a `.gitignore`**

```
.idea/
vendor/
node_modules/
.phpunit.cache/
evals/results/
```

- [ ] **Step 5: Scrivere il test che fallisce**

`tests/ManifestTest.php`:

```php
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
```

- [ ] **Step 6: Eseguire il test e verificare che fallisca**

Run: `composer test`
Expected: FAIL — "Il manifest del plugin deve esistere".

- [ ] **Step 7: Creare il manifest**

`.claude-plugin/plugin.json`:

```json
{
  "name": "worky",
  "description": "Team di agenti specializzati per lo sviluppo: analyst, backend, frontend, qa, reviewer",
  "version": "0.1.0",
  "author": { "name": "eddy2r" },
  "keywords": ["agents", "workflow", "tdd", "symfony"]
}
```

- [ ] **Step 8: Eseguire il test e verificare che passi**

Run: `composer test`
Expected: PASS (1 test, 3 assertions).

- [ ] **Step 9: Scrivere `README.md`**

Deve contenere: cos'è worky, come si installa (`claude plugin marketplace add <url-repo>` poi `claude plugin install worky`), come si esegue l'onboarding su un progetto, e come si lanciano i test del plugin (`composer test`).

- [ ] **Step 10: Commit**

```bash
git add composer.json composer.lock phpunit.xml.dist .gitignore .claude-plugin README.md tests
git commit -m "Aggiunge lo scheletro del plugin worky con toolchain di test"
```

---

### Task 2: Rilevamento di stack, versioni e comando di test

**Files:**
- Create: `src/ProjectDetector.php`
- Test: `tests/ProjectDetectorTest.php`
- Create: `tests/fixtures/symfony-composer-script/composer.json`
- Create: `tests/fixtures/symfony-plain/composer.json`, `tests/fixtures/symfony-plain/phpunit.xml.dist`
- Create: `tests/fixtures/laravel-basic/composer.json`

**Interfaces:**
- Consumes: autoload PSR-4 dal Task 1
- Produces: `Worky\ProjectDetector::__construct(string $projectDir)` e `detect(): array`. Le chiavi prodotte in questo task sono `schema_version` (int), `framework` (string|null), `stack` (string|null), `php` (string|null), `framework_version` (string|null), `test` (string|null).

- [ ] **Step 1: Creare le fixture**

`tests/fixtures/symfony-composer-script/composer.json`:

```json
{
    "require": {
        "php": ">=8.2",
        "symfony/framework-bundle": "7.1.*",
        "symfony/twig-bundle": "7.1.*"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0"
    },
    "scripts": {
        "test": "phpunit"
    }
}
```

`tests/fixtures/symfony-plain/composer.json`:

```json
{
    "require": {
        "php": ">=8.1",
        "symfony/framework-bundle": "6.4.*"
    }
}
```

`tests/fixtures/symfony-plain/phpunit.xml.dist`: un file XML minimo, basta `<phpunit/>`.

`tests/fixtures/laravel-basic/composer.json`:

```json
{
    "require": {
        "php": ">=8.2",
        "laravel/framework": "^11.0"
    }
}
```

- [ ] **Step 2: Scrivere i test che falliscono**

`tests/ProjectDetectorTest.php`:

```php
<?php

declare(strict_types=1);

namespace Worky\Tests;

use PHPUnit\Framework\TestCase;
use Worky\ProjectDetector;

final class ProjectDetectorTest extends TestCase
{
    private function detect(string $fixture): array
    {
        return (new ProjectDetector(__DIR__ . '/fixtures/' . $fixture))->detect();
    }

    public function testRiconosceSymfonyEAssegnaIlPacchettoDiStack(): void
    {
        $result = $this->detect('symfony-composer-script');

        self::assertSame(1, $result['schema_version']);
        self::assertSame('symfony', $result['framework']);
        self::assertSame('symfony-twig-stimulus', $result['stack']);
        self::assertSame('7.1', $result['framework_version']);
        self::assertSame('8.2', $result['php']);
    }

    public function testPreferisceLoScriptComposerTestAlBinarioPhpunit(): void
    {
        self::assertSame('composer test', $this->detect('symfony-composer-script')['test']);
    }

    public function testRipiegaSuPhpunitQuandoNonCiSonoScriptNeMakefile(): void
    {
        self::assertSame('vendor/bin/phpunit', $this->detect('symfony-plain')['test']);
    }

    public function testRiconosceIlFrameworkMaLasciaLoStackNulloSenzaPacchetto(): void
    {
        $result = $this->detect('laravel-basic');

        self::assertSame('laravel', $result['framework']);
        self::assertNull($result['stack'], 'Nella v1 non esiste un pacchetto Laravel');
    }
}
```

- [ ] **Step 3: Eseguire i test e verificare che falliscano**

Run: `vendor/bin/phpunit --filter ProjectDetectorTest`
Expected: FAIL — `Class "Worky\ProjectDetector" not found`.

- [ ] **Step 4: Scrivere l'implementazione minima**

`src/ProjectDetector.php`:

```php
<?php

declare(strict_types=1);

namespace Worky;

final class ProjectDetector
{
    /** Pacchetto composer che identifica il framework => nome del framework. */
    private const FRAMEWORKS = [
        'symfony/framework-bundle' => 'symfony',
        'laravel/framework' => 'laravel',
    ];

    /**
     * Framework => pacchetto di stack in skills/stacks/.
     * Aggiungere un pacchetto significa aggiungere una riga qui.
     */
    private const STACK_PACKS = [
        'symfony' => 'symfony-twig-stimulus',
    ];

    public function __construct(private readonly string $projectDir)
    {
    }

    public function detect(): array
    {
        $composer = $this->readJson('composer.json');
        $framework = $this->detectFramework($composer);

        return [
            'schema_version' => 1,
            'framework' => $framework,
            'stack' => $framework === null ? null : (self::STACK_PACKS[$framework] ?? null),
            'framework_version' => $this->detectFrameworkVersion($composer, $framework),
            'php' => $this->versionFrom($composer['require']['php'] ?? null),
            'test' => $this->detectTestCommand($composer),
        ];
    }

    private function detectFramework(array $composer): ?string
    {
        $require = $composer['require'] ?? [];

        foreach (self::FRAMEWORKS as $package => $framework) {
            if (isset($require[$package])) {
                return $framework;
            }
        }

        return null;
    }

    private function detectFrameworkVersion(array $composer, ?string $framework): ?string
    {
        if ($framework === null) {
            return null;
        }

        $package = array_search($framework, self::FRAMEWORKS, true);

        return $this->versionFrom($composer['require'][$package] ?? null);
    }

    private function detectTestCommand(array $composer): ?string
    {
        if (isset($composer['scripts']['test'])) {
            return 'composer test';
        }

        if ($this->hasMakeTarget('test')) {
            return 'make test';
        }

        if ($this->exists('phpunit.xml.dist') || $this->exists('phpunit.xml')) {
            return 'vendor/bin/phpunit';
        }

        return null;
    }

    private function hasMakeTarget(string $target): bool
    {
        if (!$this->exists('Makefile')) {
            return false;
        }

        $makefile = (string) file_get_contents($this->path('Makefile'));

        return preg_match('/^' . preg_quote($target, '/') . ':/m', $makefile) === 1;
    }

    private function versionFrom(?string $constraint): ?string
    {
        if ($constraint === null) {
            return null;
        }

        return preg_match('/(\d+\.\d+)/', $constraint, $matches) === 1 ? $matches[1] : null;
    }

    private function readJson(string $relative): array
    {
        if (!$this->exists($relative)) {
            return [];
        }

        return json_decode((string) file_get_contents($this->path($relative)), true) ?? [];
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
```

- [ ] **Step 5: Eseguire i test e verificare che passino**

Run: `vendor/bin/phpunit --filter ProjectDetectorTest`
Expected: PASS (4 test).

- [ ] **Step 6: Commit**

```bash
git add src/ProjectDetector.php tests/ProjectDetectorTest.php tests/fixtures
git commit -m "Rileva framework, stack, versioni e comando di test del progetto"
```

---

### Task 3: Rilevamento di analisi statica, stile, percorsi, server e fixture

**Files:**
- Modify: `src/ProjectDetector.php`
- Modify: `tests/ProjectDetectorTest.php`
- Create: `tests/fixtures/symfony-full/composer.json`, `phpstan.neon`, `phpunit.xml.dist`, `.php-cs-fixer.dist.php`, `public/index.php`, `src/Entity/.gitkeep`, `src/Controller/.gitkeep`, `templates/.gitkeep`, `assets/controllers/.gitkeep`

**Interfaces:**
- Consumes: `Worky\ProjectDetector::detect()` dal Task 2
- Produces: chiavi aggiuntive nell'array di `detect()`: `test_functional` (string|null), `static_analysis` (string|null), `static_analysis_level` (int|null), `cs` (string|null), `server` (string|null), `fixtures` (string|null), `paths` (array con chiavi `entity`, `controller`, `templates`, `assets`, ciascuna string|null)

- [ ] **Step 1: Creare la fixture completa**

`tests/fixtures/symfony-full/composer.json`:

```json
{
    "require": {
        "php": ">=8.3",
        "symfony/framework-bundle": "7.2.*"
    },
    "require-dev": {
        "doctrine/doctrine-fixtures-bundle": "^3.6",
        "phpstan/phpstan": "^2.0"
    }
}
```

`tests/fixtures/symfony-full/phpstan.neon`:

```
parameters:
    level: 8
    paths:
        - src
```

`tests/fixtures/symfony-full/phpunit.xml.dist`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
    <testsuites>
        <testsuite name="unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="functional">
            <directory>tests/Functional</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

`tests/fixtures/symfony-full/.php-cs-fixer.dist.php`: un file PHP che restituisce un config vuoto, basta `<?php return [];`.

Creare anche i file/cartelle vuoti: `public/index.php` (contenuto `<?php`), `src/Entity/.gitkeep`, `src/Controller/.gitkeep`, `templates/.gitkeep`, `assets/controllers/.gitkeep`.

- [ ] **Step 2: Scrivere i test che falliscono**

Aggiungere a `tests/ProjectDetectorTest.php`:

```php
    public function testRilevaLaTestsuiteFunzionaleQuandoEDichiarata(): void
    {
        self::assertSame(
            'vendor/bin/phpunit --testsuite functional',
            $this->detect('symfony-full')['test_functional'],
        );
    }

    public function testLasciaNulloIlComandoFunzionaleSenzaTestsuiteDedicata(): void
    {
        self::assertNull($this->detect('symfony-plain')['test_functional']);
    }

    public function testRilevaPhpstanConIlSuoLivello(): void
    {
        $result = $this->detect('symfony-full');

        self::assertSame('vendor/bin/phpstan analyse --no-progress', $result['static_analysis']);
        self::assertSame(8, $result['static_analysis_level']);
    }

    public function testRilevaPhpCsFixer(): void
    {
        self::assertSame('vendor/bin/php-cs-fixer fix', $this->detect('symfony-full')['cs']);
    }

    public function testRilevaIlComandoDelServerQuandoEsistePublicIndex(): void
    {
        self::assertSame('php -S localhost:8000 -t public', $this->detect('symfony-full')['server']);
    }

    public function testRilevaIlComandoDelleFixtureQuandoIlBundleEPresente(): void
    {
        self::assertSame(
            'php bin/console doctrine:fixtures:load -n --env=test',
            $this->detect('symfony-full')['fixtures'],
        );
    }

    public function testMappaIPercorsiConvenzionaliEsistenti(): void
    {
        $paths = $this->detect('symfony-full')['paths'];

        self::assertSame('src/Entity', $paths['entity']);
        self::assertSame('src/Controller', $paths['controller']);
        self::assertSame('templates', $paths['templates']);
        self::assertSame('assets/controllers', $paths['assets']);
    }

    public function testLasciaNulliIPercorsiCheNonEsistono(): void
    {
        self::assertNull($this->detect('symfony-plain')['paths']['entity']);
    }
```

- [ ] **Step 3: Eseguire i test e verificare che falliscano**

Run: `vendor/bin/phpunit --filter ProjectDetectorTest`
Expected: FAIL — chiavi `static_analysis`, `cs`, `server`, `fixtures`, `paths` non definite.

- [ ] **Step 4: Estendere l'implementazione**

In `src/ProjectDetector.php`, estrarre il comando dei test in una variabile locale all'inizio di `detect()`:

```php
        $test = $this->detectTestCommand($composer);
```

e usarla nell'array (`'test' => $test,`). Poi aggiungere le chiavi al valore restituito da `detect()`, subito dopo `'test'`:

```php
            'test_functional' => $this->detectFunctionalTestCommand($test),
            'static_analysis' => $this->detectStaticAnalysis(),
            'static_analysis_level' => $this->detectStaticAnalysisLevel(),
            'cs' => $this->detectCodingStandard(),
            'server' => $this->detectServer(),
            'fixtures' => $this->detectFixtures($composer),
            'paths' => $this->detectPaths(),
```

e i metodi:

```php
    private function detectFunctionalTestCommand(?string $testCommand): ?string
    {
        if ($testCommand === null) {
            return null;
        }

        foreach (['phpunit.xml.dist', 'phpunit.xml'] as $candidate) {
            if (!$this->exists($candidate)) {
                continue;
            }

            $contents = (string) file_get_contents($this->path($candidate));

            if (preg_match('/<testsuite\s+name="([^"]*functional[^"]*)"/i', $contents, $matches) === 1) {
                return $testCommand . ' --testsuite ' . $matches[1];
            }
        }

        return null;
    }

    private function detectStaticAnalysis(): ?string
    {
        if ($this->exists('phpstan.neon') || $this->exists('phpstan.neon.dist')) {
            return 'vendor/bin/phpstan analyse --no-progress';
        }

        if ($this->exists('psalm.xml') || $this->exists('psalm.xml.dist')) {
            return 'vendor/bin/psalm --no-progress';
        }

        return null;
    }

    private function detectStaticAnalysisLevel(): ?int
    {
        foreach (['phpstan.neon', 'phpstan.neon.dist'] as $candidate) {
            if (!$this->exists($candidate)) {
                continue;
            }

            $contents = (string) file_get_contents($this->path($candidate));

            if (preg_match('/^\s*level:\s*(\d+)/m', $contents, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    private function detectCodingStandard(): ?string
    {
        if ($this->exists('.php-cs-fixer.dist.php') || $this->exists('.php-cs-fixer.php')) {
            return 'vendor/bin/php-cs-fixer fix';
        }

        if ($this->exists('ecs.php')) {
            return 'vendor/bin/ecs check --fix';
        }

        return null;
    }

    private function detectServer(): ?string
    {
        return $this->exists('public/index.php') ? 'php -S localhost:8000 -t public' : null;
    }

    private function detectFixtures(array $composer): ?string
    {
        $dev = $composer['require-dev'] ?? [];

        return isset($dev['doctrine/doctrine-fixtures-bundle'])
            ? 'php bin/console doctrine:fixtures:load -n --env=test'
            : null;
    }

    private function detectPaths(): array
    {
        $candidates = [
            'entity' => 'src/Entity',
            'controller' => 'src/Controller',
            'templates' => 'templates',
            'assets' => 'assets/controllers',
        ];

        $paths = [];

        foreach ($candidates as $key => $relative) {
            $paths[$key] = is_dir($this->path($relative)) ? $relative : null;
        }

        return $paths;
    }
```

- [ ] **Step 5: Eseguire i test e verificare che passino**

Run: `composer test`
Expected: PASS (13 test).

- [ ] **Step 6: Commit**

```bash
git add src/ProjectDetector.php tests
git commit -m "Rileva analisi statica, stile, percorsi, server e fixture"
```

---

### Task 4: Lettura e scrittura di `.worky.json`

**Files:**
- Create: `src/Config.php`
- Create: `src/MissingConfigException.php`
- Test: `tests/ConfigTest.php`

**Interfaces:**
- Consumes: l'array prodotto da `ProjectDetector::detect()`
- Produces: `Worky\Config::write(string $projectDir, array $data): string` (restituisce il percorso scritto), `Worky\Config::load(string $projectDir): array` (lancia `Worky\MissingConfigException`), `Worky\Config::FILENAME` = `.worky.json`

- [ ] **Step 1: Scrivere i test che falliscono**

`tests/ConfigTest.php`:

```php
<?php

declare(strict_types=1);

namespace Worky\Tests;

use PHPUnit\Framework\TestCase;
use Worky\Config;
use Worky\MissingConfigException;

final class ConfigTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/worky-config-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/' . Config::FILENAME);
        @rmdir($this->dir);
    }

    public function testScriveELeggeLaConfigurazione(): void
    {
        Config::write($this->dir, ['schema_version' => 1, 'test' => 'composer test']);

        self::assertSame(
            ['schema_version' => 1, 'test' => 'composer test'],
            Config::load($this->dir),
        );
    }

    public function testScriveJsonLeggibileConAcapoFinale(): void
    {
        $path = Config::write($this->dir, ['schema_version' => 1]);
        $contents = (string) file_get_contents($path);

        self::assertStringEndsWith("}\n", $contents);
        self::assertStringContainsString("\n    \"schema_version\"", $contents);
    }

    public function testLanciaUnEccezioneConIstruzioniQuandoLaConfigurazioneManca(): void
    {
        $this->expectException(MissingConfigException::class);
        $this->expectExceptionMessageMatches('/worky:onboard/');

        Config::load($this->dir);
    }
}
```

- [ ] **Step 2: Eseguire i test e verificare che falliscano**

Run: `vendor/bin/phpunit --filter ConfigTest`
Expected: FAIL — `Class "Worky\Config" not found`.

- [ ] **Step 3: Scrivere l'implementazione**

`src/MissingConfigException.php`:

```php
<?php

declare(strict_types=1);

namespace Worky;

final class MissingConfigException extends \RuntimeException
{
}
```

`src/Config.php`:

```php
<?php

declare(strict_types=1);

namespace Worky;

final class Config
{
    public const FILENAME = '.worky.json';

    public static function write(string $projectDir, array $data): string
    {
        $path = $projectDir . '/' . self::FILENAME;
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        file_put_contents($path, $json . "\n");

        return $path;
    }

    public static function load(string $projectDir): array
    {
        $path = $projectDir . '/' . self::FILENAME;

        if (!is_file($path)) {
            throw new MissingConfigException(sprintf(
                'worky: %s non trovato in %s. Esegui /worky:onboard per configurare il progetto.',
                self::FILENAME,
                $projectDir,
            ));
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (!is_array($data)) {
            throw new MissingConfigException(sprintf(
                'worky: %s non è un JSON valido. Correggilo o rilancia /worky:onboard.',
                $path,
            ));
        }

        return $data;
    }
}
```

- [ ] **Step 4: Eseguire i test e verificare che passino**

Run: `composer test`
Expected: PASS (16 test).

- [ ] **Step 5: Commit**

```bash
git add src/Config.php src/MissingConfigException.php tests/ConfigTest.php
git commit -m "Aggiunge lettura e scrittura di .worky.json"
```

---

### Task 5: Hook di lint PHP dopo ogni scrittura

**Files:**
- Create: `hooks/php-lint.php`
- Test: `tests/Hook/PhpLintHookTest.php`

**Interfaces:**
- Consumes: niente (script autonomo, nessun autoload)
- Produces: `hooks/php-lint.php`, eseguibile come `php hooks/php-lint.php` con JSON su stdin nella forma `{"tool_name": "Write", "tool_input": {"file_path": "..."}}`. Esce 0 se non c'è nulla da controllare o se il file è valido, 2 con motivo su stderr se la sintassi è rotta.

- [ ] **Step 1: Scrivere i test che falliscono**

`tests/Hook/PhpLintHookTest.php`:

```php
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
```

- [ ] **Step 2: Eseguire i test e verificare che falliscano**

Run: `vendor/bin/phpunit --filter PhpLintHookTest`
Expected: FAIL — lo script non esiste, `php` esce con codice diverso da 2.

- [ ] **Step 3: Scrivere l'hook**

`hooks/php-lint.php`:

```php
<?php

declare(strict_types=1);

/**
 * PostToolUse: dopo ogni Write/Edit su un file PHP ne verifica la sintassi.
 * Nessun autoload: questo script gira anche in un plugin installato senza vendor/.
 */

$raw = stream_get_contents(STDIN);
$input = json_decode((string) $raw, true);

if (!is_array($input)) {
    exit(0);
}

$path = $input['tool_input']['file_path'] ?? '';

if (!is_string($path) || !str_ends_with($path, '.php') || !is_file($path)) {
    exit(0);
}

exec(sprintf('php -l %s 2>&1', escapeshellarg($path)), $output, $code);

if ($code !== 0) {
    fwrite(STDERR, sprintf(
        "worky: errore di sintassi PHP in %s\n%s\nCorreggi il file prima di proseguire.\n",
        $path,
        implode("\n", $output),
    ));
    exit(2);
}

exit(0);
```

- [ ] **Step 4: Eseguire i test e verificare che passino**

Run: `vendor/bin/phpunit --filter PhpLintHookTest`
Expected: PASS (4 test).

- [ ] **Step 5: Commit**

```bash
git add hooks/php-lint.php tests/Hook/PhpLintHookTest.php
git commit -m "Aggiunge l'hook di lint PHP sulle scritture"
```

---

### Task 6: Gate di qualità prima della pull request

**Files:**
- Create: `hooks/pre-pr-gate.php`
- Test: `tests/Hook/PrePrGateHookTest.php`

**Interfaces:**
- Consumes: `Worky\Config` (Task 4), incluso con `require_once __DIR__ . '/../src/Config.php'`
- Produces: `hooks/pre-pr-gate.php`, hook PreToolUse su `Bash`. Intercetta solo i comandi che contengono `gh pr create`; esegue `test` e `static_analysis` presi da `.worky.json` nella directory `$CLAUDE_PROJECT_DIR`. Esce 2 se uno dei due fallisce o se la configurazione manca.

- [ ] **Step 1: Scrivere i test che falliscono**

`tests/Hook/PrePrGateHookTest.php`:

```php
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
```

- [ ] **Step 2: Eseguire i test e verificare che falliscano**

Run: `vendor/bin/phpunit --filter PrePrGateHookTest`
Expected: FAIL — lo script non esiste.

- [ ] **Step 3: Scrivere l'hook**

`hooks/pre-pr-gate.php`:

```php
<?php

declare(strict_types=1);

/**
 * PreToolUse su Bash: prima di aprire una pull request esegue i comandi di
 * qualità dichiarati in .worky.json. Nessun autoload di Composer.
 */

require_once __DIR__ . '/../src/MissingConfigException.php';
require_once __DIR__ . '/../src/Config.php';

use Worky\Config;
use Worky\MissingConfigException;

$input = json_decode((string) stream_get_contents(STDIN), true);

if (!is_array($input) || ($input['tool_name'] ?? '') !== 'Bash') {
    exit(0);
}

$command = $input['tool_input']['command'] ?? '';

if (!is_string($command) || preg_match('/\bgh\s+pr\s+create\b/', $command) !== 1) {
    exit(0);
}

$projectDir = getenv('CLAUDE_PROJECT_DIR') ?: getcwd();

try {
    $config = Config::load($projectDir);
} catch (MissingConfigException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(2);
}

foreach (['test', 'static_analysis'] as $key) {
    $gate = $config[$key] ?? null;

    if (!is_string($gate) || $gate === '') {
        continue;
    }

    $output = [];
    exec(sprintf('cd %s && %s 2>&1', escapeshellarg($projectDir), $gate), $output, $code);

    if ($code !== 0) {
        fwrite(STDERR, sprintf(
            "worky: gate '%s' fallito prima della pull request.\nComando: %s\n%s\n"
            . "Sistema il problema e riprova: la PR non viene aperta con il rosso.\n",
            $key,
            $gate,
            implode("\n", array_slice($output, -30)),
        ));
        exit(2);
    }
}

exit(0);
```

- [ ] **Step 4: Eseguire i test e verificare che passino**

Run: `vendor/bin/phpunit --filter PrePrGateHookTest`
Expected: PASS (6 test).

- [ ] **Step 5: Commit**

```bash
git add hooks/pre-pr-gate.php tests/Hook/PrePrGateHookTest.php
git commit -m "Aggiunge il gate di qualita che precede l'apertura della PR"
```

---

### Task 7: Registrazione degli hook nel plugin

**Files:**
- Create: `hooks/hooks.json`
- Test: `tests/HooksRegistrationTest.php`

**Interfaces:**
- Consumes: `hooks/php-lint.php` (Task 5), `hooks/pre-pr-gate.php` (Task 6)
- Produces: `hooks/hooks.json` nel formato plugin (`{"hooks": {...}}`), con `PostToolUse` su `Write|Edit` e `PreToolUse` su `Bash`

- [ ] **Step 1: Scrivere il test che fallisce**

`tests/HooksRegistrationTest.php`:

```php
<?php

declare(strict_types=1);

namespace Worky\Tests;

use PHPUnit\Framework\TestCase;

final class HooksRegistrationTest extends TestCase
{
    private function config(): array
    {
        $path = __DIR__ . '/../hooks/hooks.json';
        self::assertFileExists($path);

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testRegistraILintSulleScrittureEIlGateSuBash(): void
    {
        $hooks = $this->config()['hooks'];

        self::assertSame('Write|Edit', $hooks['PostToolUse'][0]['matcher']);
        self::assertSame('Bash', $hooks['PreToolUse'][0]['matcher']);
    }

    public function testOgniScriptReferenziatoEsiste(): void
    {
        $hooks = $this->config()['hooks'];
        $found = 0;

        foreach ($hooks as $entries) {
            foreach ($entries as $entry) {
                foreach ($entry['hooks'] as $hook) {
                    self::assertSame('command', $hook['type']);
                    preg_match('/\$\{CLAUDE_PLUGIN_ROOT\}([^"\']+)/', $hook['command'], $matches);
                    self::assertNotEmpty($matches, 'Ogni comando deve usare ${CLAUDE_PLUGIN_ROOT}');
                    self::assertFileExists(__DIR__ . '/..' . $matches[1]);
                    ++$found;
                }
            }
        }

        self::assertSame(2, $found);
    }
}
```

- [ ] **Step 2: Eseguire il test e verificare che fallisca**

Run: `vendor/bin/phpunit --filter HooksRegistrationTest`
Expected: FAIL — `hooks/hooks.json` non esiste.

- [ ] **Step 3: Scrivere `hooks/hooks.json`**

```json
{
  "description": "Gate di qualita worky: lint dei file PHP scritti e verifica prima della pull request",
  "hooks": {
    "PostToolUse": [
      {
        "matcher": "Write|Edit",
        "hooks": [
          {
            "type": "command",
            "command": "php \"${CLAUDE_PLUGIN_ROOT}/hooks/php-lint.php\"",
            "timeout": 15
          }
        ]
      }
    ],
    "PreToolUse": [
      {
        "matcher": "Bash",
        "hooks": [
          {
            "type": "command",
            "command": "php \"${CLAUDE_PLUGIN_ROOT}/hooks/pre-pr-gate.php\"",
            "timeout": 900
          }
        ]
      }
    ]
  }
}
```

- [ ] **Step 4: Eseguire i test e verificare che passino**

Run: `composer test`
Expected: PASS (tutti i test, 28 in totale).

- [ ] **Step 5: Commit**

```bash
git add hooks/hooks.json tests/HooksRegistrationTest.php
git commit -m "Registra gli hook di lint e del gate pre-PR"
```

---

### Task 8: Comando `/worky:onboard`

**Files:**
- Create: `scripts/detect.php`
- Create: `commands/onboard.md`
- Test: `tests/DetectScriptTest.php`

**Interfaces:**
- Consumes: `Worky\ProjectDetector` (Task 2 e 3), `Worky\Config` (Task 4)
- Produces: `scripts/detect.php` — con `--write <dir>` scrive `.worky.json`, senza flag stampa il JSON rilevato su stdout. Il comando `/worky:onboard` lo esegue, mostra il risultato all'utente e scrive solo dopo conferma.

- [ ] **Step 1: Scrivere il test che fallisce**

`tests/DetectScriptTest.php`:

```php
<?php

declare(strict_types=1);

namespace Worky\Tests;

use PHPUnit\Framework\TestCase;
use Worky\Config;

final class DetectScriptTest extends TestCase
{
    private function run(array $args): array
    {
        $script = __DIR__ . '/../scripts/detect.php';
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(array_merge(['php', $script], $args), $descriptors, $pipes);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }

    public function testStampaIlRilevamentoSenzaScrivereNulla(): void
    {
        $fixture = __DIR__ . '/fixtures/symfony-full';

        [$code, $stdout] = $this->run([$fixture]);

        self::assertSame(0, $code);
        $data = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('symfony-twig-stimulus', $data['stack']);
        self::assertFileDoesNotExist($fixture . '/' . Config::FILENAME);
    }

    public function testConWriteScriveLaConfigurazione(): void
    {
        $dir = sys_get_temp_dir() . '/worky-detect-' . bin2hex(random_bytes(6));
        mkdir($dir);
        copy(__DIR__ . '/fixtures/symfony-full/composer.json', $dir . '/composer.json');

        [$code] = $this->run(['--write', $dir]);

        self::assertSame(0, $code);
        self::assertSame('symfony', Config::load($dir)['framework']);

        unlink($dir . '/composer.json');
        unlink($dir . '/' . Config::FILENAME);
        rmdir($dir);
    }
}
```

- [ ] **Step 2: Eseguire i test e verificare che falliscano**

Run: `vendor/bin/phpunit --filter DetectScriptTest`
Expected: FAIL — `scripts/detect.php` non esiste.

- [ ] **Step 3: Scrivere lo script**

`scripts/detect.php`:

```php
<?php

declare(strict_types=1);

/**
 * Rileva la configurazione di un progetto.
 *
 *   php scripts/detect.php [dir]            stampa il JSON rilevato
 *   php scripts/detect.php --write [dir]    scrive .worky.json nella directory
 *
 * Nessun autoload di Composer: funziona anche da plugin installato.
 */

require_once __DIR__ . '/../src/ProjectDetector.php';
require_once __DIR__ . '/../src/MissingConfigException.php';
require_once __DIR__ . '/../src/Config.php';

use Worky\Config;
use Worky\ProjectDetector;

$args = array_slice($argv, 1);
$write = in_array('--write', $args, true);
$positional = array_values(array_filter($args, static fn (string $a): bool => $a !== '--write'));
$projectDir = rtrim($positional[0] ?? (getenv('CLAUDE_PROJECT_DIR') ?: getcwd()), '/');

if (!is_dir($projectDir)) {
    fwrite(STDERR, sprintf("worky: la directory %s non esiste.\n", $projectDir));
    exit(1);
}

$detected = (new ProjectDetector($projectDir))->detect();

if ($write) {
    $path = Config::write($projectDir, $detected);
    fwrite(STDOUT, sprintf("Configurazione scritta in %s\n", $path));
    exit(0);
}

fwrite(STDOUT, json_encode($detected, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
exit(0);
```

- [ ] **Step 4: Eseguire i test e verificare che passino**

Run: `vendor/bin/phpunit --filter DetectScriptTest`
Expected: PASS (2 test).

- [ ] **Step 5: Scrivere il comando**

`commands/onboard.md`:

```markdown
---
description: Rileva stack, comandi e convenzioni del progetto e scrive .worky.json
allowed-tools: Bash(php:*), Read, Glob, Write, AskUserQuestion
---

## Rilevamento

!`php "${CLAUDE_PLUGIN_ROOT}/scripts/detect.php"`

## Il tuo compito

Il blocco qui sopra è ciò che worky ha deducibilmente rilevato in questo progetto.

1. Presenta il rilevamento all'utente in forma leggibile, non come JSON grezzo:
   stack, versioni, comando dei test, analisi statica e livello, stile, come si
   avvia l'applicazione, come si caricano le fixture, percorsi convenzionali.

2. Segnala esplicitamente ogni campo rilevato come `null` e spiega cosa
   comporta. Un `test` nullo significa che nessun agente potrà dichiarare un
   task completo: va risolto prima di procedere.

3. Se `stack` è `null` ma `framework` no, dillo chiaramente: il framework è
   riconosciuto ma non esiste ancora un pacchetto di convenzioni per esso, e il
   team lavorerà senza conoscenza specifica dello stack.

4. Verifica sul campo i comandi dubbi prima di confermarli. Se il comando dei
   test è `vendor/bin/phpunit`, controlla che il binario esista davvero.

5. Chiedi conferma all'utente con AskUserQuestion, proponendo le correzioni che
   ritieni necessarie. **Non scrivere nulla prima della conferma.**

6. Dopo la conferma esegui `php "${CLAUDE_PLUGIN_ROOT}/scripts/detect.php" --write .`
   e poi applica a mano le eventuali correzioni concordate al file `.worky.json`.

7. Suggerisci all'utente di committare `.worky.json`: è configurazione del
   progetto, non un file personale.
```

- [ ] **Step 6: Commit**

```bash
git add scripts/detect.php commands/onboard.md tests/DetectScriptTest.php
git commit -m "Aggiunge il comando /worky:onboard e lo script di rilevamento"
```

---

### Task 9: Skill della pipeline (`worky-workflow`)

**Files:**
- Create: `skills/worky-workflow/SKILL.md`
- Test: `tests/SkillsFrontmatterTest.php`

**Interfaces:**
- Consumes: niente
- Produces: skill `worky-workflow`, richiamata dai comandi `/worky:feature` e `/worky:ship` (Task 13) e citata dagli agenti (Task 11 e 12)

- [ ] **Step 1: Scrivere il test che fallisce**

`tests/SkillsFrontmatterTest.php`:

```php
<?php

declare(strict_types=1);

namespace Worky\Tests;

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

    public function testEsisteLaSkillDellaPipeline(): void
    {
        self::assertFileExists(__DIR__ . '/../skills/worky-workflow/SKILL.md');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('skillFiles')]
    public function testOgniSkillHaNomeEDescrizioneNelFrontmatter(string $path): void
    {
        $contents = (string) file_get_contents($path);

        self::assertSame(1, preg_match('/^---\n(.*?)\n---\n/s', $contents, $matches), "Frontmatter mancante in $path");
        self::assertSame(1, preg_match('/^name:\s*\S+/m', $matches[1]), "Campo name mancante in $path");
        self::assertSame(1, preg_match('/^description:\s*\S+/m', $matches[1]), "Campo description mancante in $path");
    }
}
```

- [ ] **Step 2: Eseguire il test e verificare che fallisca**

Run: `vendor/bin/phpunit --filter SkillsFrontmatterTest`
Expected: FAIL — `skills/worky-workflow/SKILL.md` non esiste.

- [ ] **Step 3: Scrivere la skill**

`skills/worky-workflow/SKILL.md`:

```markdown
---
name: worky-workflow
description: Use when running the worky team pipeline - taking a feature request through spec, plan, parallel implementation, QA and review to a pull request, with the two human gates
---

# La pipeline di worky

Questa skill descrive **come lavora il team**. Non contiene conoscenza di
framework: quella sta nel pacchetto di stack indicato da `.worky.json`.

## Prerequisito

`.worky.json` deve esistere nella radice del progetto. Se manca, fermati e
chiedi all'utente di eseguire `/worky:onboard`. Non indovinare mai i comandi di
un progetto.

Carica il pacchetto di stack indicato dal campo `stack`. Se è `null`, dillo
all'utente: il team lavorerà senza convenzioni specifiche.

## Le fasi

1. **Spec** — `worky-analyst` esplora il progetto e scrive una spec in
   `docs/specs/`. → **Gate 1: la approva l'utente.** Non si prosegue senza.
2. **Piano** — dalla spec nasce un piano a task con dipendenze esplicite
   (skill `superpowers:writing-plans`).
3. **Isolamento** — si apre un git worktree per la feature
   (skill `superpowers:using-git-worktrees`).
4. **Implementazione** — i task senza dipendenze reciproche vanno in parallelo a
   `worky-backend` e `worky-frontend`; gli altri in sequenza. Ogni task segue
   `superpowers:test-driven-development`.
5. **QA** — `worky-qa` esegue la suite completa e verifica la feature contro la
   **spec**, non contro il piano.
6. **Review** — `worky-reviewer` produce i rilievi; tornano agli sviluppatori.
7. **Consegna** — push del branch e `gh pr create` con descrizione derivata
   dalla spec. → **Gate 2: merge dell'utente.**

## Regole non negoziabili

- **Completamento provato.** Nessun agente dichiara un task finito senza
  allegare l'output del comando di test preso da `.worky.json`. Una frase come
  "i test passano" senza output è un task non finito.
- **Tre fallimenti e ci si ferma.** Se un agente fallisce tre volte sullo stesso
  task, smette e riporta cosa ha provato. Non accumula workaround.
- **Rosso significa indagine.** Un test che fallisce attiva
  `superpowers:systematic-debugging`: ipotesi, verifica, causa radice. Mai
  tentativi a caso.
- **Due giri di review.** Se dopo due cicli di rilievi il team non converge, la
  pipeline si ferma e chiama l'utente.
- **I gate sono dell'utente.** Nessun agente approva una spec o mergia una PR.
```

- [ ] **Step 4: Eseguire i test e verificare che passino**

Run: `vendor/bin/phpunit --filter SkillsFrontmatterTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add skills/worky-workflow tests/SkillsFrontmatterTest.php
git commit -m "Aggiunge la skill della pipeline worky"
```

---

### Task 10: Pacchetto di stack `symfony-twig-stimulus`

**Files:**
- Create: `skills/stacks/symfony-twig-stimulus/SKILL.md`
- Modify: `tests/SkillsFrontmatterTest.php`

**Interfaces:**
- Consumes: il campo `stack` di `.worky.json` (Task 4), valorizzato `symfony-twig-stimulus` dal detector (Task 2)
- Produces: skill `worky-stack-symfony-twig-stimulus`, caricata dagli agenti quando `.worky.json` la indica

- [ ] **Step 1: Scrivere il test che fallisce**

Aggiungere a `tests/SkillsFrontmatterTest.php`:

```php
    public function testIlPacchettoSymfonyEsisteEDichiaraIlProprioNome(): void
    {
        $path = __DIR__ . '/../skills/stacks/symfony-twig-stimulus/SKILL.md';
        self::assertFileExists($path);

        $contents = (string) file_get_contents($path);
        self::assertStringContainsString('name: worky-stack-symfony-twig-stimulus', $contents);
    }

    public function testIlPacchettoNonContieneRiferimentiAllaPipeline(): void
    {
        $contents = (string) file_get_contents(
            __DIR__ . '/../skills/stacks/symfony-twig-stimulus/SKILL.md',
        );

        self::assertStringNotContainsString(
            'gh pr create',
            $contents,
            'Il pacchetto di stack deve contenere solo convenzioni, non la pipeline',
        );
    }
```

- [ ] **Step 2: Eseguire i test e verificare che falliscano**

Run: `vendor/bin/phpunit --filter SkillsFrontmatterTest`
Expected: FAIL — il file del pacchetto non esiste.

- [ ] **Step 3: Scrivere il pacchetto**

`skills/stacks/symfony-twig-stimulus/SKILL.md`:

```markdown
---
name: worky-stack-symfony-twig-stimulus
description: Use when working on a Symfony project with Twig templates and Stimulus controllers - house conventions for structure, testing and naming that go beyond the framework documentation
---

# Convenzioni Symfony / Twig / Stimulus

Questo pacchetto **non** ripete la documentazione di Symfony. Contiene le
decisioni di casa: quelle che non puoi dedurre leggendo symfony.com.

Leggi `.worky.json` per i comandi e i percorsi reali di **questo** progetto: i
percorsi citati qui sono le convenzioni predefinite, non certezze.

## Struttura

- I controller non parlano con Doctrine. Interrogano un service o un repository.
- La logica applicativa sta in servizi con una responsabilità sola, non in
  classi `Manager` o `Helper` che crescono all'infinito.
- Un repository restituisce entità o DTO, mai array associativi grezzi verso
  il controller.
- Le entità non contengono logica di presentazione.

## Test

- Test unitario per la logica dei servizi, senza container.
- Test funzionale con `WebTestCase` per ogni rotta nuova: almeno lo status code
  e un elemento distintivo della pagina.
- I test che toccano il database usano il comando fixture dichiarato in
  `.worky.json`, non dati creati a mano nel test.
- Il nome del test descrive il comportamento, non il metodo:
  `testRifiutaUnOrdineSenzaRighe`, non `testValidate`.

## Twig

- Nessuna logica applicativa nei template: niente query, niente calcoli di
  business. Solo presentazione.
- I template ereditano da un layout; i frammenti riusabili sono include o
  component, non copia-incolla.

## Stimulus e JS vanilla

- Un controller Stimulus per comportamento, nominato come il comportamento
  (`dropdown_controller.js`), non come la pagina.
- I dati dal server passano per `data-*` values, non per variabili globali.
- Nessuna dipendenza npm nuova senza chiederlo all'utente: lo stack è vanilla
  per scelta.
```

- [ ] **Step 4: Eseguire i test e verificare che passino**

Run: `composer test`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add skills/stacks tests/SkillsFrontmatterTest.php
git commit -m "Aggiunge il pacchetto di stack symfony-twig-stimulus"
```

---

### Task 11: Agenti `analyst` e `backend`

**Files:**
- Create: `agents/worky-analyst.md`
- Create: `agents/worky-backend.md`
- Test: `tests/AgentsTest.php`

**Interfaces:**
- Consumes: skill `worky-workflow` (Task 9), pacchetto di stack (Task 10), `.worky.json` (Task 4)
- Produces: agenti `worky-analyst` e `worky-backend`, invocabili dalla pipeline (Task 13)

- [ ] **Step 1: Scrivere il test che fallisce**

`tests/AgentsTest.php`:

```php
<?php

declare(strict_types=1);

namespace Worky\Tests;

use PHPUnit\Framework\TestCase;

final class AgentsTest extends TestCase
{
    private const EXPECTED = [
        'worky-analyst',
        'worky-backend',
    ];

    /** @return iterable<string, array{0: string}> */
    public static function agentNames(): iterable
    {
        foreach (self::EXPECTED as $name) {
            yield $name => [$name];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('agentNames')]
    public function testOgniAgenteEsisteEDichiaraNomeEDescrizione(string $name): void
    {
        $path = __DIR__ . '/../agents/' . $name . '.md';
        self::assertFileExists($path);

        $contents = (string) file_get_contents($path);

        self::assertSame(1, preg_match('/^---\n(.*?)\n---\n/s', $contents, $matches));
        self::assertStringContainsString('name: ' . $name, $matches[1]);
        self::assertSame(1, preg_match('/^description:\s*\S+/m', $matches[1]));
        self::assertSame(1, preg_match('/^tools:\s*\S+/m', $matches[1]));
    }

    public function testLAnalystNonPuoScrivereCodice(): void
    {
        $contents = (string) file_get_contents(__DIR__ . '/../agents/worky-analyst.md');
        preg_match('/^tools:\s*(.+)$/m', $contents, $matches);

        self::assertStringNotContainsString('Edit', $matches[1]);
    }
}
```

- [ ] **Step 2: Eseguire i test e verificare che falliscano**

Run: `vendor/bin/phpunit --filter AgentsTest`
Expected: FAIL — i file degli agenti non esistono.

- [ ] **Step 3: Scrivere `agents/worky-analyst.md`**

```markdown
---
name: worky-analyst
description: Trasforma una richiesta di feature in una spec approvabile esplorando il codice esistente, senza scrivere codice di produzione
tools: Glob, Grep, Read, Write, Bash, WebFetch
model: opus
---

Sei l'analista del team. Trasformi una richiesta vaga in una spec che un altro
agente possa implementare senza doverti chiedere nulla.

## Vincoli

- **Non scrivi codice di produzione.** L'unico file che scrivi è la spec.
- Leggi `.worky.json` prima di tutto. Se manca, fermati e chiedi
  `/worky:onboard`.
- Carica il pacchetto di stack indicato e rispettane le convenzioni quando
  descrivi la soluzione.

## Processo

1. Esplora il codice esistente prima di proporre qualsiasi cosa. Cerca una
   funzionalità simile già presente: spesso la feature richiesta è una
   variazione di qualcosa che esiste.
2. Elenca le domande la cui risposta cambierebbe la soluzione. Ponile
   all'utente. Non riempire i buchi con assunzioni silenziose.
3. Scrivi la spec in `docs/specs/YYYY-MM-DD-<nome>.md` con: obiettivo, vincoli,
   comportamento atteso, casi limite, cosa resta fuori, criteri di accettazione
   verificabili.
4. I criteri di accettazione devono essere verificabili da una macchina o da
   un'ispezione precisa. "L'utente è soddisfatto" non è un criterio.

## Output

Il percorso della spec e un riassunto di dieci righe delle decisioni prese e
delle alternative scartate, con il motivo.
```

- [ ] **Step 4: Scrivere `agents/worky-backend.md`**

```markdown
---
name: worky-backend
description: Implementa task backend in TDD sul progetto corrente, seguendo le convenzioni del pacchetto di stack e provando il completamento con l'output dei test
tools: Glob, Grep, Read, Write, Edit, Bash
model: sonnet
---

Sei lo sviluppatore backend del team. Ricevi **un** task del piano e lo porti a
termine.

## Prima di toccare qualsiasi cosa

1. Leggi `.worky.json`: da lì prendi il comando dei test, quello di analisi
   statica, quello di stile e i percorsi. Non inventarli.
2. Carica il pacchetto di stack indicato nel campo `stack` e seguine le
   convenzioni.
3. Leggi il codice intorno al punto in cui interverrai. Il codice esistente ha
   la precedenza sulle tue preferenze.

## Come lavori

Segui `superpowers:test-driven-development` alla lettera:

1. Scrivi il test che descrive il comportamento richiesto.
2. Eseguilo e **verifica che fallisca per il motivo giusto**. Un test che
   fallisce perché la classe non esiste non ha ancora provato nulla.
3. Scrivi il minimo che lo fa passare.
4. Rieseguilo.
5. Rifattorizza con i test verdi.

Quando un test fallisce in modo inatteso usa `superpowers:systematic-debugging`:
ipotesi, verifica, causa radice. Niente tentativi a caso.

## Quando ti fermi

Dopo **tre** tentativi falliti sullo stesso problema smetti e riporti: cosa hai
provato, cosa hai osservato, qual è la tua ipotesi migliore. Non accumuli
workaround.

## Come consegni

Il task è completo solo con:

- il diff dei file toccati,
- **l'output vero** del comando dei test di `.worky.json`,
- l'esito dell'analisi statica se configurata.

Senza output dei test il task non è completo, qualunque sia la tua impressione.
```

- [ ] **Step 5: Eseguire i test e verificare che passino**

Run: `vendor/bin/phpunit --filter AgentsTest`
Expected: PASS (3 test).

- [ ] **Step 6: Commit**

```bash
git add agents/worky-analyst.md agents/worky-backend.md tests/AgentsTest.php
git commit -m "Aggiunge gli agenti analyst e backend"
```

---

### Task 12: Agenti `frontend`, `qa` e `reviewer`

**Files:**
- Create: `agents/worky-frontend.md`
- Create: `agents/worky-qa.md`
- Create: `agents/worky-reviewer.md`
- Modify: `tests/AgentsTest.php`

**Interfaces:**
- Consumes: le stesse dipendenze del Task 11
- Produces: agenti `worky-frontend`, `worky-qa`, `worky-reviewer`

- [ ] **Step 1: Estendere il test**

In `tests/AgentsTest.php`, portare la costante a:

```php
    private const EXPECTED = [
        'worky-analyst',
        'worky-backend',
        'worky-frontend',
        'worky-qa',
        'worky-reviewer',
    ];
```

e aggiungere:

```php
    public function testIlQaNonScriveCodiceDiProduzione(): void
    {
        $contents = (string) file_get_contents(__DIR__ . '/../agents/worky-qa.md');
        preg_match('/^tools:\s*(.+)$/m', $contents, $matches);

        self::assertStringNotContainsString('Edit', $matches[1]);
    }

    public function testIlReviewerNonModificaIlCodiceCheRivede(): void
    {
        $contents = (string) file_get_contents(__DIR__ . '/../agents/worky-reviewer.md');
        preg_match('/^tools:\s*(.+)$/m', $contents, $matches);

        self::assertStringNotContainsString('Edit', $matches[1]);
    }
```

- [ ] **Step 2: Eseguire i test e verificare che falliscano**

Run: `vendor/bin/phpunit --filter AgentsTest`
Expected: FAIL — i tre file non esistono.

- [ ] **Step 3: Scrivere `agents/worky-frontend.md`**

```markdown
---
name: worky-frontend
description: Implementa task frontend in TDD - template, controller di comportamento e stili - seguendo le convenzioni del pacchetto di stack e provando il completamento con l'output dei test
tools: Glob, Grep, Read, Write, Edit, Bash
model: sonnet
---

Sei lo sviluppatore frontend del team. Ricevi **un** task del piano.

Valgono per te tutte le regole di `worky-backend`: leggi `.worky.json` prima di
toccare qualsiasi cosa, carica il pacchetto di stack, lavora in TDD, fermati
dopo tre fallimenti, consegna solo con l'output vero dei test.

In più:

- Una rotta nuova o modificata richiede un test funzionale che verifichi almeno
  lo status code e un elemento distintivo della pagina.
- La logica di comportamento sta nei controller lato client, non nei template.
- Nessuna dipendenza npm nuova senza chiedere all'utente.
- Verifica il rendering reale quando il progetto dichiara un comando `server` in
  `.worky.json`: un template che compila non è un template che funziona.
```

- [ ] **Step 4: Scrivere `agents/worky-qa.md`**

```markdown
---
name: worky-qa
description: Verifica in modo indipendente che una feature implementata soddisfi la spec, eseguendo la suite completa e cercando i casi limite che il piano non copriva
tools: Glob, Grep, Read, Bash
model: sonnet
---

Sei il QA del team. Il tuo valore è l'**indipendenza**: non hai scritto tu il
codice e non leggi il piano come fosse la verità.

## Cosa non fai

Non scrivi i test di produzione: li scrive chi implementa, altrimenti il TDD non
è TDD. Non modifichi il codice. Se trovi un problema, lo descrivi.

## Cosa fai

1. Leggi la **spec**, non il piano. La domanda a cui rispondi è: "questa feature
   fa ciò che la spec dice?", non "il piano è stato eseguito?".
2. Esegui la suite completa con il comando di `.worky.json`, non solo i test
   della feature. Le regressioni stanno altrove.
3. Esegui l'analisi statica se configurata.
4. Cerca i casi limite che la spec implica e nessuno ha coperto: input vuoti,
   valori al confine, concorrenza, permessi, errori di rete, dati assenti.
5. Quando il progetto dichiara un comando `server`, prova il percorso utente
   reale, non solo i test.

## Output

Un rapporto con: esito della suite (output vero, non la tua parola), esito
dell'analisi statica, elenco puntato delle discrepanze rispetto alla spec, e i
casi limite scoperti, con la loro gravità. Se è tutto a posto, dillo in
una riga e allega le prove.
```

- [ ] **Step 5: Scrivere `agents/worky-reviewer.md`**

```markdown
---
name: worky-reviewer
description: Fa review avversariale di un diff prima della pull request, cercando difetti di correttezza, violazioni delle convenzioni e duplicazione di codice gia presente nel progetto
tools: Glob, Grep, Read, Bash
model: opus
---

Sei il revisore del team. Il tuo compito non è approvare: è trovare ciò che non
va prima che lo trovi la produzione.

## Cosa non fai

Non modifichi il codice che rivedi. Produci rilievi; le correzioni le fa chi ha
scritto il codice.

## Cosa cerchi, in quest'ordine

1. **Correttezza.** Per ogni rilievo devi saper dire con quali input concreti il
   codice produce il risultato sbagliato. Se non sai costruire lo scenario, non
   è un rilievo: è un'impressione.
2. **Duplicazione.** Questa cosa esiste già nel progetto? Cerca prima di
   giudicare. È il difetto più costoso e il meno visibile nei diff.
3. **Convenzioni.** Confronta con il pacchetto di stack indicato in
   `.worky.json` e con il codice circostante.
4. **Test.** I test provano il comportamento o solo che il codice gira? Un test
   che passerebbe anche con l'implementazione sbagliata non è un test.

## Come riporti

Ogni rilievo ha: file e riga, cosa non va, lo scenario concreto in cui si
manifesta, e la gravità (bloccante / da sistemare / opinione). Separa le
opinioni dai difetti: un revisore che le mescola viene ignorato su entrambe.

Se dopo due cicli di rilievi non si converge, fermati e dillo all'utente.
```

- [ ] **Step 6: Eseguire i test e verificare che passino**

Run: `composer test`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add agents tests/AgentsTest.php
git commit -m "Aggiunge gli agenti frontend, qa e reviewer"
```

---

### Task 13: Comandi `/worky:feature` e `/worky:ship`

**Files:**
- Create: `commands/feature.md`
- Create: `commands/ship.md`
- Test: `tests/CommandsTest.php`

**Interfaces:**
- Consumes: skill `worky-workflow` (Task 9), i cinque agenti (Task 11 e 12)
- Produces: `/worky:feature <descrizione>` (pipeline completa) e `/worky:ship` (QA, review e PR su un branch già pronto)

- [ ] **Step 1: Scrivere il test che fallisce**

`tests/CommandsTest.php`:

```php
<?php

declare(strict_types=1);

namespace Worky\Tests;

use PHPUnit\Framework\TestCase;

final class CommandsTest extends TestCase
{
    /** @return iterable<string, array{0: string}> */
    public static function commands(): iterable
    {
        foreach (['onboard', 'feature', 'ship'] as $name) {
            yield $name => [$name];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('commands')]
    public function testOgniComandoEsisteEDichiaraUnaDescrizione(string $name): void
    {
        $path = __DIR__ . '/../commands/' . $name . '.md';
        self::assertFileExists($path);

        $contents = (string) file_get_contents($path);

        self::assertSame(1, preg_match('/^---\n(.*?)\n---\n/s', $contents, $matches));
        self::assertSame(1, preg_match('/^description:\s*\S+/m', $matches[1]));
    }

    public function testLaFeatureUsaLArgomentoRicevuto(): void
    {
        $contents = (string) file_get_contents(__DIR__ . '/../commands/feature.md');

        self::assertStringContainsString('$ARGUMENTS', $contents);
    }

    public function testEntrambiIComandiRichiamanoLaSkillDellaPipeline(): void
    {
        foreach (['feature', 'ship'] as $name) {
            $contents = (string) file_get_contents(__DIR__ . '/../commands/' . $name . '.md');
            self::assertStringContainsString('worky-workflow', $contents, "manca in $name");
        }
    }
}
```

- [ ] **Step 2: Eseguire i test e verificare che falliscano**

Run: `vendor/bin/phpunit --filter CommandsTest`
Expected: FAIL — `commands/feature.md` e `commands/ship.md` non esistono.

- [ ] **Step 3: Scrivere `commands/feature.md`**

```markdown
---
description: Porta una feature dalla richiesta alla pull request con il team worky
argument-hint: <descrizione della feature>
---

## Contesto

- Configurazione del progetto: @.worky.json
- Branch corrente: !`git branch --show-current`
- Stato del working tree: !`git status --short`

## Richiesta

$ARGUMENTS

## Il tuo compito

Invoca la skill `worky-workflow` ed eseguila su questa richiesta.

Punti su cui non transigere:

1. Se `.worky.json` non esiste, fermati e chiedi `/worky:onboard`.
2. Se il working tree non è pulito, chiedi all'utente come procedere prima di
   creare il worktree.
3. Dopo che `worky-analyst` ha scritto la spec, **fermati** e falla approvare.
   È il Gate 1 e non si supera per iniziativa tua.
4. In implementazione dispatcha in parallelo solo i task che il piano dichiara
   indipendenti.
5. Non aprire la pull request se QA o review hanno rilievi bloccanti aperti.
```

- [ ] **Step 4: Scrivere `commands/ship.md`**

```markdown
---
description: Esegue QA, review e apertura della pull request su un branch gia pronto
---

## Contesto

- Configurazione del progetto: @.worky.json
- Branch corrente: !`git branch --show-current`
- Diff rispetto al branch principale: !`git diff --stat $(git merge-base HEAD origin/HEAD 2>/dev/null || echo HEAD~1)`

## Il tuo compito

Invoca la skill `worky-workflow` ed esegui le sole fasi finali: QA, review e
consegna.

1. Individua la spec di riferimento in `docs/specs/`. Se non ne esiste una,
   chiedi all'utente contro cosa vada verificato il lavoro: senza un criterio il
   QA non ha significato.
2. Dispatcha `worky-qa` e poi `worky-reviewer`.
3. Riporta all'utente i rilievi bloccanti prima di procedere.
4. Solo con tutto risolto, pusha il branch e apri la PR con `gh pr create`,
   con una descrizione derivata dalla spec. Il gate pre-PR eseguirà da sé test e
   analisi statica: se blocca, non aggirarlo — risolvi.
```

- [ ] **Step 5: Eseguire i test e verificare che passino**

Run: `composer test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add commands tests/CommandsTest.php
git commit -m "Aggiunge i comandi /worky:feature e /worky:ship"
```

---

### Task 14: Eval del team e verifica end-to-end su un progetto reale

**Files:**
- Create: `evals/onboard/` (generata da `claude plugin eval init --bare onboard`)
- Create: `evals/feature-piccola/` (generata allo stesso modo)
- Modify: `README.md`

**Interfaces:**
- Consumes: tutto il plugin
- Produces: suite di eval eseguibile con `claude plugin eval .`, e la prova che il plugin installato funziona su un progetto Symfony reale

- [ ] **Step 1: Generare lo scheletro del primo eval**

Run: `claude plugin eval init --bare onboard`
Expected: crea un caso vuoto sotto `evals/onboard/`.

Ispeziona i file generati: sono loro a definire il formato esatto del caso e dei
grader. Non inventare campi: compila quelli che lo scheletro fornisce.

- [ ] **Step 2: Compilare il caso `onboard`**

Il prompt del caso chiede di eseguire l'onboarding su un progetto Symfony
minimo. I criteri di valutazione:

- viene invocato il comando `/worky:onboard` (o lo script di rilevamento);
- il risultato presenta all'utente stack, comando dei test e percorsi;
- **non** viene scritto `.worky.json` senza chiedere conferma;
- i campi rilevati come nulli vengono segnalati esplicitamente.

- [ ] **Step 3: Eseguire l'eval**

Run: `claude plugin eval . --case onboard`
Expected: il caso viene eseguito e produce un punteggio. Se fallisce, correggi
`commands/onboard.md` — non il grader — e rilancia.

- [ ] **Step 4: Generare e compilare il secondo eval**

Run: `claude plugin eval init --bare feature-piccola`

Il caso descrive una feature piccola su un progetto Symfony di prova. Criteri:

- l'analyst produce una spec prima di qualunque codice;
- la pipeline si **ferma** al Gate 1 invece di implementare;
- nessun agente dichiara lavoro completo senza output dei test.

Il terzo criterio è il più importante: è la regola che distingue un team
affidabile da uno che riporta successi immaginari.

- [ ] **Step 5: Eseguire tutta la suite**

Run: `claude plugin eval .`
Expected: entrambi i casi eseguiti, punteggi riportati.

- [ ] **Step 6: Installare il plugin e provarlo su un progetto vero**

```bash
git -C /Users/eddy2r/eddy/worky rev-parse --show-toplevel
claude plugin marketplace add /Users/eddy2r/eddy/worky
claude plugin install worky
```

Poi, in una sessione aperta su un progetto Symfony reale: eseguire
`/worky:onboard`, verificare che `.worky.json` prodotto sia corretto, e
controllare che l'hook di lint intervenga davvero introducendo di proposito un
errore di sintassi in un file PHP.

Annotare gli scostamenti trovati: sono il materiale della v0.2.

- [ ] **Step 7: Aggiornare il README con l'esito**

Documentare la procedura di installazione verificata e i risultati degli eval.

- [ ] **Step 8: Commit**

```bash
git add evals README.md
git commit -m "Aggiunge la suite di eval e documenta la verifica end-to-end"
```

---

## Note per chi esegue

- **Ordine.** I task 1→8 sono sequenziali: ognuno usa il precedente. I task 9 e
  10 sono indipendenti fra loro. I task 11 e 12 dipendono da 9 e 10. Il 13
  dipende da 11 e 12. Il 14 chiude.
- **`composer test` deve restare verde** alla fine di ogni task. Un task che
  lascia rosso il repo non è finito.
- **Niente `vendor/autoload.php` negli script di `hooks/` e `scripts/`.** È
  l'errore che si scopre solo a plugin installato, quando è tardi.
