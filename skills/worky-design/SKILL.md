---
name: worky-design
description: Use when building or changing any user interface - pages, screens, forms, lists, components - to apply the house UX and UI rules that get skipped most often. Covers states, feedback, forms, destructive actions, accessibility and responsive behaviour. Visual identity (colours, typography) belongs to each project, not here.
---

# Regole di interfaccia

Questo pacchetto non spiega cos'è una buona gerarchia visiva: quella la sai già.
Contiene le cose che **vengono saltate sistematicamente** quando si costruisce
un'interfaccia in fretta, e che si notano solo quando è tardi.

Sono regole verificabili guardando una pagina, non opinioni di gusto. Se una
non si applica al caso, dillo e vai avanti: una regola disattesa
consapevolmente è una decisione, una dimenticata è un difetto.

**L'identità visiva non sta qui.** Colori, caratteri, tono e componenti
ricorrenti sono di ogni progetto e stanno nella sezione `## Aspetto` del suo
`CLAUDE.md`. Leggila prima di scrivere interfaccia; se manca, chiedi invece di
inventare uno stile nuovo.

## Gli stati che mancano sempre

Ogni cosa che mostra dati ha **quattro** stati, non uno. Progettali tutti:

- **Pieno**, il caso che tutti disegnano.
- **Vuoto**: dice cosa fare, non "nessun risultato". Un elenco vuoto al primo
  accesso e uno svuotato da un filtro sono due messaggi diversi.
- **In caricamento**: non deve far saltare il contenuto quando arriva. Riserva
  lo spazio che occuperà.
- **In errore**: dice cosa è andato storto e cosa può fare l'utente adesso.
  "Si è verificato un errore" non è nessuna delle due cose.

## Feedback

- Ogni azione ha una conseguenza visibile entro un istante. Un bottone che non
  reagisce viene premuto due volte.
- Un'operazione che può fallire deve dire se è riuscita. Il silenzio viene
  letto come successo, e a volte non lo è.
- Un'azione **distruttiva** chiede conferma nominando cosa sta per sparire, e
  quando possibile lascia una via d'uscita invece della sola conferma.

## Form

- L'etichetta c'è sempre. Il segnaposto non è un'etichetta: sparisce proprio
  quando serve, cioè mentre si scrive.
- L'errore sta **accanto al campo** che lo ha causato, non solo in cima.
- Dopo un invio fallito i dati inseriti restano dove sono.
- Si dice cosa è obbligatorio prima di premere invio, non dopo.
- Un campo con un formato preciso lo dichiara con un esempio, non con una
  regola scritta a parole.

## Accessibilità, cioè il minimo

- **Niente comunica solo col colore.** Un errore rosso senza testo non esiste
  per chi non distingue i rossi.
- Ogni elemento interattivo ha uno **stato di focus visibile**. È la prima cosa
  che si toglie per estetica e non si rimette più: senza, la tastiera diventa
  inutilizzabile.
- Si arriva a ogni comando con la tastiera, nell'ordine in cui la pagina si
  legge.
- Le immagini che significano qualcosa hanno un'alternativa testuale; quelle
  decorative non la hanno.
- I bersagli toccabili non scendono sotto i 44 pixel di lato.

## Resistenza al contenuto vero

I dati di prova sono sempre gentili; quelli veri no. Prima di dire che una
pagina è finita, provala con:

- un testo lungo dove ne prevedevi uno corto — un nome di sessanta caratteri,
  un titolo che va a capo tre volte;
- un numero grande, con i separatori delle migliaia;
- un elenco vuoto e uno con trecento righe;
- un valore assente: cosa si vede dove non c'è niente?

## Larghezza

- Niente si rompe sotto i 360 pixel di larghezza.
- Il contenuto non scorre orizzontalmente. Le eccezioni sono tabelle, diagrammi
  e blocchi di codice, e scorrono dentro il proprio contenitore, non insieme
  alla pagina.
- Quello che era una riga su schermo largo diventa una colonna su schermo
  stretto, non una riga schiacciata.

## Prima di aggiungere

- Cerca se il componente esiste già nel progetto. Un secondo modo di fare la
  stessa cosa costa più di quanto sembri: va mantenuto, e le due copie
  divergono.
- Non introdurre una dipendenza nuova per un problema che il progetto risolve
  già, e in nessun caso senza chiederlo.
