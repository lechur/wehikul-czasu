# Aplikacja mieszkańca Kobyłki

Odpady (oficjalne kalendarze Urzędu, aktualizowane ręcznie; terminy opublikowane do końca 2026 r.), wydarzenia, zgłoszenia i pisma do Urzędu Miasta, przekazywanie spraw radnym, mapy i niebo nad Kobyłką.

Inicjatywa radnej Edyty Zbieć, otwarta dla wszystkich radnych Rady Miasta Kobyłka. To nie jest usługa Urzędu Miasta.

Aplikacja nie zbiera danych osobowych na serwerze: sprawy zostają w telefonie mieszkańca. Przekazana sprawa trafia e-mailem do wybranego radnego, który odpowiada za te dane.

Radni: adres e-mail do przyjmowania spraw dopisuje się w `radni.json`.

## Cotygodniowa aktualizacja danych
- `terminy.json`: {"pobrano","zrodlo","rejony":{"Rejon I":[{"data":"RRRR-MM-DD","frakcja":"..."}]}}; nazwy frakcji: "Bioodpady", "Zmieszane (niesegregowane)", "Segregowane: tworzywa i metale, papier, szkło". Identyfikatory kalendarzy Google rejonów: `dane.json` → odpady.rejony[].kalendarz_id.
- `radni_aktywnosc.json`: liczby interpelacji i zapytań z rejestru BIP (kobylka.bipgmina.pl/wiadomosci/dzial/4449).
- `dane.json` → rada.sesje_daty, rada.dyzury_daty, wydarzenia.
- Po każdej zmianie danych podnieś numer wersji w `sw.js` (const C='kobylka-vN').
- `index.html` jest wersją główną (dawna propozycja v2); poprzednia wersja: `scripts/archiwum/index-v1.html`.


## Moduły miejskie (moduly.js)

- `komunikacja.json`: linie, przystanki, odjazdy i pociągi. Widok liczy czasy przesiadek, gdy linie mają `kierunki` z `odjazdy`, a `pociagi.stacje` zawiera godziny. Bez nich pokazuje tylko listę linii i bilety. Źródła: GTFS ZTM Warszawa (linie L), tabela R6 Kolei Mazowieckich.
- `budzet.json`: uchwała budżetowa, plan po zmianach, wykonanie, procedura (BIP: kobylka.bipgmina.pl, dział budżet).
- `bo.json`: budżet obywatelski (budzet.kobylka.pl): zasady, edycje, projekty ze współrzędnymi i statusem.
- W cotygodniowej aktualizacji sprawdzaj: nowy projekt budżetu (do 15 XI), zmiany planu, ogłoszenie nowej edycji BO, statusy realizacji projektów BO, zmiany rozkładów.
