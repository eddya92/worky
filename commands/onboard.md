---
description: Intervista guidata che configura worky su questo progetto e scrive .worky.json
allowed-tools: Bash(php:*), Bash(ls:*), Bash(cat:*), Bash(composer:*), Bash(make:*), Bash(vendor/bin/phpunit:*), Read, Glob, Grep, Write, Edit, AskUserQuestion
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

Guarda `composer_json` e **dillo ad alta voce quando non vale `ok`**:

- `assente`: qui non c'è un `composer.json`. Nessun fatto viene dai pacchetti,
  quindi dovrai chiedere tutto.
- `illeggibile`: il `composer.json` c'è ma non si lascia leggere (JSON rotto,
  oppure non è un oggetto). I fatti mancanti non dicono che il progetto sia
  vuoto: dicono che non abbiamo potuto guardare. Segnalalo all'utente, invita a
  correggere il file, e in questa intervista chiedi *di più*, non di meno.

Un file illeggibile scambiato per assenza di fatti è il caso peggiore: porta a
non chiedere nulla proprio dove servirebbe chiedere tutto.

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

Se il progetto ha un'interfaccia, fai **una seconda domanda** sul suo aspetto:
colori, caratteri, componenti ricorrenti, tono delle scritte. Le regole
generali di interfaccia le porta già la skill `worky-design`, valida su ogni
progetto; qui serve solo ciò che distingue *questo*. Se esiste già un foglio di
stile, delle variabili CSS o un design system, chiedi dove sono invece di farti
elencare i valori a voce: li leggerai tu.

Accetta anche qui "non c'è niente di deciso". In quel caso dillo esplicitamente
nel riepilogo: senza un aspetto dichiarato ogni pagina nuova rischia di
somigliare a sé stessa e a nient'altro.

### 5. Scrivi, solo dopo conferma

Presenta il riepilogo completo e chiedi conferma con AskUserQuestion. **Non
scrivere nulla prima.**

Alla conferma **non scrivere `.worky.json` con Write**: passalo allo script del
plugin, che è lo stesso codice che il gate userà per rileggerlo. I nomi delle
chiavi devono venire da lì, non da questa prosa.

```bash
php "${CLAUDE_PLUGIN_ROOT}/scripts/write-config.php" "$PWD" <<'JSON'
{
  "stack": "symfony-twig-stimulus",
  "test": "make test",
  "test_functional": null,
  "static_analysis": "vendor/bin/phpstan analyse",
  "cs": null,
  "fixtures": null,
  "server": null,
  "paths": {}
}
JSON
```

I campi sono quelli decisi nell'intervista: `stack`, `test`, `test_functional`,
`static_analysis`, `cs`, `fixtures`, `server`, `paths`. `schema_version` lo
impone lo script: non passarlo e non discuterlo.

Segnala esplicitamente ogni campo rimasto vuoto con la sua conseguenza: senza
`test`, il gate che precede la pull request non può proteggere niente.

Poi scrivi la sezione `## Convenzioni di progetto` dentro `CLAUDE.md`,
creandolo se manca, con due cose:

1. Le convenzioni specifiche che l'utente ha dato, se ne ha date.
2. **Il pacchetto di convenzioni da caricare**, se `stack` non è nullo. Il nome
   della skill è `worky-stack-` seguito dal nome del pacchetto, cioè dal valore
   di `stack`: per `stack: "symfony-twig-stimulus"` la skill si chiama
   `worky-stack-symfony-twig-stimulus`. Scrivilo come un'istruzione, non come
   una nota:

   > Per lavorare su questo progetto carica la skill
   > `worky-stack-symfony-twig-stimulus`: contiene le convenzioni di questo
   > stack.

   Senza questa riga il pacchetto resta un file che nessuno apre: `.worky.json`
   dichiara lo stack, ma niente lo carica da solo.

Se il progetto ha un'interfaccia, scrivi anche una sezione `## Aspetto` nello
stesso `CLAUDE.md`, con ciò che l'utente ha dichiarato: colori, caratteri,
componenti ricorrenti, tono. Se ha indicato un foglio di stile o delle
variabili invece di elencare i valori, cita il percorso del file: la fonte è
quella, e resta aggiornata da sola.

Chiudi la sezione con l'istruzione che vale su ogni progetto:

> Per costruire o modificare interfaccia in questo progetto carica la skill
> `worky-design`, e rispetta quanto scritto qui sopra dove le due cose si
> sovrappongono.

Se l'utente non ha dichiarato nulla sull'aspetto, scrivi lo stesso la sezione
con una riga che dice che non è stato deciso niente: un vuoto dichiarato è una
domanda aperta, un vuoto silenzioso è una dimenticanza.

### 6. Chiudi

Suggerisci di committare `.worky.json`: è configurazione del progetto, non un
file personale.
