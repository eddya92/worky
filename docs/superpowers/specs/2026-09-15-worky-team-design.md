# worky — Team di agenti per sviluppo Symfony

**Data:** 2026-09-15
**Stato:** design approvato, da implementare

## Obiettivo

Un team di agenti specializzati, distribuito come plugin Claude Code, che prende
una richiesta di feature su un progetto Symfony e la porta fino a una pull
request con test verdi. Il prodotto è il team, non l'applicazione su cui lavora.

Il team è installato una volta e disponibile su tutti i progetti. Ogni progetto
dichiara i propri comandi concreti in un file di adattamento; gli agenti
restano generici.

## Vincoli

- **Runtime:** Claude Code. Nessun servizio, nessuna infrastruttura da mantenere.
- **Stack dei progetti serviti:** PHP 8 / Symfony / Twig / Stimulus / JS vanilla.
- **Gate umani:** due. Approvazione della spec, approvazione della PR.
- **Consegna:** branch pushato + pull request GitHub via `gh`.
- **Riuso:** il processo di sviluppo (brainstorming, piani, TDD, debugging,
  code review) viene da `superpowers` e non va riscritto. worky aggiunge i
  ruoli, l'adattamento per progetto, i gate di qualità e il collante.

## Architettura

### Distribuzione

`worky` è un repo git con un manifest `.claude-plugin/plugin.json`. Si registra
come marketplace (`claude plugin marketplace add <repo>`) e si installa una
volta; da quel momento agenti, skill, comandi e hook sono attivi in ogni
progetto. Gli aggiornamenti passano da `claude plugin marketplace update`.

Le alternative scartate: `~/.claude/` (nessun versioning, non condivisibile,
legato alla macchina) e la copia in ogni progetto (N copie che divergono).

### Struttura del pacchetto

```
worky/
├── .claude-plugin/plugin.json
├── agents/
│   ├── worky-analyst.md
│   ├── worky-backend.md
│   ├── worky-frontend.md
│   ├── worky-qa.md
│   └── worky-reviewer.md
├── skills/
│   ├── symfony-conventions/
│   ├── twig-stimulus/
│   └── worky-workflow/
├── commands/
│   ├── feature.md
│   ├── onboard.md
│   └── ship.md
├── hooks/
│   ├── hooks.json
│   └── scripts/
├── templates/worky.schema.json
└── evals/
```

### Il team

Cinque ruoli, mandati non sovrapposti. Il tech lead non è un agente: è la
sessione principale.

| Ruolo | Mandato | Input | Output |
|---|---|---|---|
| `analyst` | Trasforma una richiesta vaga in spec approvabile. Legge il codice, non lo scrive. | Richiesta o issue | File di spec |
| `backend` | Entity e Doctrine, repository, controller, servizi, form. TDD stretto. | Task del piano | Diff + output PHPUnit |
| `frontend` | Twig, controller Stimulus, JS vanilla, CSS. Test funzionali `WebTestCase`. | Task del piano | Diff + output test |
| `qa` | Verifica indipendente: suite completa, feature contro la **spec**, casi limite scoperti. Non scrive i test di produzione. | Spec + branch | Rapporto di verifica |
| `reviewer` | Review avversariale del diff: correttezza, PHPStan, CS, duplicazione di codice già presente. | Diff | Elenco di rilievi |

`qa` non scrive i test di produzione: li scrive chi implementa, altrimenti il
TDD non è TDD. Il suo valore è l'indipendenza dal piano.

### Adattamento per progetto

`/worky:onboard` ispeziona `composer.json`, `phpunit.xml.dist`, `Makefile`,
`package.json`, `phpstan.neon` e la struttura delle cartelle, poi scrive
`.worky.json` nel progetto:

- comando dei test (e separazione unit / functional, se esiste)
- comando di lint e di analisi statica, con il livello configurato
- versione di Symfony e di PHP
- come si prepara il database di test (fixture, migrazioni)
- percorsi convenzionali: entity, controller, template, assets Stimulus
- comando per avviare l'applicazione in locale

Il comando **mostra all'utente ciò che ha dedotto e chiede conferma** prima di
scrivere. Un comando di test dedotto male in silenzio avvelena ogni task
successivo.

Se `.worky.json` manca, ogni comando worky si ferma e propone l'onboarding:
gli agenti non indovinano mai i comandi di un progetto.

## Flusso di lavoro

1. `/worky:feature "<descrizione>"` (o riferimento a issue).
2. `analyst` esplora e produce la spec in `docs/specs/`. → **Gate 1: utente.**
3. Dalla spec nasce un piano a task con dipendenze esplicite (`writing-plans`).
4. Si apre un git worktree isolato per la feature.
5. Fan-out: i task indipendenti vanno in parallelo a `backend` e `frontend`,
   quelli dipendenti in sequenza. Ogni task in TDD: rosso, verde, refactor.
6. `qa` esegue la suite completa e verifica la feature contro la spec.
7. `reviewer` produce i rilievi; tornano agli sviluppatori. Dopo due giri senza
   convergenza il flusso si ferma e chiama l'utente.
8. Push del branch e apertura della PR con descrizione derivata dalla spec.
   → **Gate 2: utente.**

`/worky:ship` esegue i passi 6-8 su un branch già pronto.

## Qualità e verifica

I controlli sono hook eseguiti dal runtime, non promesse degli agenti.

- **PostToolUse** su scrittura di `*.php`: `php -l` e CS-Fixer sul singolo file.
- **Pre-PR**: la suite di test e PHPStan devono passare; in caso contrario la
  PR non viene aperta.
- **Regola di completamento**: nessun agente dichiara un task completo senza
  allegare l'output del comando di test (`verification-before-completion`).

**Fallimenti.** Un test rosso attiva `systematic-debugging`: ipotesi, verifica,
causa radice. Niente patch tentate a caso. Dopo tre fallimenti sullo stesso
task l'agente si ferma e riporta, invece di accumulare workaround.

**Test del team stesso.** `evals/` contiene casi eseguibili con
`claude plugin eval`: l'onboarding su un progetto Symfony di prova e una
feature piccola end-to-end. Così "il team funziona" è una domanda con risposta
eseguibile.

## Fuori scope per la v1

Agente security, deploy e CI, dashboard, esecuzione schedulata, più progetti in
parallelo. Da valutare dopo che il team ha funzionato su un progetto reale.

## Criteri di successo

1. Il plugin si installa su una macchina pulita e i cinque agenti sono attivi.
2. `/worky:onboard` su un progetto Symfony reale produce un `.worky.json`
   corretto senza correzioni manuali.
3. `/worky:feature` su una feature piccola arriva a una PR con test verdi,
   fermandosi ai due gate previsti.
4. Gli hook bloccano davvero un commit con PHPStan rosso.
5. Gli eval passano.
