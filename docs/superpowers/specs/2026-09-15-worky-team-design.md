# worky — Enforcement e conoscenza di progetto per lo sviluppo agentico

**Data:** 2026-09-15 (revisione 2)
**Stato:** design approvato, in implementazione

## Obiettivo

worky è un plugin Claude Code che rende affidabile lo sviluppo assistito su
progetti reali, aggiungendo i due pezzi che oggi mancano:

1. **Regole che il runtime fa rispettare.** Hook che eseguono i comandi di
   qualità del progetto e bloccano ciò che non passa. Un agente non può
   dichiarare che i test passano se non passano.
2. **Memoria delle convenzioni.** Un file per progetto (`.worky.json`) con i
   comandi veri di quel repo, più pacchetti di convenzioni per stack che
   codificano le regole di casa.

A questi si aggiunge un **onboarding a intervista** che raccoglie entrambe le
cose su un progetto nuovo.

## Cosa worky NON fa, e perché

worky **non** contiene agenti, né una pipeline, né gate di processo.

Il processo di sviluppo — brainstorming, spec, piani, TDD, debugging
sistematico, code review, esecuzione via subagent con registro — esiste già in
`superpowers` ed è mantenuto da altri. Ricostruirlo significherebbe mantenere
due copie divergenti dello stesso workflow, e la seconda copia sarebbe la
peggiore.

Gli esperti di dominio esistono già anch'essi: cataloghi come
`wshobson/agents` ne pubblicano centinaia, installabili con un comando e
compatibili con worky.

Ciò che nessuno dei due fa, verificato per ispezione: eseguire *i comandi del
tuo progetto* e impedire che si prosegua col rosso, e ricordare come si fanno
le cose *in quel repo*. È lì che worky si ferma, ed è tutto ciò che fa.

## Vincoli

- **Runtime:** Claude Code. Nessun servizio, nessuna infrastruttura.
- **Stack dei progetti serviti:** architettura multi-stack; pacchetto
  implementato nella v1: PHP 8 / Symfony / Twig / Stimulus / JS vanilla.
- **Nessuna dipendenza runtime.** Gli script eseguiti come hook usano
  `require_once` espliciti, mai l'autoloader di Composer: un plugin installato
  non ha `vendor/`.
- **Riuso:** il processo viene da `superpowers` e viene richiamato per nome,
  non riscritto.

## Architettura

### Distribuzione

`worky` è un repo git con manifest `.claude-plugin/plugin.json`. Si registra
come marketplace (`claude plugin marketplace add <repo>`) e si installa una
volta; da quel momento hook, skill e comandi sono attivi in ogni progetto.
Gli aggiornamenti passano da `claude plugin marketplace update`.

Le alternative scartate: `~/.claude/` (nessun versioning, legato alla
macchina) e la copia in ogni progetto (N copie che divergono).

### Struttura del pacchetto

```
worky/
├── .claude-plugin/plugin.json
├── src/                             # Config: lettura e scrittura di .worky.json
├── hooks/
│   ├── hooks.json
│   ├── php-lint.php                 # PostToolUse su Write|Edit
│   └── pre-pr-gate.php              # PreToolUse su Bash
├── commands/onboard.md              # l'intervista
├── skills/stacks/
│   └── symfony-twig-stimulus/       # unico pacchetto della v1
└── tests/
```

### Pacchetti di stack

Un pacchetto è una skill in `skills/stacks/<nome>/` che risponde a tre domande
per il suo stack: quali convenzioni di struttura seguire, come si scrive un
test, quali errori tipici evitare.

Il contenuto **non** ripete le best practice generiche del framework: quelle il
modello le conosce. Un pacchetto codifica le regole di casa e le decisioni non
deducibili dalla documentazione ufficiale (organizzazione dei servizi, uso di
DTO o form, cosa può parlare con Doctrine, livello di PHPStan preteso,
convenzioni di naming dei controller Stimulus).

Aggiungere un pacchetto (`laravel-blade`, `react`) deve richiedere solo una
cartella nuova: nessuna modifica agli hook o al resto del plugin.

### Adattamento per progetto

`.worky.json` nella radice del progetto servito dichiara:

- lo **stack**, e quindi il pacchetto di convenzioni da caricare
- il **comando dei test** (e la testsuite funzionale, se separata)
- il comando di **analisi statica** e quello di **stile**
- come si **prepara il database** di test
- come si **avvia** l'applicazione in locale
- i **percorsi convenzionali**: entity, controller, template, assets

Se il file manca, il gate si ferma con un messaggio che indica l'onboarding.
Gli agenti non indovinano mai i comandi di un progetto.

## Onboarding

`/worky:onboard` è un'**intervista**, non un rilevamento silenzioso.

La ripartizione fra macchina e domande segue un criterio solo: ciò che un file
dichiara senza ambiguità viene letto; tutto il resto viene chiesto.

- **Letto dai file:** stack e framework, versioni di PHP e del framework,
  presenza di PHPUnit, PHPStan, CS-Fixer, percorsi convenzionali esistenti.
- **Chiesto all'utente:** i comandi che vanno eseguiti davvero (spesso dentro
  un wrapper Docker o un target `make` non standard), come si preparano le
  fixture, e soprattutto le convenzioni di casa di *quel* progetto — che non
  stanno in nessun file e che nessun rilevamento potrà mai dedurre.

Le domande seguono la disciplina di `superpowers:brainstorming`: una per volta,
a scelta multipla dove possibile, mai un muro di domande.

L'intervista **non scrive nulla prima della conferma**, e segnala
esplicitamente ogni campo rimasto vuoto con la conseguenza che comporta: senza
comando dei test, il gate non può proteggere niente.

L'output è `.worky.json` più, quando l'utente fornisce convenzioni specifiche
del progetto, una sezione in `CLAUDE.md` che le registra.

## Qualità e verifica

I controlli sono hook eseguiti dal runtime, non promesse degli agenti.

- **PostToolUse** su scrittura di un `.php`: `php -l` sul singolo file.
- **PreToolUse** su Bash, quando il comando apre una pull request: la suite di
  test e l'analisi statica dichiarate in `.worky.json` devono passare, o la PR
  non viene aperta.
- Ogni hook che blocca esce con **codice 2** e scrive il motivo su **stderr**,
  prefissato `worky: `.

Il plugin verifica se stesso con PHPUnit (`composer test`): il rilevamento,
la configurazione e il comportamento dei due hook sono coperti da test veri,
eseguiti su processi reali con input reali.

## Fuori scope per la v1

Agenti di ruolo, pipeline e comandi di processo (li fornisce `superpowers`).
Agente security, deploy e CI, dashboard di visualizzazione, esecuzione
schedulata. Pacchetti di stack oltre a `symfony-twig-stimulus`: la struttura
che li accoglie è nella v1, il loro contenuto no.

## Criteri di successo

1. Il plugin si installa su una macchina pulita e gli hook risultano attivi.
2. `/worky:onboard` su un progetto Symfony reale produce un `.worky.json`
   corretto, avendo chiesto ciò che non poteva leggere.
3. L'hook di lint blocca davvero la scrittura di un PHP con errore di sintassi.
4. Il gate impedisce davvero l'apertura di una PR con i test rossi.
5. `composer test` è verde.
6. Un pacchetto di stack nuovo si aggiunge creando una cartella, senza
   modificare hook o codice.
