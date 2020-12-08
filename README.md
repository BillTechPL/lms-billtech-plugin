# Wtyczka BillTech payments dla LMS

## Opis
Wtyczka dodaje integrację z platformą BillTech:
* Dodaje przycisk *Opłać teraz* do panelu klienta w sekcji finanse przy saldzie oraz indywidualnych 
fakturach pozwalając na wykonanie płatności on-line poprzez platformę BillTech,
* Pozwala na dodanie przycisku *Opłać teraz* do emaili z fakturą oraz notyfikacji,
* Wstrzykuje informacje o płatności do nagłówków wiadomości email z fakturą,
* Dodaje przycisk *Opłać teraz* do ekranu blokady internetu,
* Informacja o płatności wykonanej na platformie BillTech trafia do LMS.

## Uwaga
Wtyczka do działania wymaga aktualizacji odpowiedniej wersji LMS. W przypadku posiadania najnowszej wersji
lmsgit nie jest konieczne dodatkowe działanie. W przeciwnym wypadku zapraszamy do kontaktu, chętnie pomożemy 
z wprowadzeniem odpowiednich zmian również do innych wersji LMS.

## Instalacja
* Umieść zawartość tego repozytorium w katalogu *plugins/BillTech* w katalogu instalacyjnym LMSa,
* Przejdź do katalogu *plugins/BillTech*,
* Uruchom skrypt `install.sh`,
* Zaloguj się do panelu admina LMS,
* Przejdź do zakładki *Konfiguracja -> Wtyczki*,
* Kliknij żarówkę po prawej stronie w wierszu z wtyczką BillTech aby ją włączyć,
* W szablonie wiadomości email z powiadomieniem o wystawieniu nowej faktury dodaj `%billtech_btn`,
w miejscu, w którym chcesz umieścić przycisk.

## Konfiguracja
* Skrypt *install.sh* generuje parę kluczy (*lms.pem* oraz *lms.pub*). Wyślij TYLKO plik *lms.pub*
e-mailem do BillTech <admin@billtech.pl>
* W panelu admina wejdź w zakładkę *Konfiguracja -> BillTech* i wpisz wartości otrzymane od <admin@billtech.pl>	


###
Jeżeli chciałbyś przetestować wtyczkę, zobaczyć jak wygląda proces płatności, rozpocząć współpracę lub dowiedzieć się więcej prosimy o wiadomość na <kontakt@billtech.pl>
