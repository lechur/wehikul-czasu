# Życie Powiatu – aplikacja dla czytelników (PWA)

Adres: https://www.zyciepw.pl/aplikacja/ (pliki wgrywa na serwer wtyczka `../wtyczki/zycie-aplikacja`, do `ABSPATH/aplikacja/`)

Analityka: GA4 portalu (G-GL4YCNRT2Y), content_group = aplikacja, tylko pod adresem zyciepw.pl. Zdarzenia: page_view (ekrany i artykuły), app_artykul, app_glos, app_dzik, app_do_redakcji, app_gmina, app_zapis, app_instalacja, search, share. Właściwości użytkownika: app_tryb, app_gmina. Reklam brak.

Wzorowana na Aplikacji mieszkańca Kobyłki (`../kobylka`). Wszystkie dane pobiera na żywo z zyciepw.pl – nie wymaga cotygodniowej aktualizacji plików.

- Wiadomości, wyszukiwarka, czytnik: `wp-json/wp/v2/posts` (kategorie gmin w tablicy `G` w `index.html`, pasek „Ważne” = tag 41864).
- Pytanie dnia: `wp-json/zr/v1/pytanie` (GET i POST głosu).
- Twoja okolica: `zr/v1/ulica`, `zr/v1/odpady` (odpady pojawią się w kolejnych gminach, gdy portal doda harmonogramy).
- Powietrze: `zr/v1/powietrze` (GIOŚ), pogoda: Open-Meteo.
- Mapa dzików: `mieszkaniec-api/?zasob=dziki` (odczyt i zgłoszenia), granice gmin: `gminy.json` (BDOT10k).
- Do redakcji: e-mail na redakcja@zyciepw.pl (poczta telefonu / udostępnianie ze zdjęciem). Nic nie trafia na nasz serwer.
- Ustawienia (gmina, ulica, zapisane artykuły, głosy) zostają w telefonie czytelnika (localStorage).

Po każdej zmianie `index.html` podnieś wersję w `sw.js` (const C).
