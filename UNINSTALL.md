# Odinstalowanie wtyczki BillTech dla LMS

> #### Uwaga
> Przed usunięciem wtyczki wykonaj backup zarówno plików na serwerze jak i bazy danych. 
> Nie odpowiadamy za utracone dane w skutek procesu odinstalowania.

### 1. Operacje na plikach

#### 1.1. Usunięcie plików wtyczki

Wtyczka znajduje się w katalogu *{sys_dir}/plugins/Billtech/*.
Należy usunąć ten katalog wraz ze wszystkimi plikami oraz podkatalogami.

#### 1.2. Usunięcie plików logów

Logi domyślnie znajdują się w katalogu */var/log/billtech/*. 
Należy usunąć cały katalog /billtech/ wraz ze znajdującymi się tam plikami.

### 2. Operacje na bazie danych

#### 2.1. Usunięcie tabeli

Należy usunąć poniższe tabele, dedykowane dla wtyczki, które zostały dodane w trakcie procesu instalacji:
- billtech_customer_info
- billtech_info
- billtech_log
- billtech_payment_links
- billtech_payments

Można to wykonać za pomocą poniższego zapytania SQL:

```
DROP TABLE IF EXISTS billtech_customer_info,billtech_info,billtech_log,billtech_payment_links,billtech_payments;
```
Należy również usunąć sekwencje:
- billtech_payments_id_seq,
- billtech_payment_links_id_seq

Można to wykonać za pomocą poniższego zapytania SQL:

```
DROP SEQUENCE IF EXISTS billtech_payments_id_seq, billtech_payment_links_id_seq;
```

#### 2.2. Usunięcie zmiennych konfiguracyjnych

W pliku instalacyjnym Readme.md wymieniona jest lista wszystkich zmiennych konfiguracyjnych wykorzystywanych przez wtyczkę.
Posiadają one prefix 'billtech.' przez co znaduja się w jeden sekcji. Należy je usunąć.

Możliwe jest usunięcię poszczególnych zmiennych poprzez panel administracyjny LMS w zakładce *Ustawienia->Konfiguracja*.
Należy wybrać z listy sekcję billtech, a nastepnie zaznaczyć wszystkie wiersze i je usunąć.

Alternatywna opcja to zapytanie do bazy danych. Poniższy kod SQL usuwa zmienne konfiguracyjne powiązane z wtyczką BillTech
z tabeli *uiconfig*:

```
DELETE FROM uiconfig WHERE section = 'billtech';
```

Dodatkowo należy usunąć wtyczkę z listy na podstronie panelu LMS Konfiguracja -> Wtyczki. Można to wykonać poniższym
kodem SQL:

```
DELETE FROM uiconfig WHERE section = 'phpui' and var = 'plugins' and value = 'BillTech';
```

> #### Uwaga
> Należy sprawdzić także czy w pliku lms.ini nie zostały dodane ręcznie zmienne konfiguracyjne wtyczki.
> W takim przypadku należy także ręcznie je usunąć.


#### 2.3. Usunięcie rekordu dbinfo

W tabeli dbinfo znajduje się rekord dotyczący wersji aktualnej bazy danych wtyczki. Należy usunąć rekord dla którego wartość kolumny keytype = 'dbversion_BillTech'.

```
DELETE FROM dbinfo WHERE keytype = 'dbversion_BillTech';
```

### CRON

Należy usunąć rekordy z crontab'a. Bezpośrednio związane z wtyczką są 3 pliki:
- billtech-clear-logs.php
- billtech-update-links.php
- billtech-update-payments.php


Aby edytować crontab, należy wykonać poniższą komendę i usunąć w edytorze linijki powiązane z powyższymi plikami.

```
crontab -e
```
