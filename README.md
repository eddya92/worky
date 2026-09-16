# Worky

Plugin Claude Code che fa due cose su un progetto reale: **fa rispettare i
controlli di qualità** con gli hook del runtime, e **ricorda come si fanno le
cose in quel repo**.

Non contiene agenti di ruolo, né una pipeline, né comandi di processo: il
processo di sviluppo lo fornisce già `superpowers` e gli esperti di dominio
esistono già in cataloghi installabili. Worky si ferma dove nessuno dei due
arriva.

## Le due cose che fa

### 1. Enforcement: i controlli li esegue il runtime, non l'agente

- **`hooks/php-lint.php`** (PostToolUse su `Write|Edit`): dopo ogni scrittura
  di un file PHP ne verifica la sintassi con `php -l` e riporta l'errore.
- **`hooks/pre-pr-gate.php`** (PreToolUse su `Bash`): quando un comando sta per
  aprire una pull request, esegue il comando dei test e quello di analisi
  statica dichiarati in `.worky.json`. Se uno dei due fallisce, la pull request
  non si apre.

Un hook che blocca esce con codice 2 e scrive il motivo su stderr, prefissato
`worky: `. Il gate **fallisce chiuso**: se non riesce nemmeno a eseguire i
controlli, blocca la pull request invece di lasciarla passare.

### 2. Memoria: i comandi e le convenzioni di questo progetto

- **`.worky.json`** nella radice del progetto servito dichiara i comandi veri
  di quel repo: `test`, `static_analysis`, `cs`, `fixtures`, `server`, più lo
  `stack` e i `paths` convenzionali. Gli agenti non indovinano mai un comando.
- **`skills/worky-stack-<nome>/`** sono i pacchetti di convenzioni di stack: non
  ripetono le best practice del framework, codificano le regole di casa. Nella
  v1 esiste `symfony-twig-stimulus`, caricabile come skill
  `worky-stack-symfony-twig-stimulus`.
- **`skills/worky-design/`** porta le regole di interfaccia valide su ogni
  progetto: gli stati che mancano sempre, i form, l'accessibilità minima, cosa
  provare prima di dire che una pagina è finita. Nessun colore e nessun
  carattere: l'identità visiva di ogni progetto sta nella sezione `## Aspetto`
  del suo `CLAUDE.md`.
- **`/worky:onboard`** è l'intervista che produce tutto questo.

## Installazione

```bash
claude plugin marketplace add https://github.com/eddy2r/worky
claude plugin install worky
```

## Come si aggiorna

La copia installata vive in cache **sotto la versione**, quindi `claude plugin
update` confronta i numeri di versione, non i file. Senza bump non propaga
niente e la sessione continua a caricare i file vecchi, in silenzio.

Dopo ogni modifica:

1. Alza la versione in `.claude-plugin/plugin.json` **e** in
   `.claude-plugin/marketplace.json`. Un test fallisce se le due divergono.
2. `claude plugin marketplace update worky`
3. `claude plugin update worky`
4. **Riavvia Claude Code**: le sessioni aperte tengono la versione con cui sono
   partite.

Per controllare cosa è davvero installato: `claude plugin details worky` legge
dalla sorgente, non dalla copia. Per vedere la copia vera:

```bash
ls ~/.claude/plugins/cache/worky/worky/
```

## Primo uso su un progetto

Apri il progetto in Claude Code ed esegui:

```
/worky:onboard
```

L'intervista osserva ciò che i file dichiarano senza ambiguità (framework,
versioni, presenza di PHPUnit, PHPStan, CS-Fixer, script Composer, target
`make`, percorsi esistenti) e **chiede tutto il resto**: i comandi che vanno
eseguiti davvero — spesso dentro un wrapper Docker o un target `make` non
standard — e le convenzioni di casa, che non stanno in nessun file.

Non scrive nulla prima della conferma. Alla conferma produce `.worky.json` e,
se ci sono convenzioni da registrare, una sezione `## Convenzioni di progetto`
in `CLAUDE.md`. Committa `.worky.json`: è configurazione del progetto, non un
file personale.

## Cosa esegue il gate, e con quali permessi

Vale la pena saperlo prima di installarlo.

Quando un comando sta per aprire una pull request, il gate esegue i comandi
scritti in `.worky.json` — testualmente, in una shell, nella directory in cui
gira il comando. Due conseguenze:

- **Senza richiesta di permesso.** Gli hook non passano dal sistema dei
  permessi di Claude Code: quello che `.worky.json` dichiara viene eseguito
  senza chiedere conferma.
- **Al semplice tentativo.** Basta che un agente *provi* ad aprire una pull
  request: i comandi partono prima che il comando venga eseguito. Un repo
  appena clonato può quindi eseguire il proprio `.worky.json` la prima volta
  che un agente ci prova.

L'assunzione che rende la cosa accettabile è che **la directory del progetto
sia già considerata fidata per eseguire codice** — che è esattamente ciò che
implicano già `composer test`, un target di `Makefile` o un hook di Git in quel
repo. Se apri in Claude Code un progetto di cui non ti fidi, quel `.worky.json`
è una delle molte cose di cui non fidarti.

## Sviluppo del plugin

```bash
composer test
```

La suite copre, su processi veri con input veri, il comportamento dei due hook,
la lettura e scrittura di `.worky.json`, l'osservazione dei fatti di progetto e
gli script di ingresso.

## Architettura

```
worky/
├── .claude-plugin/plugin.json
├── commands/onboard.md              # l'intervista
├── hooks/
│   ├── hooks.json
│   ├── php-lint.php                 # PostToolUse su Write|Edit
│   └── pre-pr-gate.php              # PreToolUse su Bash
├── scripts/
│   ├── observe.php                  # i fatti osservati, in JSON
│   └── write-config.php             # scrive .worky.json
├── skills/
│   ├── worky-design/                # regole di interfaccia, ovunque
│   └── worky-stack-symfony-twig-stimulus/
├── src/                             # Config, ProjectFacts
└── tests/
```

- **PHP**: 8.2+
- **Testing**: PHPUnit 11
- Gli script eseguiti come hook usano `require_once` espliciti e **mai**
  l'autoloader di Composer: un plugin installato non ha `vendor/`.

## Licenza

Proprietaria
