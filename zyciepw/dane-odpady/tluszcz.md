# Tłuszcz - harmonogram odbioru odpadów (gotowe: rejony 1-10; rejon 11 pominięty)
Odczyt 15.11.2026. Plik: tluszcz.json (poprawny JSON).

## Źródła
- Strona: https://tluszcz.pl/strona-3395-harmonogram_odbioru_odpadow.html ("TERMINY ODBIORU ODPADÓW NA ROK 2026 kwiecień - grudzień"; ulice rejonów 1-3 i miejscowości 4-10 w treści strony).
- PDF-y kalendarzowe (jeden na rejon): https://tluszcz.pl/Common/pobierzPlik/id/{863,853,854,855,856,857,858,859,860,861,862}/module_short/port/obj_id/3395/culture/pl/version/pub.html = rejony 1-11. Pominięto PDF dla firm (id 864, nieobjęty zleceniem).
- Kontakt: wykonawca (Jurant) tel. 609 112 922, bok@jurant.pl; Biuro Gospodarki Odpadami UM Tłuszcz 29 777 52 61.

## Sposób odczytu
Kalendarze miesięczne w PDF: pdf.js, dzień + oznaczenie (Z zmieszane, B bio, PAP papier, PL żółte worki = tworzywa+metale, S szkło, P popiół, G gabaryt) przypisane po pozycji. Kontrole automatyczne: liczba dni w każdym miesiącu (30/31) i zgodność dnia tygodnia z kolumną - wszystkie OK dla rejonów 1-10. Dodatkowo odczyt wizualny (render PDF).

## Zakres i liczby
- 10 rejonów w JSON (r1-r10), 197 pozycji ulic/miejscowości, 109 dat, terminy 2026-11-15 .. 2026-12-31.
- 2027: brak - strona publikuje wyłącznie "kwiecień - grudzień 2026".
- Strona mówi "10 rejonów", ale wymienia 11 (rejon 11 = zabudowa wielorodzinna).
- Gabaryty (gab): tylko rejon 3 (28.11); w innych rejonach brak terminów po 15.11.
- Rejony 4 i 5 mają identyczne terminy; 6 i 7 identyczne; 8 i 9 identyczne (tak w PDF-ach).
- Boże Narodzenie: w kalendarzach 24 i 25.12 przekreślone (brak odbioru); zmieszane przesunięte (np. rejony 6, 7: 30.12 zamiast 24.12).

## Rejon 11 (zabudowa wielorodzinna) - NIE wpisany
PDF nie zawiera listy ulic/adresów (tylko "ZABUDOWA WIELORODZINNA"), strona też nie. Terminy odczytane (Z, Z* = zmieszane tylko osiedle Jedność, S = papier+tworzywa+metale, S* = + szkło, B bio), np. listopad: B 16, 23, 30; Z+S 17; Z+S* 24; Z* 20, 27 - ale bez ulic nie da się przypisać mieszkańca, więc pominięto.

## 5 wyrywkowych sprawdzeń (drugi odczyt wizualny z renderu PDF)
1. Rejon 1: 20.11 szkło, 23.11 papier, 24.11 zmieszane - OK
2. Rejon 1, grudzień: 3.12 popiół, 11.12 żółte worki, 21.12 papier, 22.12 zmieszane - OK
3. Rejon 2: 17.11 Z, 18.11 papier+szkło, 27.11 tworzywa - OK
4. Rejon 10: 16.11 Z, 20.11 papier, 24.11 szkło, 25.11 bio, 30.11 Z - OK
5. Rejon 11 (wizualnie, legenda): potwierdzono sens oznaczeń Z/Z*/S/S*

## Wątpliwości
- Liczba pozycji "ulice" w r4-r10 to miejscowości (całe wsie).
- Jedyny termin w sobotę: gabaryty rejon 3, 28.11.2026 (odczytane z kalendarza, G).
