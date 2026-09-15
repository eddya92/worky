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
