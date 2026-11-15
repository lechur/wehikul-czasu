# Życie Powiatu – aplikacja dla czytelników (PWA)

Adres: https://lechur.github.io/wehikul-czasu/zyciepw/

Wzorowana na Aplikacji mieszkańca Kobyłki (`../kobylka`). Wszystkie dane pobiera na żywo z zyciepw.pl – nie wymaga cotygodniowej aktualizacji plików.

- Wiadomości, wyszukiwarka, czytnik: `wp-json/wp/v2/posts` (kategorie gmin w tablicy `G` w `index.html`, pasek „Ważne” = tag 41864).
- Pytanie dnia: `wp-json/zr/v1/pytanie` (GET i POST głosu).
- Twoja okolica: `zr/v1/ulica`, `zr/v1/odpady` (odpady pojawią się w kolejnych gminach, gdy portal doda harmonogramy).
- Powietrze: `zr/v1/powietrze` (GIOŚ), pogoda: Open-Meteo.
- Mapa dzików: `mieszkaniec-api/?zasob=dziki` (odczyt i zgłoszenia), granice gmin: `gminy.json` (BDOT10k).
- Do redakcji: e-mail na redakcja@zyciepw.pl (poczta telefonu / udostępnianie ze zdjęciem). Nic nie trafia na nasz serwer.
- Ustawienia (gmina, ulica, zapisane artykuły, głosy) zostają w telefonie czytelnika (localStorage).

Po każdej zmianie `index.html` podnieś wersję w `sw.js` (const C).
