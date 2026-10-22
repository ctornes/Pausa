# Pausa

Prenotazione delle pause giornaliere: slot da 10 minuti, 2 persone per slot.
PHP 8.2+ con estensione `pdo_sqlite`, nessuna dipendenza.

## Avvio in locale

```bash
php -S localhost:8000 -t public
```

Apri http://localhost:8000: al primo avvio crei l'amministratore, poi da **Persone** aggiungi i colleghi con il loro PIN.

## Come funziona

- Chi non è ancora nell'elenco si registra da solo con nome, cognome e PIN (mai come amministratore). La richiesta compare in **Persone → Da attivare**: finché un admin non la attiva, la persona non può accedere. Gli admin possono chiudere la registrazione da **Impostazioni**.
- Alla registrazione (o dopo, da **Il mio profilo**) si può aggiungere una foto: scattata con la fotocamera del telefono o con la webcam, oppure caricata dal dispositivo. Viene ritagliata quadrata, ridotta a 400×400 e si vede solo dopo l'accesso. Gli admin possono rimuoverla da **Persone**.
- Ognuno entra scegliendo il proprio nome e digitando il PIN di 4 cifre (5 tentativi sbagliati = blocco di 5 minuti).
- Nella pagina **Oggi** si prenota uno slot libero o ci si unisce a chi è già prenotato. Ognuno può avere una sola pausa per fascia oraria (es. una al mattino e una al pomeriggio); scegliere un altro slot nella stessa fascia sposta la prenotazione.
- Si può annullare o spostare la pausa fino all'inizio dello slot. La pagina si aggiorna da sola ogni 30 secondi.
- Gli admin possono assegnare **pause fisse**: la stessa persona nello stesso slot nei giorni scelti, prenotata in automatico ogni giorno. La persona non può annullarla né spostarla; per saltare un giorno l'admin la toglie dallo Storico.
- Gli admin gestiscono persone, orari (fasce, durata, posti per slot, pause al giorno) e vedono lo storico.

## Struttura

| Percorso | Contenuto |
|---|---|
| `public/` | unica cartella esposta dal web server: pagine, CSS, JS |
| `src/` | logica: slot, prenotazioni, utenti, login |
| `views/` | template HTML |
| `schema.sql`, `migrations/` | struttura del database e sue modifiche successive (applicate in automatico) |
| `data/` | database SQLite, foto profilo e sessioni — **fuori da `public/`**, da includere nei backup |

## Messa online

Punta la document root del sito su `public/` e assicurati che `data/` sia scrivibile da PHP.
Usa **HTTPS**: i browser aprono l'anteprima live della fotocamera solo su pagine sicure (o su `localhost`). Su http il pulsante "Scatta una foto" apre comunque l'app fotocamera del telefono, ma sul computer la webcam non è disponibile.
Se l'app sta in una sottocartella (es. `/pausa`), imposta `base_url` in `config.php`.
Per aggiungere una persona da terminale: `php bin/create-user.php "Nome Cognome" [--admin]`.
