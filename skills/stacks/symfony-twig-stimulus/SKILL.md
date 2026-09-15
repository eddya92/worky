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
