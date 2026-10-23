# LightweightCookieConsent

Soluzione leggera per gestire il consenso ai cookie su WordPress, basata su [CookieConsent v3](https://github.com/orestbida/cookieconsent). Il progetto usa un file PHP per mostrare il banner, raccogliere le preferenze e applicare regole di blocco preventivo ad alcuni script di terze parti.

## Funzionalità

- Banner e pannello preferenze in italiano: accetta tutti, rifiuta i cookie non necessari o personalizza le scelte.
- Tre categorie: **Necessari**, **Statistiche e analisi**, **Marketing e profilazione**.
- Rilevamento euristico di alcuni script esterni caricati tramite WordPress e di alcuni script inline presenti nell'HTML.
- Integrazione con **Google Consent Mode v2**: segnali Google predefiniti su `denied` e aggiornamento dei segnali in base alle preferenze. Con Consent Mode attivo, gli snippet inline riconosciuti come composti esclusivamente da servizi Google non vengono bloccati da questo meccanismo.
- Cancellazione automatica di una lista di cookie noti quando viene negato o revocato il consenso alla categoria corrispondente.
- Pulsante flottante per riaprire le preferenze.
- Caricamento di CookieConsent da file locali, se disponibili, con fallback alla CDN jsDelivr.

## Limiti

Il riconoscimento degli script si basa su hostname, URL e indicatori noti nel codice: **non rileva automaticamente ogni possibile tracker**. Codice personalizzato, iframe, pixel, caricamenti indiretti, script aggiunti dopo il buffering o strumenti non inclusi nelle regole possono sfuggire al rilevamento.

Il blocco riguarda gli script riconosciuti nel filtro degli script WordPress o nell'HTML intercettato dal buffering. Non garantisce, da solo, che nessuna richiesta di tracciamento venga inviata prima del consenso. Cache, minificazione, ordine di caricamento e integrazioni di terze parti possono cambiare il comportamento e vanno testati sul sito reale.

La cancellazione dei cookie è limitata ai cookie eliminabili dal browser nel contesto del sito, con dominio e percorso corretti. JavaScript non può cancellare cookie appartenenti a domini estranei. Per condividere la cancellazione tra sottodomini si può configurare `NVCC_COOKIE_DOMAIN` con il dominio principale appropriato.

Questo progetto è uno strumento tecnico: **non garantisce da solo la conformità al GDPR o ad altre normative**. È necessario verificare i servizi realmente usati dal sito, le finalità del trattamento, l'informativa e il comportamento effettivo delle richieste di rete.

## Installazione

Il file principale è [`CookieConsent.php`](CookieConsent.php).

1. Fai una copia di sicurezza del sito.
2. Inserisci il contenuto del file in uno snippet PHP eseguito sul frontend oppure integralo nel `functions.php` del tema child.
3. Assicurati che il codice venga eseguito una sola volta.
4. Verifica il banner e il pannello preferenze in una finestra anonima; controlla inoltre le richieste di rete prima del consenso, dopo l'accettazione e dopo la revoca.

Il buffering HTML esclude l'area amministrativa WordPress, anteprime e alcune richieste non frontend; interviene solo su documenti HTML completi. Sono presenti attributi di esclusione per alcuni strumenti di ottimizzazione, ma la compatibilità va comunque verificata sul sito reale.

## Configurazione

Le costanti sono definite all'inizio di `CookieConsent.php`:

- `NVCC_VERSION`: versione di CookieConsent v3 per gli asset CDN (attualmente `3.1.0`).
- `NVCC_CONSENT_MODE`: abilita o disabilita Google Consent Mode v2; è attiva per impostazione predefinita.
- `NVCC_CM_REGIONS`: codici regione per il segnale di consenso predefinito; un array vuoto applica il default a tutte le regioni.
- `NVCC_SRI_CSS` e `NVCC_SRI_JS`: valori opzionali di Subresource Integrity per gli asset CDN.
- `NVCC_COOKIE_DOMAIN`: dominio opzionale per la cancellazione dei cookie condivisi, per esempio `.example.com`. Configuralo solo se conosci il dominio corretto.

Le stringhe del banner possono essere personalizzate tramite i filtri WordPress `nvcc_language` e `nvcc_consent_description`.

Gli asset vengono cercati prima in `cookieconsent/` nel tema child e poi nel tema parent; se non vengono trovati, si usa jsDelivr.

## Manutenzione e verifiche

Quando aggiungi un servizio, aggiorna le regole di rilevamento, la categoria, la configurazione del servizio e gli eventuali cookie da cancellare. Dopo ogni modifica controlla almeno:

- rifiuto iniziale dei cookie non necessari;
- accettazione e revoca del consenso;
- script Google, non Google e snippet inline misti;
- comportamento con cache/minificazione attive;
- cookie sul dominio principale e sugli eventuali sottodomini.

## Tecnologie

- WordPress / PHP
- [CookieConsent v3](https://github.com/orestbida/cookieconsent)
- Google Consent Mode v2
