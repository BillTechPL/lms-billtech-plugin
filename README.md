# Wtyczka BillTech payments dla LMS

## Opis
Wtyczka dodaje integrację z platformą BillTech. 
* Dodaje przycisk *Opłać teraz* do panelu klienta w sekcji finanse przy saldzie oraz indywidualnych 
fakturach pozwalając na wykonanie płatności on-line poprzez platformę BillTech.
* Wstrzykuje informacje o płatności do nagłówków wiadomości email z fakturą.

## Instalacja
* Umieść zawartość tego repozytorium w katalogu *plugins/BillTech* w katalogu instalacyjnym LMSa.
* Przejdź do katalogu *plugins/Billtech*
* Wpisz komendę `chmod 700 install.sh && install.sh`,
* Zaloguj się do panelu admina LMS
* Przejdź do zakładki *Konfiguracja -> Wtyczki*
* Kliknij żarówkę po prawej stronie w wierszu z wtyczką BillTech aby ją włączyć
* W szablonie wiadomości email z powiadomieniem o wystawieniu nowej faktury dodaj `%billtech_btn`
w miejscu, w którym chcesz umieścić przycisk

## Konfiguracja
* Skrypt *install.sh* generuje parę kluczy (*lms.pem* oraz *lms.pub*). Wyślij TYLKO plik *lms.pub*
e-mailem do BillTech <michal@billtech.pl>
* W panelu admina wejdź w zakładkę *Konfiguracja -> Interfejs użytkownika* i dodaj nowe wpisy:
	* `billtech.private_key` = <zawartość pliku *lms.pem*>
	* `billtech.isp_id` = <*isp_id* otrzymane od BillTech>
	* `billtech.payment_url` <*payment_url* otrzymany od BillTech>