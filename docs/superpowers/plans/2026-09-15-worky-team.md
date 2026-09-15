# worky — Piano di implementazione

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Costruire `worky`, un plugin Claude Code che aggiunge allo sviluppo agentico i due pezzi che oggi mancano: hook che fanno rispettare i comandi di qualità del progetto, e memoria delle convenzioni di quel progetto raccolta con un'intervista di onboarding.

**Architecture:** Il plugin è un repo git con manifest `.claude-plugin/plugin.json`. La logica deterministica — lettura della configurazione, osservazione del progetto, gate di qualità — è codice PHP testato con PHPUnit nel repo stesso. La conoscenza di framework sta in pacchetti sostituibili sotto `skills/stacks/`. Il processo di sviluppo non viene riscritto: arriva da `superpowers` e viene richiamato per nome.

**Tech Stack:** PHP 8.2+, PHPUnit 11, Composer, git. Nessuna dipendenza runtime: gli script eseguiti come hook usano `require_once` espliciti e non l'autoloader di Composer, perché un plugin installato non ha `vendor/`.

**Spec:** `docs/superpowers/specs/2026-09-15-worky-team-design.md`

**Revisione 2:** questo piano nasceva con quattordici task e comprendeva cinque agenti di ruolo, una skill di pipeline e i comandi di processo. Sono stati rimossi: `superpowers` li fornisce già, e mantenerne una seconda copia divergente sarebbe stato il difetto più costoso del progetto. Il rilevamento automatico in due passate è stato sostituito da un'osservazione sottile più un'intervista, perché i comandi veri di un progetto — un wrapper Docker, un target `make` non standard — non stanno in nessun file e nessun rilevamento può dedurli.

## Global Constraints

- Nome del plugin: `worky`. Namespace PHP: `Worky\`.
- Versione PHP minima: `8.2`. Nessuna dipendenza runtime oltre alla standard library.
- Gli script in `hooks/` e `scripts/` NON possono usare `vendor/autoload.php`: usano `require_once` relativi a `__DIR__`.
- Ogni hook che blocca esce con **codice 2** e scrive il motivo su **stderr**, prefissato `worky: `.
- I file `.worky.json` hanno sempre `schema_version: 1`.
- Il codice osserva fatti verificabili; le decisioni le prende l'utente. Nessun comando di progetto viene dedotto in silenzio.
- Aggiungere un pacchetto di stack deve richiedere solo una cartella in `skills/stacks/` e una riga nelle costanti di `ProjectFacts`. Nessuna modifica agli hook.
- Tutti i testi rivolti all'utente (descrizioni, messaggi di errore, prompt) sono in italiano. Fa eccezione il campo `description:` nel frontmatter delle skill, che è testo di matching per la selezione automatica e resta in inglese.
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

---

### Task 2: Lettura e scrittura di `.worky.json`

**Files:**
- Create: `src/Config.php`
- Create: `src/MissingConfigException.php`
- Test: `tests/ConfigTest.php`

**Interfaces:**
- Consumes: niente — il contenuto di `.worky.json` lo produce l'intervista di onboarding (Task 7)
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
Expected: PASS (4 test).

- [ ] **Step 5: Commit**

```bash
git add src/Config.php src/MissingConfigException.php tests/ConfigTest.php
git commit -m "Aggiunge lettura e scrittura di .worky.json"
```

---

---

### Task 3: Hook di lint PHP dopo ogni scrittura

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

---

### Task 4: Gate di qualità prima della pull request

**Files:**
- Create: `hooks/pre-pr-gate.php`
- Test: `tests/Hook/PrePrGateHookTest.php`

**Interfaces:**
- Consumes: `Worky\Config` (Task 2), incluso con `require_once __DIR__ . '/../src/Config.php'`
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

---

### Task 5: Registrazione degli hook nel plugin

**Files:**
- Create: `hooks/hooks.json`
- Test: `tests/HooksRegistrationTest.php`

**Interfaces:**
- Consumes: `hooks/php-lint.php` (Task 3), `hooks/pre-pr-gate.php` (Task 4)
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
Expected: PASS (tutti i test, 16 in totale).

- [ ] **Step 5: Commit**

```bash
git add hooks/hooks.json tests/HooksRegistrationTest.php
git commit -m "Registra gli hook di lint e del gate pre-PR"
```

---

---

### Task 6: Pacchetto di stack `symfony-twig-stimulus`

**Files:**
- Create: `skills/stacks/symfony-twig-stimulus/SKILL.md`
- Test: `tests/SkillsFrontmatterTest.php`

**Interfaces:**
- Consumes: il campo `stack` di `.worky.json` (Task 2), valorizzato dall'intervista di onboarding (Task 7)
- Produces: skill `worky-stack-symfony-twig-stimulus`, caricata quando `.worky.json` la indica

- [ ] **Step 1: Scrivere il test che fallisce**

Creare `tests/SkillsFrontmatterTest.php`:

```php
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

    public function testIlPacchettoContieneSoloConvenzioni(): void
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

---

### Task 7: Onboarding a intervista

**Files:**
- Create: `src/ProjectFacts.php`
- Create: `scripts/observe.php`
- Create: `commands/onboard.md`
- Test: `tests/ProjectFactsTest.php`
- Create: `tests/fixtures/symfony-full/` (`composer.json`, `phpunit.xml.dist`, `phpstan.neon`, `.php-cs-fixer.dist.php`, `Makefile`, `public/index.php`, `src/Entity/.gitkeep`, `src/Controller/.gitkeep`, `templates/.gitkeep`, `assets/controllers/.gitkeep`)
- Create: `tests/fixtures/laravel-basic/composer.json`
- Create: `tests/fixtures/vuoto/.gitkeep`

**Interfaces:**
- Consumes: `Worky\Config` (Task 2), il pacchetto `symfony-twig-stimulus` (Task 6)
- Produces: `Worky\ProjectFacts::__construct(string $projectDir)` e `observe(): array` con le chiavi `framework` (string|null), `stack` (string|null), `php` (string|null), `framework_version` (string|null), `tools` (array<string,bool>), `paths` (array<string,string|null>), `composer_scripts` (list<string>), `make_targets` (list<string>). Inoltre `scripts/observe.php [dir]`, che stampa quei fatti in JSON, e il comando `/worky:onboard`.

**Principio che governa questo task:** la classe osserva **fatti verificabili** e non deduce comandi. Che in `composer.json` esista uno script `test` è un fatto; che il comando dei test di questo progetto sia `composer test` è una decisione, e le decisioni le prende l'utente nell'intervista. Un comando dedotto male in silenzio avvelena ogni uso successivo del gate.

- [ ] **Step 1: Creare le fixture**

`tests/fixtures/symfony-full/composer.json`:

```json
{
    "require": {
        "php": ">=8.3",
        "symfony/framework-bundle": "7.2.*",
        "symfony/twig-bundle": "7.2.*"
    },
    "require-dev": {
        "doctrine/doctrine-fixtures-bundle": "^3.6",
        "phpstan/phpstan": "^2.0",
        "phpunit/phpunit": "^11.0"
    },
    "scripts": {
        "test": "phpunit",
        "lint": "php-cs-fixer fix"
    }
}
```

`tests/fixtures/symfony-full/Makefile`:

```
test:
	docker compose exec php vendor/bin/phpunit

fixtures:
	docker compose exec php bin/console doctrine:fixtures:load -n
```

Attenzione: le righe di comando di un Makefile devono iniziare con un TAB, non con spazi.

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

`tests/fixtures/symfony-full/phpstan.neon`:

```
parameters:
    level: 8
    paths:
        - src
```

`tests/fixtures/symfony-full/.php-cs-fixer.dist.php`: contenuto `<?php return [];`

`tests/fixtures/symfony-full/public/index.php`: contenuto `<?php`

Creare inoltre i file vuoti `src/Entity/.gitkeep`, `src/Controller/.gitkeep`, `templates/.gitkeep`, `assets/controllers/.gitkeep` sotto la stessa fixture.

`tests/fixtures/laravel-basic/composer.json`:

```json
{
    "require": {
        "php": ">=8.2",
        "laravel/framework": "^11.0"
    }
}
```

`tests/fixtures/vuoto/.gitkeep`: file vuoto.

- [ ] **Step 2: Scrivere i test che falliscono**

`tests/ProjectFactsTest.php`:

```php
<?php

declare(strict_types=1);

namespace Worky\Tests;

use PHPUnit\Framework\TestCase;
use Worky\ProjectFacts;

final class ProjectFactsTest extends TestCase
{
    private function observe(string $fixture): array
    {
        return (new ProjectFacts(__DIR__ . '/fixtures/' . $fixture))->observe();
    }

    public function testOsservaFrameworkStackEVersioni(): void
    {
        $facts = $this->observe('symfony-full');

        self::assertSame('symfony', $facts['framework']);
        self::assertSame('symfony-twig-stimulus', $facts['stack']);
        self::assertSame('8.3', $facts['php']);
        self::assertSame('7.2', $facts['framework_version']);
    }

    public function testOsservaGliStrumentiPresenti(): void
    {
        $tools = $this->observe('symfony-full')['tools'];

        self::assertTrue($tools['phpunit']);
        self::assertTrue($tools['phpstan']);
        self::assertTrue($tools['php_cs_fixer']);
    }

    public function testElencaGliScriptComposerEITargetMake(): void
    {
        $facts = $this->observe('symfony-full');

        self::assertSame(['test', 'lint'], $facts['composer_scripts']);
        self::assertSame(['test', 'fixtures'], $facts['make_targets']);
    }

    public function testMappaSoloIPercorsiCheEsistonoDavvero(): void
    {
        $paths = $this->observe('symfony-full')['paths'];

        self::assertSame('src/Entity', $paths['entity']);
        self::assertSame('templates', $paths['templates']);
    }

    public function testRiconosceIlFrameworkMaLasciaLoStackNulloSenzaPacchetto(): void
    {
        $facts = $this->observe('laravel-basic');

        self::assertSame('laravel', $facts['framework']);
        self::assertNull($facts['stack'], 'Nella v1 non esiste un pacchetto Laravel');
    }

    public function testNonInventaNullaSuUnProgettoVuoto(): void
    {
        $facts = $this->observe('vuoto');

        self::assertNull($facts['framework']);
        self::assertNull($facts['php']);
        self::assertSame([], $facts['composer_scripts']);
        self::assertSame([], $facts['make_targets']);
        self::assertNull($facts['paths']['entity']);
    }
}
```

- [ ] **Step 3: Eseguire i test e verificare che falliscano**

Run: `vendor/bin/phpunit --filter ProjectFactsTest`
Expected: FAIL — `Class "Worky\ProjectFacts" not found`.

- [ ] **Step 4: Scrivere l'implementazione**

`src/ProjectFacts.php`:

```php
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
     * Framework => pacchetto di convenzioni in skills/stacks/.
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

        return [
            'framework' => $framework,
            'stack' => $framework === null ? null : (self::STACK_PACKS[$framework] ?? null),
            'php' => $this->versionFrom($composer['require']['php'] ?? null),
            'framework_version' => $this->frameworkVersion($composer, $framework),
            'tools' => $this->observeTools(),
            'paths' => $this->observePaths(),
            'composer_scripts' => array_keys($composer['scripts'] ?? []),
            'make_targets' => $this->observeMakeTargets(),
        ];
    }

    private function observeFramework(array $composer): ?string
    {
        $require = $composer['require'] ?? [];

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

        return $this->versionFrom($composer['require'][$package] ?? null);
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

Run: `vendor/bin/phpunit --filter ProjectFactsTest`
Expected: PASS (6 test).

- [ ] **Step 6: Scrivere lo script di osservazione**

`scripts/observe.php`:

```php
<?php

declare(strict_types=1);

/**
 * Stampa in JSON i fatti osservabili di un progetto.
 *
 *   php scripts/observe.php [dir]
 *
 * Nessun autoload di Composer: funziona anche da plugin installato.
 */

require_once __DIR__ . '/../src/ProjectFacts.php';

use Worky\ProjectFacts;

$projectDir = rtrim($argv[1] ?? (getenv('CLAUDE_PROJECT_DIR') ?: getcwd()), '/');

if (!is_dir($projectDir)) {
    fwrite(STDERR, sprintf("worky: la directory %s non esiste.\n", $projectDir));
    exit(1);
}

$facts = (new ProjectFacts($projectDir))->observe();

fwrite(STDOUT, json_encode($facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
exit(0);
```

- [ ] **Step 7: Scrivere il comando di onboarding**

`commands/onboard.md`:

```markdown
---
description: Intervista guidata che configura worky su questo progetto e scrive .worky.json
allowed-tools: Bash(php:*), Bash(ls:*), Bash(cat:*), Read, Glob, Grep, Write, Edit, AskUserQuestion
---

## Fatti osservati

!`php "${CLAUDE_PLUGIN_ROOT}/scripts/observe.php"`

## Il tuo compito

Il blocco qui sopra contiene **fatti verificabili**, non decisioni. Il tuo
compito è trasformarli in una configurazione, chiedendo all'utente tutto ciò
che i file non dicono.

Conduci l'intervista con la disciplina di `superpowers:brainstorming`: **una
domanda per volta**, a scelta multipla dove possibile, con la tua
raccomandazione come prima opzione. Mai un muro di domande.

### 1. Presenta ciò che hai osservato

In forma leggibile, non come JSON grezzo: stack riconosciuto, versioni,
strumenti presenti, script e target disponibili, percorsi trovati.

Se `stack` è nullo ma `framework` no, dillo: il framework è riconosciuto ma
non esiste ancora un pacchetto di convenzioni per esso.

### 2. Chiedi i comandi veri, uno alla volta

I fatti mostrano quali strumenti esistono, non come si eseguono in questo
progetto. Proponi le opzioni che hai osservato e lascia scegliere:

- **Comando dei test.** Le opzioni sono gli script composer e i target make
  osservati, più `vendor/bin/phpunit`. Se fra i target make ne vedi uno che
  passa da `docker compose`, mettilo per primo: un progetto con Docker quasi
  sempre esegue i test lì dentro, e un comando che gira fuori dal container
  fallisce in modi confusi.
- **Testsuite funzionale**, se `phpunit.xml` ne dichiara una separata.
- **Analisi statica** e **stile**, solo se i rispettivi strumenti risultano
  presenti.
- **Preparazione del database di test**, se il progetto ha le fixture.
- **Comando per avviare l'applicazione** in locale.

Salta ogni domanda la cui risposta è già certa dai fatti, e non chiedere di
strumenti che non ci sono.

### 3. Verifica prima di credere

Prima di scrivere, esegui il comando dei test che l'utente ha indicato e
mostragli l'esito. Un comando sbagliato scoperto adesso costa dieci secondi;
scoperto dal gate durante una feature, costa una sessione.

### 4. Chiedi le convenzioni di casa

Questa è la parte che nessun rilevamento può dedurre e che vale più di tutto
il resto. Chiedi se in questo progetto valgono regole particolari: cosa può
parlare col database, dove sta la logica applicativa, come si nominano le
cose, cosa è vietato. Poni la domanda una volta, in modo aperto, e accetta
anche "niente di particolare" come risposta.

### 5. Scrivi, solo dopo conferma

Presenta il riepilogo completo e chiedi conferma con AskUserQuestion. **Non
scrivere nulla prima.**

Alla conferma scrivi `.worky.json` nella radice del progetto con
`schema_version: 1` e i campi decisi: `stack`, `test`, `test_functional`,
`static_analysis`, `cs`, `fixtures`, `server`, `paths`.

Segnala esplicitamente ogni campo rimasto vuoto con la sua conseguenza: senza
`test`, il gate che precede la pull request non può proteggere niente.

Se l'utente ha dato convenzioni specifiche del progetto, registrale in una
sezione `## Convenzioni di progetto` dentro `CLAUDE.md`, creandolo se manca.

### 6. Chiudi

Suggerisci di committare `.worky.json`: è configurazione del progetto, non un
file personale.
```

- [ ] **Step 8: Eseguire tutta la suite**

Run: `composer test`
Expected: PASS (24 test), output pulito.

- [ ] **Step 9: Commit**

```bash
git add src/ProjectFacts.php scripts/observe.php commands/onboard.md tests/ProjectFactsTest.php tests/fixtures
git commit -m "Aggiunge l'onboarding a intervista e l'osservazione dei fatti di progetto"
```

---

## Note per chi esegue

- **Ordine.** I task 2→5 sono sequenziali: la configurazione serve al gate, e la
  registrazione serve a entrambi gli hook. Il task 6 è indipendente da tutti. Il
  task 7 chiude e dipende dal 2 (scrive `.worky.json`) e dal 6 (nomina il
  pacchetto di convenzioni).
- **`composer test` deve restare verde** alla fine di ogni task. Un task che
  lascia rosso il repo non è finito.
- **Niente `vendor/autoload.php` negli script di `hooks/` e `scripts/`.** È
  l'errore che si scopre solo a plugin installato, quando è tardi.
- **Il codice non decide.** Dove il piano dice "osserva", intende riportare ciò
  che i file dichiarano. Trasformare un'osservazione in un comando è compito
  dell'intervista, non della classe.

## Verifica finale, da fare con l'utente

Non è un task: richiede un progetto reale e la presenza dell'utente.

1. Registrare e installare il plugin:
   `claude plugin marketplace add <percorso del repo>` poi
   `claude plugin install worky`.
2. In una sessione aperta su un progetto Symfony vero, eseguire `/worky:onboard`
   e controllare che l'intervista chieda ciò che non poteva leggere e che il
   `.worky.json` prodotto sia corretto.
3. Introdurre di proposito un errore di sintassi in un file PHP e verificare che
   l'hook di lint lo blocchi.
4. Con i test rossi, provare ad aprire una pull request e verificare che il gate
   la impedisca.

Gli scostamenti trovati qui sono il materiale della v0.2.
