=== Payout payment gateway ===
Contributors: Seduco
Tags: payout, gateway, slovakia, payment, payment gateway, platobná brána, payout payment gateway
Requires at least: 5.0.19
Tested up to: 6.4.2
Stable tag: 1.1.2
Donate link: https://payout.one/
Requires PHP: 7.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Platobný modul pre WooCommerce, ktorý spája Váš e-shop s platobnou bránou Payout.

== Description ==
**Rýchla, jednoduchá a variabilná platobná brána**

Ponúkame vám oficiálny a bezplatný plugin na prepojenie platobnej brány Payout k vášmu e-shopu.

## Výhody Payout

Pomôžeme vám zvýšiť efektivitu platobných procesov a zlepšiť zákaznícku skúsenosť. Spracovali sme už viac ako **550 miliónov eur** v **14 krajinách Európy**. Máme viac ako **600 000** spokojných zákazníkov. A s našimi klientmi spoločne rastieme každý mesiac o **10 - 12%**.

Všetko čo ponúkame je jednoduché, finančne dostupné či posilňujúce výkon a efektivitu firiem. Platobná brána Payout je cenovo dostupná pre každého. Základná cena je **0,99% +0,20€** (\*Možnosť individuálnej cenovej ponuky).

## Základné funkcie platobnej brány

### Jednoduchá expanzia na zahraničné trhy

- **18 jazykov:** Angličtina, Buhlarčina, Čeština, Chorvátčina, Estónčina, Gréčtina, Litovčina, Lotyština, Maďarčina, Nemčina, Poľština, Rumunčina, Ruština, Slovenčina, Slovinčina, Španielčina, Taliančina, Ukrajinčina

- **6 mien:** EUR, CZK, PLN, RON, HUF, BGN

### Všetky platobné metódy na jednom mieste

- Platba kartou
- Google Pay a Apple Pay
- QR kód
- Bankové tlačidlá a okamžité prevody
- Buy Now, Pay Later

### Prispôsobenie brány

Naša platobná brána vám umožní prispôsobiť si vzhľad podľa vašich preferencií a potrieb. Môžete si vložiť svoje vlastné logo, aby sa platobná brána stala súčasťou vášho brandingu. Okrem toho si viete prispôsobiť farebný dizajn platobnej brány tak, aby sa dokonale hodili k celkovej vizuálnej identite.

### Rozširujúce funkcie

- Vyžiadanie si platby cez link

### Prehľadná administrácia

Výpisy transakcií sú vo formátoch PDF a XML, čo účtovníkom ušetrí enormné množstvo času pri importe transakcií do účtovných systémov.

### Pre koho je náš plugin určený?

Platobná brána je určená pre každého, kto už prevádzkuje alebo len začína s online obchodom a chce pre svojich zákazníkov zabezpečiť bezpečné a pohodlné platby, zlepšiť užívateľský zážitok a zvýšiť konverziu.

### Prečo si vybrať práve tento plugin?

- Posilníme a **zvýšime výkon a efektivitu Vášho podnikania**.
- **Ponúkame Vám nadštandardnú klientskú podporu** , pretože na názoroch a komunikácii s našimi klientmi nám záleží.
- Naše **technológie** udržujeme neustále **moderné a bezpečné**.
- Tvoríme pre Vás **riešenia na mieru**.
- **Peniaze** , ktoré Vám zákazníci pošlú, máte **rýchlo k dispozícii** a môžete s nimi narábať tak, ako potrebujete.
- Pri nedokončenom nákupe zákazníkovi posielame automatický mail pre opätovné dokončenie platby.

== Installation ==
1. Nahrajte obsah zložky pluginu 'payout-gateway' po rozbalení  do '/wp-content/plugins/' priečinka, alebo nainštalujte plugin priamo cez rozhranie Wordpressu v sekcii Pluginy.
2. Aktivujte plugin.
3. Prejdite na nastavenia platieb WooCommerce a aktivujte platobnú bránu prepínačom.
4. V nastaveniach danej platobnej metódy vyplňte informácie potrebné k spusteniu platobnej brány (informácie potrebné k vyplneniu nájdete vo svojom účte na https://app.payout.one).
5. Vytvorte testovaciu objednávku a skontrolujte funkčnosť.

== Frequently Asked Questions ==
Navštívte https://payout.one/faq/ pre podporu a často kladené otázky.

== Screenshots ==
1. Základné settings
2. Výber platobnej brány
3. Rozhranie platobnej brány

== Changelog ==
= 1.1.2 =
* Add Woo Blocks Checkout compatibility

= 1.1.1 =
* Add HPOS compatibility

= 1.1.0 =
* Major code improvements
* Removed jQuery dependency
* Fixed language and payment method in redirect url
* Fixed typos

= 1.0.15 =
* Partial refunds
* Refunds enhacements
* Payment notification handling, improved compatibility with specific order statuses

= 1.0.14 =
* Debug enhancement, separate debug log for errors
* SSL verify fix
* Added billing_phone to the checkout data
* Function payout_callback() accept only checkout type payment notification

= 1.0.13 =
* Idempotency key
* Ajax-based watcher for payment notification - checking time changed

= 1.0.12 =
* Fixed typo with billing address

= 1.0.11 =
* Prevent creating duplicated checkout - fix

= 1.0.10 =
* Prevent changing status if order is completed

= 1.0.9 =
* Code improvements

= 1.0.8 =
* Order refund support
* Code improvements
* Sending billing, shipping and product data to Payout API

= 1.0.7 =
* Prevent creating duplicated checkout on Payout side
* Added setting field for language parameter

= 1.0.6 =
* Added condition: Possibility to make a payment only if the value of the order is higher than 0.

= 1.0.5 =
* Code improvements

= 1.0.4 =
* Code improvements

= 1.0.3 =
* SK description

= 1.0.2 =
* Language translation fix

= 1.0.1 =
* Code improvements

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.15 =
Pozor zmena v spôsobe refundácie. Refundácia sa nevykoná zmenou stavu ale je potrebné kliknúť na tlačidlo Refundácia pri sumáre objednávky.

= 1.0.0 =
Initial release
