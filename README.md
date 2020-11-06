# Wtyczka BillTech payments dla LMS

## Opis
Wtyczka umożliwia integrację z usługą BillTech Pay poprzez:
* Dodanie przycisku *Opłać teraz* do panelu klienta w sekcji finanse przy saldzie oraz indywidualnych 
fakturach pozwalając na wykonanie płatności on-line poprzez platformę BillTech,
* Pozwala na dodanie przycisku *Opłać teraz* do emaili z fakturą oraz notyfikacji,
* Wstrzykuje informacje o płatności do nagłówków wiadomości email z fakturą,
* Dodaje przycisk *Opłać teraz* do ekranu blokady internetu,
* Informacja o płatności wykonanej na platformie BillTech trafia do LMS.

BillTech Pay to usługa, która pozwala Dostawcom usług na wygodne pobieranie należności od swoich klientów. 
Po wystawieniu faktury Dostawca generuje link do płatności, który może dostarczyć swoim klientom różnymi kanałami,
 np. wysłać w wiadomości e-mail, sms lub pokazać w panelu online. 
Klient (użytkownik) po kliknięciu w taki link, zostaje przekierowany na ekran podsumowania płatności.
Informacja o wykonanej płatności natychmiast trafia do Dostawcy,
 dzięki czemu możliwe jest szybkie zwiększenia salda klienta oraz ew. zdjęcie blokady usług.

#### Uwaga
Wtyczka do działania wymaga aktualizacji odpowiedniej wersji LMS. W przypadku posiadania najnowszej wersji
lmsgit nie jest konieczne dodatkowe działanie. W przeciwnym wypadku zapraszamy do kontaktu, chętnie pomożemy 
z wprowadzeniem odpowiednich zmian również do innych wersji LMS.

## Instalacja
* Umieść zawartość tego repozytorium w katalogu *plugins/BillTech* w katalogu instalacyjnym LMSa,
* Zaloguj się do panelu admina LMS,
* Przejdź do zakładki *Konfiguracja -> Wtyczki*,
* Kliknij żarówkę po prawej stronie w wierszu z wtyczką BillTech aby ją włączyć,
* W szablonie wiadomości email z powiadomieniem o wystawieniu nowej faktury dodaj `%billtech_btn` i/lub `%billtech_balance_btn`,
w miejscu, w którym powinny pojawić się przyciski do opłacenia odpowiednio indywidualnej faktury i/lub salda. 

## Konfiguracja
W panelu admina wejdź w zakładkę *Konfiguracja -> BillTech* i wpisz wartości zmiennych konfiguracyjnych otrzymanych od <admin@billtech.pl>. 
Podane wartości można również wprowadzić w panelu zakładce *Konfiguracja -> Interfejs użytkownika* w sekcji billtech.

## Dodatkowe informacje
### Obsługa płatności po stronie klienta
Wpłaty które powstają po wykonaniu płatności BillTech, to tzw. opłaty tymczasowe. Są tworzone aby użytkownik widział wykonaną opłatę w userpanelu. Wpłaty tymczasowe również umożliwiają natychmiastowe odblokowanie usług w przypadku blokady z powodu niepłacenia. 
Opłaty tymczasowe przestają być potrzebne w momencie pojawienia się opłat z banku, wtedy mogą zostać zamknięte, po czym przestają być widoczne w panelu admina. Istnieją 3 możliwości ich zamykania:

   1. Automatycznie rozliczanie w momencie dokonania cashimport-u. Aby włączyć zamykanie wpłat tymczasowych poprzez cashimport, należy ustawić wartość zmiennej billtech.cashimport_enabled na true.

   1. Po upływie zadanej liczby dni (domyślnie jest to 5 dni). Odpowiada za to zmienna środowiskowa billtech.payment_expiration. 
    Aby wpłaty tymczasowe nie wygasały po upływie czasu, należy ustawić tą zmienną na wartość `never`. 
    Takie ustawienie jest wskazane, gdy rozliczanie wpłat tymczasowych poprzez cashimport jest włączone (punkt pierwszy).
    
   1. Wpłaty tymczasowe można rozliczać manualnie poprzez panel Płatności BillTech. 
   W tym celu należy zaznaczyć wpłaty do rozliczenia i kliknąć przycisk *Zaznacz/Odznacz jako rozliczone*.

### Spis zmiennych konfiguracyjnych w sekcji billtech (billtech.<nazwa_zmiennej>):

##### Zmienne związane z łączeniem się z BillTech (umożliwiające dostęp do API systemu płatności BillTech)

| nazwa zmiennej 	| wartości 	| przykład                         	| opis                                                                                                                                                                                        	|
|----------------	|----------	|----------------------------------	|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------	|
| api_key        	| string   	| Lg8C6zy851WCMSx8d2hctoWIFAwPGlbk 	| Parametr wykorzystywany do uwierzytelnienia HTTP BASIC.                                                                                                                                     	|
| api_secret     	| string   	| fYA9FuqVjMQ4bJIEtNloBMUni1qAKNVi 	| Parametr wykorzystywany do uwierzytelnienia HTTP BASIC.  Otrzymywany po kliknięcie po podaniu parametru PIN i kliknięciu przycisku Generuj API secret w zakładce *Konfiguracja -> BillTech*. 	|
| api_url        	| string   	| https://api.test.billtech.pl     	| Adres do komunikacji z platformą BillTech                                                                                                                                                   	|

##### Zmienne związane z obsługą dokonanej płatności

| nazwa zmiennej      	| wartości   	| przykład       	| opis                                                                                                                                                                                                                          	|
|---------------------	|------------	|----------------	|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------	|
| payment_expiration  	| int/string 	| 5              	| Liczba dni po których wpłata tymczasowa BillTech znika z systemu. Dla wartości `never` mechanizm ten zostaje wyłączony. Taka powinna być wartość tej zmiennej w przypadku korzystania z cashimport-u (cashimport_enabled=true). 	|
| cashimport_enabled  	| boolean    	| true           	| Parametr umożliwiający automatyczne rozliczanie opłat tymczasowych poprzez mechanizm cashimport-u.                                                                                                                            	|
| isp_id              	| string     	| nazwa_dostawcy 	| Id dostawcy w systemie BillTech.                                                                                                                                                                                              	|
| produce_short_links 	| boolean    	| true           	| Odpowiada za podanie danych osobowych podczas generowania linków do płatności przez API, co sprawia że możliwe jest wygenerowanie skróconego linku do płatności, który można wysłać np. w SMS.                                	|

## Change Log

#### Wersja 1.0 (obecna)
* Dane na temat płatności są generowane w momencie ich powstawania w systemie LMS i identyfikowane w BillTech poprzez token, który jest główną częścią nowego, krótszego linka. 
Linki są zapisywane w bazie LMS w tabeli billtech_payment_links.
Istnieją 2 możliwości podania danych identyfikujących użytkownika dokonującego płatności:
    * dane mogą zostać dodane do linka poprzez paramtery zapytania (np. ?name=Jan&surname=Kowalski&email=email@example.com),
    * dane mogą zostać podane przy tworzeniu linka do płatności w body zapytania.
    Wtedy dane zostaną zapisane w bazie BillTech oraz umożliwią utworzenie skróconego linka. 
    Odpowiada za to parametr produce_short_links ustawiony na wartość true. 
* Przechowywanie linków do płatności w bazie powoduje wyeliminowanie problemów ze spójnością salda.
* Integracja z ekosystemem BillTech:
    * połączenia z bankami i aplikacjami,
    * przypomnienia o nadchodzących i przeterminowanych płatnościach,
    * płatności jednym kliknięciem z zapisanej karty,
    * autopłatności,
    * odraczanie płatności.
* Dodanie nowych tabel billtech_payment_links, billtech_customer_info oraz aktualizacja istniejących poprzez skrypty migracyjne. 
* Przeniesienie mechanizmu aktualizowania informacji nt. wpłat łączącego się z BillTech co 5 minut do skryptu cron. 
* Zmiana wartości parametrów payment_expiration. Aby wyłączyć mechanizm należy podać wartość `never` zamiast 0.
* Dodanie możliwości generowania skróconych linków. Dla wartości produce_short_links = true pole shortLink nie jest null.

#### Wersja 1.1 (nadchodząca)
* Rozróżnienie czy dany użytkownik jest w ekosystemie BillTech.
* Generowanie linków przez API tylko dla użytkowników, którzy są w ekosystemie.
Dla pozostałych użytkowników dane o saldzie zakodowane są w parametrach zapytania - przekazanie danych następuje dopiero w momencie kliknięcia w link.

## Kontakt
Więcej informacji na temat naszego API można znaleźć na stronie <https://docs.billtech.pl>. Po dane do połączenia prosimy o wysyłanie wiadomości na adres <admin@billtech.pl>

Jeżeli chciałbyś przetestować wtyczkę, zobaczyć jak wygląda proces płatności, rozpocząć współpracę lub dowiedzieć się więcej prosimy o wiadomość na adres <kontakt@billtech.pl>
