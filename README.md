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

Szczegółowa [dokumentacja API](https://docs.billtech.pl/) produktu Bill Gateway.

> #### Uwaga
> Wtyczka do działania wymaga aktualizacji odpowiedniej wersji LMS. W przypadku posiadania najnowszej wersji
lmsgit nie jest konieczne dodatkowe działanie. W przeciwnym wypadku zapraszamy do kontaktu, chętnie pomożemy 
z wprowadzeniem odpowiednich zmian również do innych wersji.

## Licencja
Wtyczka udostepniana jest na licencji MPL (Mozilla Public License) z klauzulą Commons Clause. Więcej informacji znajduje się w pliku license.md.
Firma Billtech prowadzi odpłatną pomoc techniczną - jeśli jesteś zainteresowany wykup konstultację w aplikacji rezerwacyjnej https://billtech.trafft.com/ lub skontaktuj się z nami mailowo pod adresem <lms@billtech.pl>.

## Instalacja
* Umieść zawartość tego repozytorium w katalogu *plugins/BillTech* w katalogu instalacyjnym LMSa,
* W katalogu projektu uruchom skrypt install.sh. W celu jego poprawnego działania plik lms.ini powinien znajdować się w katalogu `/etc/lms` i mieć wypełnione pole `sys_dir`,
* Zaloguj się do panelu admininistracyjnego LMS,
* Przejdź do zakładki *Konfiguracja -> Wtyczki*,
* Kliknij żarówkę po prawej stronie w wierszu z wtyczką BillTech, aby ją włączyć,
* W szablonie wiadomości e-mail z powiadomieniem o wystawieniu nowej faktury dodaj `%billtech_btn` i/lub `%billtech_balance_btn` w miejscu,
  w którym powinny pojawić się przyciski do opłacenia odpowiednio indywidualnej faktury i/lub salda. 

## Odinstalowanie wtyczki
Proces kompletnego odinstalowania wtyczki został opisany w oddzielnym pliku [UNINSTALL.md](../master/UNINSTALL.md).
Odinstalowanie jest procesem nieodwracalnym, jeśli potrzebujesz zdezaktywować wtyczkę na pewien okres czasu zalecamy
skorzystanie z przełącznika aktywności wtyczki BillTech znajdującego się w panelu administracyjnym LMS w zakładce: *Konfiguracja->Wtyczki*.

## Konfiguracja
W panelu administracyjnym wejdź w zakładkę *Konfiguracja -> BillTech* i wpisz wartości zmiennych konfiguracyjnych otrzymanych od <lms@billtech.pl>. 
Podane wartości można również wprowadzić w zakładce *Konfiguracja -> Interfejs użytkownika* w sekcji billtech.

## Dodatkowe informacje
### Obsługa płatności po stronie klienta
Operacje kasowe, które powstają po wykonaniu płatności BillTech, to tzw. wpłaty tymczasowe. Są tworzone po to, aby użytkownik oraz administrator systemu widzieli wykonaną płatność. Wpłaty tymczasowe umożliwiają natychmiastowe odblokowanie usług w przypadku blokady z powodu nieuregulowania opłat.
Wpłaty tymczasowe przestają być potrzebne w momencie zaksięgowania opłat z wykazu bankowego - mogą wtedy zostać rozliczone (zamknięte), po czym przestają być widoczne (znikają zarówno w panelu administracyjnym jak i w panelu klienta).
Istnieją 3 możliwości rozliczania wpłat tymczasowych:

   1. Automatyczne rozliczanie poprzez mapowanie odpowiednich transakcji pochodzących z wyciągu bankowego (tzw. cashimport). 
   
        Aby włączyć automatyczne rozliczanie opłat tymczasowych poprzez cashimport, należy ustawić wartość zmiennej `billtech.cashimport_enabled=true`.

   2. Po upływie określonej liczby dni (domyślnie jest to 5 dni), wpłaty tymczasowe są automatycznie zamykane jako rozliczone. Odpowiada za to zmienna środowiskowa `billtech.payment_expiration`. 
    
        Aby wpłaty tymczasowe nigdy nie wygasały po upływie zadanego czasu, należy ustawić zmienną `billtech.payment_expiration=never`. 
    Takie ustawienie jest wskazane, gdy rozliczanie wpłat tymczasowych poprzez cashimport jest włączone (punkt pierwszy).
    
   3. Wpłaty tymczasowe można rozliczać manualnie poprzez panel Płatności BillTech. 
   
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
* Naprawienie błędu wynikającego z korzystania ze starszej wersji systemu zarządzania bazą danych (MySQL lub PostgreSQL) polegającego na tworzeniu rekordów tabeli billtech_payments z niepoprawną wartości pola cashid (wynoszącą 1 dla wszystkich wpisów).

#### Wersja 1.1 (nadchodząca)
* Rozróżnienie czy dany użytkownik jest w ekosystemie BillTech.
* Generowanie linków przez API tylko dla użytkowników, którzy są w ekosystemie.
Dla pozostałych użytkowników dane o saldzie zakodowane są w parametrach zapytania - przekazanie danych następuje dopiero w momencie kliknięcia w link.

# FAQ

## Brak linków do opłacenia na wygenerowanych fakturach
<details>
  <summary>Sprawdzenie CRONów</summary>

Należy zweryfikować, czy CRONy odpowiedzialne za generowanie wtyczki są uruchomione (komenda `crontab -e`). Poprawnie powinny być uruchomione trzy crony:

  ```bash
  0 0 1 * * bash -c ${sys_dir}/plugins/BillTech/bin/billtech-clear-logs.php
  0,5,10,15,20,25,30,35,40,45,50,55 * * * * bash -c ${sys_dir}/plugins/BillTech/bin/billtech-update-links.php | ${sys_dir}/plugins/BillTech/bin/timestamp.sh >> /var/log/billtech/`date +%Y%m%d`-update-links.log 2>&1
  * * * * * bash -c ${sys_dir}/plugins/BillTech/bin/billtech-update-payments.php | ${sys_dir}/plugins/BillTech/bin/timestamp.sh >> /var/log/billtech/`date +%Y%m%d`-update-payments.log 2>&1
  ```
</details>

<details>
  <summary>Sprawdzenie logów</summary>

Należy sprawdzić logi odpowiadające za generowanie faktur: `/var/log/billtech/*-update-links.log`. Najczęstsze błędy to:

- **"Could not acquire lock. Another process is running."**
    - Jeśli błąd widoczny jest dłużej niż kilka minut, oznacza to, że istnieje inny proces uruchomiony przez CRON, który nie został zamknięty.
    - Rozwiązanie: Znalezienie procesu na maszynie, zabicie go oraz usunięcie pliku `/tmp/billtech-lock-update-links-*`.

- **"Validation of the request failed"**
    - Błędy mogą wynikać z niepoprawnych danych przesyłanych do systemu, np.:
        - Nieprawidłowe znaki w tytułach faktur
        - Puste lub za długie tytuły faktur
        - Pusty numer konta

Aby debugować błąd, można dodać linię `print_r($apiRequests);` w pliku `lib/BilltechLinkApiService.php` w funkcji `generatePaymentLinks`. Jeśli w LMS dane są poprawne, może być konieczna edycja zapytań SQL w `getLinkData()`.
</details>

## Brak linku w wiadomości e-mail/SMS
<details>
  <summary>Sprawdzenie opóźnień</summary>

Poza sprawdzeniem problemów opisanych w punkcie 1, należy uwzględnić odstęp czasowy od generowania faktur do wysyłki.
- Wtyczka generuje maksymalnie 100 linków na minutę.
- Przy generowaniu kilku tysięcy linków proces może potrwać dłużej.
</details>

## Duplikaty wpłat bankowych na liście użytkowników
<details>
  <summary>Sprawdzenie mechanizmu mapowania</summary>

Każdy przelew tymczasowy posiada `reference_number`, który służy do mapowania z realną wpłatą bankową.

- Należy zweryfikować działanie funkcji `processCashImport` w pliku `handlers/BillTechPaymentCashImportHandler.php`.
- Jeśli stosowane są indywidualne mechanizmy importu, trzeba sprawdzić ich kompatybilność ze standardowym systemem `cashimport`.
- Zmienna konfiguracyjna `billtech.cashimport_enabled` powinna być ustawiona na `true`.
</details>

## Brak wpłat tymczasowych użytkowników
<details>
  <summary>Sprawdzenie CRONów i logów</summary>

- Sprawdzić poprawne uruchomienie CRONów (`crontab -e`).
- Przejrzeć logi `/var/log/billtech/*-update-payments.log`.
- Jeśli pojawia się błąd **"Could not acquire lock. Another process is running."**, oznacza to, że inny proces CRON nie został zamknięty.
    - Rozwiązanie: Znalezienie procesu na maszynie, zabicie go oraz usunięcie pliku `/tmp/billtech-lock-update-payments-*`.
</details>

## Kontakt
Więcej informacji na temat naszego API można znaleźć na stronie <https://docs.billtech.pl>. Po dane do połączenia prosimy o wysyłanie wiadomości na adres <lms@billtech.pl>

Jeżeli chciałbyś przetestować wtyczkę, zobaczyć jak wygląda proces płatności, rozpocząć współpracę lub dowiedzieć się więcej, prosimy o wiadomość na adres <sales@billtech.pl>
