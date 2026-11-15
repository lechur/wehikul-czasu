# Wołomin (miasto + gmina) – gotowe

Stan: 15.11.2026. Plik: wolomin.json (74 rejony: 52 miejskie m1–m52, 22 wiejskie g1–g22; 327 wpisów ulic/miejscowości; 2733 daty; okres do 2027-03-31, czyli do końca harmonogramu 01.04.2026–31.03.2027).

## Źródła i odczyt
- Miasto: https://wolomin.org/wp-content/uploads/2026/03/Harmonogram-Miasto-Wolomin-2026.pdf (6 str., PDF tekstowy)
- Gmina (wsie): https://wolomin.org/wp-content/uploads/2026/03/Harmonogram-Gmina-Wolomin-2026.pdf
- Odczyt: pdf.js w przeglądarce, kolumny tabel przypisane po współrzędnej x nagłówków "Rejon N"; listy ulic parsowane z tekstu PDF, a unikalne kombinacje rejonów zbudowane programowo.
- Kontakt (z PDF): Miejski Zakład Oczyszczania w Wołominie, ul. Łukasiewicza 4, tel. 22 318 14 92; reklamacje (UM) 22 763 30 47.

## WAŻNE: jak zbudowano rejony
PDF ma osobny podział na rejony dla każdej frakcji: miasto – zmieszane 10 rejonów, worki (papier/szkło/tworzywa+metale) 8, bio 5, popiół 5; gmina – zmieszane 8, worki 7, bio 5, popiół 5. Żeby zachować format (jeden rejon = komplet terminów), rejon w JSON = unikalna kombinacja (zm, bio, worki, popiół); jej numery są w polu "nazwa". Każdy rejon ma to, co wynika z PDF dla tych ulic.
- Frakcje: zm = zmieszane; bio; pap=mtw=szk = "odpady segregowane w workach" (jedna tabela z jedną datą dla wszystkich worków; legenda kolorów worków: papier, szkło, tworzywa, metale – przyjęto, że wszystko jest odbierane tego dnia, bo tabela jest jedna); pop = popiół (tabela "POPIÓŁ", odbiór co 2 tyg., w zimie).
- Gabaryty: tylko jedna data w roku (16.05.2026 miasto / 23.05.2026 gmina) – już minęła, więc brak "gab". Mobilny PSZOK bez dat w PDF.
- Brak frakcji: zie, cho.

## Wątpliwości i braki
1. Ulice dzielone (w PDF z opisem "od … do …") – POMINIĘTE w JSON, bo nie da się wiarygodnie sparować segmentów z rejonami poszczególnych frakcji (wpisanie we wszystkie rejony dałoby błędne daty): Legionów, Sikorskiego, Lipińska, Matejki, Mickiewicza, Radzymińska, Kobyłkowska, Krótka, Przejazd, Szosa Jadowska, Aleja Niepodległości, Topolowa, Geodetów, Aleja Armii Krajowej, Kiejstuta, Kościuszki, Poniatowskiego, Słowackiego. Wymagają ręcznego dopisania z PDF (str. 2–5) – mogę to zrobić na żądanie.
2. Gmina – adresy szczególne pominięte, bo PDF nie podaje dla nich wszystkich frakcji: Nowe Grabie 144 (zm r3, bio/pop r5, worki nieujęte), Nowe Grabie nr 117,119,120,121 (tylko w zm r6), Ossów 192 (tylko w zm r8).
3. Gmina, wsie: miejscowości podzielone na ulice zapisano jako "Wieś – ulica" (np. "Duczki – Azaliowa", "Zagościniec – Graniczna"); miejscowości w całości w jednym rejonie – sama nazwa wsi. Mostówka w PDF nie ma ulic; w worki r2 (zgodnie z układem listy worków – sąsiedztwo z listą rejonu 2), w pozostałych r1/zm r3.
4. Daty w kolumnach nie wynikające z rytmu, ale odczytane wprost (np. zm miasto r3 i gmina r3: 2027-03-31; zm r7: 2027-01-02; zm r2: 2026-12-19; popiół gmina r3: 2027-01-09, r1/r3: 2027-03-31). Brak weryfikacji wizualnej tych komórek – zgodne z tekstem PDF w dwóch niezależnych parsowaniach.
5. W PDF "bio gmina" w Kwietniu jest literówka "29.04.2025" (nieistotna, data przeszła).
6. Dec 31: PDF informuje, że PSZOKi są nieczynne 31 grudnia; odbiory z kalendarzem na 31.12 (zm, worki r8 miasto/r5 gmina, popiół) wpisane literalnie z tabel.

## Sprawdzenia wyrywkowe (odczyt wizualny PDF, drugi raz)
Miasto, worki (screenshot str. 3): rejon 1 – 03.12.2026, 07.01.2027; rejon 2 – 02.12.2026 i 09.01.2027 (czerwone = przesunięty); rejon 4 – 09.12.2026 (czerwone); rejon 5 – 16.12.2026; rejon 7 – 23.12.2026 – zgodne z JSON.
Miasto, bio (screenshot str. 4): rejon 1 – 07.12.2026, 11.01.2027, 08.02.2027; rejon 2 – 08.12.2026; rejon 5 – 11.12.2026 – zgodne.
Tabele zmieszane i popiół miasta oraz wszystkie tabele gminy: odczyt programowy dwoma niezależnymi parserami (zgodne); wizualnie sprawdzono tylko układ kolumn str. 5 (kwiecień–październik), bez styczniowych komórek szczególnych.
