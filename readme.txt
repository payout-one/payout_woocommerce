=== Payout payment gateway ===
Contributors: Seduco
Tags: payout, gateway, slovakia, payment, payment gateway, platobná brána, payout payment gateway
Requires at least: 4.0
Tested up to: 5.4.1
Stable tag: 1.0.6
Donate link: https://payout.one/sk/
Requires PHP: 7.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Platobný modul pre WooCommerce, ktorý spája Váš e-shop s platobnou bránou Payout.

== Description ==
Príjmajte platby od Vašich zákazníkov bezpečne a pohodlne cez platobnú bránu Payout. Plugin je plne kompatibilný so systémom WooCommerce.

Hlavné črty:
* Jednoduchá inštalácia
* Pravidelné aktualizácie a podpora pluginu
* Plugin podporuje ostrú a testovaciu prevádzku
* Funguje výbobrne so službou Superfaktúra.sk
* Automaticky aktualizuje stavy objednávok vo WooCommerce na základe typu platby
* Schopnosť nastaviť preddefinovaný typ platby po presmerovaní na platobnú bránu
* Plugin je momentálne lokalizovaný do SK a EN jazyka

== Installation ==
1. Nahrajte obsah zložky pluginu 'payout-gateway' po rozbalení  do '/wp-content/plugins/' priečinka, alebo nainštalujte plugin priamo cez rozhranie Wordpressu v sekcii Pluginy.
2. Aktivujte plugin
3. Prejdite na nastavenia platieb WooCommerce a aktivujte platobnú bránu prepínačom
4. V nastaveniach danej platobnej metódy vyplňte informácie potrebné k spusteniu platobnej brány (informácie potrebné k vyplneniu nájdete vo svojom účte na https://app.payout.one)
5. Vytvorte testovaciu objednávku a skontrolujte funkčnosť.

== Frequently Asked Questions ==
Navštívte https://payout.one/sk/faq.html pre podporu a časté kladené otázky.

== Screenshots ==
1. Základné settings
2. Výber platobnej brány
3. Rozhranie platobnej brány

== Changelog ==
= 1.0.7 =
* Prevent creating duplicate checkout on Payout side
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
= 1.0.0 =
Initial release