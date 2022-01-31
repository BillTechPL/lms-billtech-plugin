# Wtyczka Bill Gateway dla LMS od BillTech

## Opis
Bill Gateway to usługa, która pozwala Dostawcom na wygodne pobieranie należności od swoich klientów. 
Po wystawieniu faktury Dostawca generuje link do płatności, który może dostarczyć swoim klientom różnymi kanałami,
 np. wysłać w wiadomości e-mail, sms lub pokazać w panelu klienta (userpanel). 
Klient (użytkownik) po kliknięciu w taki link, zostaje przekierowany na ekran podsumowania płatności.
Informacja o wykonanej płatności natychmiast trafia do Dostawcy,
 dzięki czemu możliwe jest szybkie uregulowanie salda klienta oraz ewentualne zdjęcie blokady usług.
 
Więcej o korzyściach [Bill Gateway na stronie BillTech](https://billtech.pl/bill-gateway/).
 
Wtyczka umożliwia integrację z usługą Bill Gateway poprzez:
* Dodanie przycisku *Opłać teraz* do panelu klienta w sekcji finanse przy saldzie oraz indywidualnych 
fakturach pozwalając na wykonanie płatności online poprzez platformę BillTech,
* Dodanie przycisku *Opłać teraz* do wiadomości e-mail z fakturą oraz notyfikacji,
* Wstawienie informacji o płatności do nagłówków wiadomości e-mail z fakturą,
* Dodanie przycisku *Opłać teraz* do ekranu blokady internetu,
* Przekazanie informacji o płatności wykonanej na platformie BillTech do LMS.

Szczegółowa [dokumnetacja API](https://docs.billtech.pl/) produktu Bill Gateway.

> #### Uwaga
> Wtyczka do działania wymaga aktualizacji odpowiedniej wersji LMS. W przypadku posiadania najnowszej wersji
lmsgit nie jest konieczne dodatkowe działanie. W przeciwnym wypadku zapraszamy do kontaktu, chętnie pomożemy 
z wprowadzeniem odpowiednich zmian również do innych wersji.

## Instalacja
* Umieść zawartość tego repozytorium w katalogu *plugins/BillTech* w katalogu instalacyjnym LMSa,
* W katalogu projektu uruchom skrypt install.sh. W celu jego poprawnego działania plik lms.ini powinien znajdować się w katalogu `/etc/lms` i mieć wypełnione pole `sys_dir`,
* Zaloguj się do panelu admininistracyjnego LMS,
* Przejdź do zakładki *Konfiguracja -> Wtyczki*,
* Kliknij żarówkę po prawej stronie w wierszu z wtyczką BillTech, aby ją włączyć,
* W szablonie wiadomości e-mail z powiadomieniem o wystawieniu nowej faktury dodaj `%billtech_btn` i/lub `%billtech_balance_btn` w miejscu,
  w którym powinny pojawić się przyciski do opłacenia odpowiednio indywidualnej faktury i/lub salda. 

## Konfiguracja
W panelu administracyjnym wejdź w zakładkę *Konfiguracja -> BillTech* i wpisz wartości zmiennych konfiguracyjnych otrzymanych od <admin@billtech.pl>. 
Podane wartości można również wprowadzić w zakładce *Konfiguracja -> Interfejs użytkownika* w sekcji billtech.

## Dodatkowe informacje
### Obsługa płatności po stronie klienta
Operacje kasowe, które powstają po wykonaniu płatności BillTech, to tzw. wpłaty tymczasowe. Są tworzone po to, aby użytkownik oraz administrator systemu widzieli wykonaną płatność. Wpłaty tymczasowe umożliwiają natychmiastowe odblokowanie usług w przypadku blokady z powodu nieuregulowania opłat.
Wpłaty tymczasowe przestają być potrzebne w momencie zaksięgowania opłat z wykazu bankowego - mogą wtedy zostać rozliczone (zamknięte), po czym przestają być widoczne (znikają zarówno w panelu administracyjnym jak i w panelu klienta).
Istnieją 3 możliwości rozliczania wpłat tymczasowych:

   1. Automatyczne rozliczanie poprzez mapowanie odpowiednich transakcji pochodzących z wyciągu bankowego (tzw. cashimport). 
   
        Aby włączyć automatyczne rozliczanie opłat tymczasowych poprzez cashimport, należy ustawić wartość zmiennej `billtech.cashimport_enabled=true`.

   1. Po upływie określonej liczby dni (domyślnie jest to 5 dni), wpłaty tymczasowe są automatycznie zamykane jako rozliczone. Odpowiada za to zmienna środowiskowa `billtech.payment_expiration`. 
    
        Aby wpłaty tymczasowe nigdy nie wygasały po upływie zadanego czasu, należy ustawić zmienną `billtech.payment_expiration=never`. 
    Takie ustawienie jest wskazane, gdy rozliczanie wpłat tymczasowych poprzez cashimport jest włączone (punkt pierwszy).
    
   1. Wpłaty tymczasowe można rozliczać manualnie poprzez panel Płatności BillTech. 
   
        W tym celu należy zaznaczyć opłaty do rozliczenia i kliknąć przycisk *Zaznacz/Odznacz jako rozliczone*. 
   W przypadku pomyłki, proces ten jest w pełni odwracalny poprzez wskazanie wyszarzonych (rozliczonych) wpłat tymczasowych, a następnie kliknięcie przycisku *Zaznacz/Odznacz jako rozliczone*. 

### Spis zmiennych konfiguracyjnych w sekcji billtech (billtech.<nazwa_zmiennej>):

##### Zmienne związane z łączeniem się z BillTech (umożliwiające dostęp do API systemu płatności BillTech)

| nazwa zmiennej 	| wartości 	| przykład                         	| opis                                                                                                                                                                                        	|
|----------------	|----------	|----------------------------------	|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------	|
| api_key        	| string   	| Lg8C6zy851WCMSx8d2hctoWIFAwPGlbk 	| Parametr wykorzystywany do uwierzytelnienia HTTP BASIC.                                                                                                                                     	|
| api_secret     	| string   	| fYA9FuqVjMQ4bJIEtNloBMUni1qAKNVi 	| Parametr wykorzystywany do uwierzytelnienia HTTP BASIC.  Otrzymywany po podaniu parametru PIN i kliknięciu przycisku Generuj API secret w zakładce *Konfiguracja -> BillTech*. 	            |
| api_url        	| string   	| https://api.test.billtech.pl     	| Adres do komunikacji z platformą BillTech.                                                                                                                                                 	|

##### Zmienne związane z obsługą dokonanej płatności
> #### Uwaga
> 
>W przypadku braku któregokolwiek z paramterów opcjonalnych typu boolean (aka flag) LMS traktuje je jakby miały wartość false.

| nazwa zmiennej      	  	| wartości   	| przykład                   	| opis                                                                                                                                                                                                                                                          	|
|-------------------------	|------------	|----------------------------	|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------	|
| payment_expiration  	  	| int/string 	| 5                          	| Liczba dni po których wpłata tymczasowa BillTech znika z systemu. Dla wartości  `never`  mechanizm ten zostaje wyłączony -- taka powinna być wartość w przypadku korzystania z rozliczania wpłat tymczasowych poprzez cashimport (`cashimport_enabled=true`). 	|
| isp_id              	  	| string     	| nazwa_dostawcy             	| Id dostawcy w systemie BillTech.                                                                                                                                                                                                                              	|
| bankaccount         	  	| string     	| 61109010140000071219812874 	| Opcjonalny parametr. Odpowiada za globalny numer rachunku bankowego wykorzystywany do generowania linków dla wszystkich klientów. W przypadku niepodania tego parametru linki są tworzone na podstawie indywidualnych rachunków bankowych klientów.               |
| manage_cutoff 	      	| boolean    	| true                       	| Opcjonalny parametr. Powinien być ustawiony na wartość true w przypadku włączonego mechanizmu blokady usług w LMS (dla niepłacących klientów). Wartość początkowa: true                                                                                           |
| append_customer_info 	  	| boolean    	| true                       	| Opcjonalny parametr. Odpowiada za dodanie danych osobowych podczas procesu tworzenia linków. Skutkuje wygenerowaniem skróconych linków do płatności, które mogą mieć zastosowanie np. w wiadomościach SMS. Wartość początkowa: true                               |
| cashimport_enabled  	  	| boolean    	| true                       	| Opcjonalny parametr. Umożliwia automatyczne rozliczanie opłat tymczasowych poprzez wyciąg bankowy. Wartość początkowa: true                                                                                                                                	    |
| balance_button_disabled  	| boolean    	| true                       	| Opcjonalny parametr. Umożliwia schowanie przycisku opłacenia salda w panelu klienta. Wartość początkowa: false                                                                                                                                	                |
| row_buttons_disabled    	| boolean    	| true                       	| Opcjonalny parametr. Umożliwia schowanie przycisków opłacenia przy każdej fakturze w panelu klienta. Wartość początkowa: false                                                                                                                                	|

## Change Log

#### Wersja 1.0 (obecna)
* Dane na temat płatności są generowane w momencie ich powstawania w systemie LMS i identyfikowane w BillTech poprzez token, który jest główną częścią nowego, krótszego linku. 
Linki są zapisywane w bazie LMS w tabeli billtech_payment_links.
Istnieją 2 możliwości podania danych identyfikujących użytkownika dokonującego płatności:
    * dane mogą zostać dodane do linku poprzez parametry zapytania (np. ?name=Jan&surname=Kowalski&email=email@example.com),
    * dane mogą zostać podane przy tworzeniu linku do płatności w body zapytania.
    Wtedy dane zostaną zapisane w bazie BillTech oraz umożliwią utworzenie skróconego linku. 
* Przechowywanie linków do płatności w bazie powoduje wyeliminowanie problemów ze spójnością salda.
* Integracja z ekosystemem BillTech (poprzez alternatywne metody płatności na bramce płatniczej):
    * połączenia z bankami i aplikacjami,
    * przypomnienia o nadchodzących i przeterminowanych płatnościach,
    * płatności jednym kliknięciem z zapisanej karty,
    * autopłatności,
    * odraczanie płatności.
* Dodanie nowych tabel billtech_payment_links, billtech_customer_info oraz aktualizacja istniejących poprzez skrypty migracyjne. 
* Przeniesienie mechanizmu aktualizowania informacji nt. wpłat łączącego się z BillTech co 5 minut do skryptu cron. 
* Zmiana możliwych wartości zmiennej `billtech.payment_expiration`. Aby wyłączyć mechanizm automatycznego zamykania się wpłat tymczasowych należy podać wartość `never` zamiast `0`.
* Dodanie możliwości generowania skróconych linków. Dla wartości zmiennej środowiskowej `billtech.produce_short_links=true` pole shortLink w tabeli billtech_payment_links zawiera skrócony link.
* Naprawienie błędu wynikającego z korzystania ze starszej wersji systemu zarządzania bazą danych (MySQL lub PostgreSQL) polegającego na tworzeniu rekordów tabeli billtech_payments z niepoprawną wartości pola cashid (wynoszącą 1 dla wszystkich wpisów).

#### Wersja 1.1 (nadchodząca)
* Rozróżnienie czy dany użytkownik jest w ekosystemie BillTech.
* Generowanie linków przez API tylko dla użytkowników, którzy są w ekosystemie.
Dla pozostałych użytkowników dane o saldzie zakodowane są w parametrach zapytania - przekazanie danych następuje dopiero w momencie kliknięcia w link.

## Znane problemy
* Spontaniczne dodawanie spacji oraz znaku nowej linii w treści wiadomości z fakturą, skutkujące załączaniem niepoprawnego linku (niepotrzebne znaki występują w treści linku).

    Wskazany problem występuje rzadko, natomiast gdy ma miejsce, to wynika z korzystania z silnika pocztowego Pear. 
LMS ma możliwość korzystania z dwóch silników pocztowch służących do wysyłania wiadomości z fakturą i notyfikacji. 
Stosowany silnik można wskazać poprzez wartość zmiennej środowiskowej `mail.backend`. Obsługiwane wartości to `pear` lub `phpmailer`.
W przypadku gdy problem się pojawi, zalecamy ustawienie zmiennej `mail.backend=phpmailer`. 
Nie odnotowaliśmy żadnych skutków ubocznych wśród użytkowników, którzy dokonali zmiany silnika pocztowego na PHPMailer.

## Kontakt
Więcej informacji na temat naszego API można znaleźć na stronie <https://docs.billtech.pl>. Po dane do połączenia prosimy o wysyłanie wiadomości na adres <admin@billtech.pl>

Jeżeli chciałbyś przetestować wtyczkę, zobaczyć jak wygląda proces płatności, rozpocząć współpracę lub dowiedzieć się więcej, prosimy o wiadomość na adres <sales@billtech.pl>

## Odinstalowanie wtyczki

Proces odinstalowania wtyczki został opisany w oddzielnym pliku [UNINSTALL.md](../blob/master/UNINSTALL.md).