# Worky

Team di agenti specializzati per lo sviluppo assistito con Claude Code.

Worky mette a tua disposizione cinque agenti esperti: **analyst** (analisi dei requisiti), **backend** (sviluppo server), **frontend** (interfacce utente), **qa** (testing e qualità), e **reviewer** (revisione del codice). Ognuno è specializzato nel suo dominio e può collaborare con gli altri per accelerare lo sviluppo di un progetto.

## Installazione

### Da Marketplace

```bash
claude plugin install worky
```

### Da Repository

```bash
claude plugin marketplace add https://github.com/eddy2r/worky
claude plugin install worky
```

## Onboarding su un Progetto

Una volta installato, il plugin è automaticamente disponibile nel tuo Claude Code. Per iniziare a usarlo su un nuovo progetto:

1. Apri il tuo progetto in Claude Code
2. Il plugin si registra automaticamente
3. Richiedi l'intervento di uno dei team di agenti nella tua sessione (ad es. "analyst, analizza questo requirement")

## Lanciare i Test

Per eseguire i test del plugin e verificare il corretto funzionamento:

```bash
composer test
```

Questo comando esegue la suite di test PHPUnit che valida l'integrità e la corretta configurazione di Worky.

## Architettura

- **PHP**: 8.2+
- **Testing**: PHPUnit 11
- **Autoload PSR-4**: 
  - `Worky\` → `src/`
  - `Worky\Tests\` → `tests/`

## Licenza

Proprietaria
