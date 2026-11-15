# Ząbki – gotowe (tylko 2026, zabudowa jednorodzinna)

Stan: 15.11.2026. zabki.json: 10 rejonów, 200 wpisów ulic, 121 dat (2026-11-15 … 2026-12-31). Harmonogram 2027 nie jest opublikowany.

## Źródła i odczyt
- Strona (adres z "2025", treść "Harmonogram odbioru odpadów 2026", data wpisu 20.01.2026): https://www.zabki.pl/godspodarka-odpadami/harmonogram-odbioru-odpadow-2025 – listy ulic rejonów 1–10 i linki do PDF.
- PDF REJON 1–10 (kalendarze roczne, 1 strona): https://www.zabki.pl/media/download/<uuid> (np. Rejon 1: df403ca8-ae43-4295-b29e-5fa0ea7e17f7). Daty w tych PDF-ach są oznaczone KOLOREM pola obok numeru dnia (tekstu odpowiadającego frakcji brak), więc PDF-y wyrenderowano (pdf.js) i odczytano wizualnie z powiększeń oraz pomocniczo z próbkowania koloru pikseli (wyniki zgodne).
- Legenda z PDF: czarny = zmieszane + bioodpady; żółty = segregowane (plastik, papier, szkło) razem; zielony = zm.+bio+choinki (styczeń); fioletowy = tekstylia; pomarańczowy = wielkogabarytowe; przekreślone = brak odbioru.
- Mapowanie na frakcje: zm = bio = czarne daty; pap = mtw = szk = żółte daty (PDF nie rozdziela papieru/tworzyw/szkła); gab = pomarańczowe.

## Wątpliwości i braki
1. Zabudowa wielorodzinna (wspólnoty) NIE ujęta – osobny PDF "Wspólnoty harmonogram 2026" (https://www.zabki.pl/media/download/8ffc234c-d99f-4572-a252-e4045602b9d9) i harmonogram gabarytów dla wspólnot.
2. Gabaryty: tylko Rejon 4 i Rejon 9 mają pomarańczową datę po 15.11 (19.11.2026; w rejonie 9 ten sam dzień co zm.+bio). Odbiór po wcześniejszym zgłoszeniu (1 tydz.–1 dzień przed, do 14:00/15:00). Pozostałe rejony – brak gab w listopadzie/grudniu.
3. Sobota 19.12.2026 (rejon 1) – zm.+bio w sobotę (24.12 przekreślone); R6 18.12 – komórka dzielona czarny+żółty (zm.+bio oraz segregowane tego dnia); R9 19.11 – czarny+pomarańczowy.
4. Zielone (choinki) tylko w styczniu – poza zakresem.
5. Ulice rejonów bez opisu "od…do…" – brak ulic dzielonych. Nazwy skrócone jak w źródle (np. "Skorupki", "Obrońców", "Westerplatte", "Wigury", "Żwirki").

## Sprawdzenia wyrywkowe (drugi odczyt wizualny)
Rejon 1: 19.12.2026 zm.+bio (sobota), 31.12.2026 segregowane; Rejon 6: 18.12.2026 zm.+bio+segregowane; Rejon 7: wtorki 01.12 i 29.12 zm.+bio, 08.12 segregowane; Rejon 9: 19.11.2026 zm.+bio+gabaryty; Rejon 10: 26.11.2026 segregowane, 27.11 zm.+bio, 22.12 zm.+bio. Odczytane z powiększeń kalendarzy – zgodne z JSON. (Rejony 3,4,5,8 – odczyt z powiększeń, zgodny z próbkowaniem.)
