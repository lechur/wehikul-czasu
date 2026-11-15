<?php
/**
 * Wspólna mapa zgłoszeń mieszkańców: dziury, awarie, oświetlenie, odpady…
 * Wczytywany z index.php (?zasob=zgloszenia), korzysta z jego funkcji. Dane: data/zgloszenia.json.
 *
 * Zgłoszenia tej samej kategorii w promieniu ok. 35 m łączą się w jedną sprawę z licznikiem.
 * Sprawa pamięta datę pierwszego i ostatniego zgłoszenia oraz ich liczbę.
 *
 * GET  ?zasob=zgloszenia[&gmina=kobylka][&status=otwarte|naprawione]   lista spraw
 * POST ?zasob=zgloszenia   JSON albo multipart {kat, lat, lon, opis?, zrodlo, u} [+ plik zdjęcia]  nowe zgłoszenie
 * POST ?zasob=zgloszenia&akcja=tez {id, u}        „też to widzę” (+1); u = losowy identyfikator telefonu
 * POST ?zasob=zgloszenia&akcja=naprawione {id, u}    mieszkaniec zgłasza naprawę; 2 niezależne głosy zamykają sprawę
 * POST ?zasob=zgloszenia&akcja=zatwierdz|usun|zamknij|otworz {id, klucz}   moderacja
 * GET  ?zasob=zgloszenia&plik=<nazwa>              zatwierdzone zdjęcie
 * Opis i zdjęcie są publiczne dopiero po zatwierdzeniu przez moderatora. Nie zapisujemy danych osobowych.
 */
if (!defined('MAX_FOTO')) exit;
const ZG_PROMIEN_M = 35;
const ZG_DNI_OTWARTE = 365;     // sprawa bez nowych zgłoszeń znika po roku
const ZG_DNI_NAPRAWIONE = 90;   // naprawiona znika po 90 dniach
const ZG_GLOSY_NAPRAWY = 2;
const ZG_LIMIT_ZGL = 8;         // zgłoszeń na godzinę z jednego adresu
const ZG_LIMIT_GLOS = 30;       // głosów na godzinę
$KAT = array('droga' => 'Dziura w drodze lub chodniku', 'oswietlenie' => 'Nie świeci latarnia', 'woda' => 'Woda, kanalizacja, zalana ulica', 'odpady' => 'Śmieci, dzikie wysypisko', 'zielen' => 'Zieleń, drzewa', 'bezpieczenstwo' => 'Znak, przejście, bezpieczeństwo ruchu', 'potrzeba' => 'Potrzebna inwestycja', 'inne' => 'Inna sprawa');
$zp = $dir . '/zgloszenia.json';

function zg_odl($a, $b, $c, $d) { $k = 111320; return sqrt(pow(($a - $c) * $k, 2) + pow(($b - $d) * $k * cos(deg2rad($a)), 2)); }
function zg_porzadek($d) {
    $n = array(); $t = time();
    foreach ($d as $s) {
        $wiek = $s['status'] === 'naprawione' ? $t - strtotime($s['naprawione_od']) : $t - strtotime($s['ostatnie']);
        if ($wiek <= ($s['status'] === 'naprawione' ? ZG_DNI_NAPRAWIONE : ZG_DNI_OTWARTE) * 86400) $n[] = $s; else usun_plik($s);
    }
    return $n;
}
/** Skrót osoby: pozwala nie liczyć dwa razy tego samego głosu. Bierzemy losowy identyfikator telefonu
 *  (aplikacja tworzy go sama, bez danych osobowych), a gdy go brak – adres IP z danego dnia. Zapisujemy tylko skrót. */
function zg_osoba($cfg, $p) {
    $u = isset($p['u']) ? preg_replace('/[^a-zA-Z0-9]/', '', (string) $p['u']) : '';
    $baza = strlen($u) >= 12 ? 'u' . substr($u, 0, 40) : 'ip' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '') . gmdate('Y-m-d');
    return substr(hash('sha256', 'zg' . $baza . (isset($cfg['klucz']) ? $cfg['klucz'] : '')), 0, 16);
}
function zg_publiczne($s, $mod) {
    if (!$mod) {
        if (empty($s['opis_ok'])) $s['opis'] = '';
        if (!empty($s['plik']) && empty($s['media_ok'])) $s['plik'] = '';
        unset($s['os'], $s['osn'], $s['opis_ok'], $s['media_ok']);
    }
    return $s;
}

$mod = ma_klucz($cfg, isset($_GET['klucz']) ? $_GET['klucz'] : null);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['plik'])) {
    $nazwa = (string) $_GET['plik'];
    if (!preg_match('/^[a-f0-9]{24}\.[a-z0-9]{3,4}$/', $nazwa)) blad(404, 'Brak pliku.');
    $jest = false;
    foreach (plik($zp) as $s) if (!empty($s['plik']) && $s['plik'] === $nazwa && ($mod || !empty($s['media_ok']))) $jest = true;
    $sc = $dir . '/media/' . $nazwa;
    if (!$jest || !is_file($sc)) blad(404, 'Brak pliku.');
    $typy = array('jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp');
    $ext = pathinfo($nazwa, PATHINFO_EXTENSION);
    header('Content-Type: ' . (isset($typy[$ext]) ? $typy[$ext] : 'application/octet-stream'));
    header('Content-Length: ' . filesize($sc)); header('Cache-Control: ' . ($mod ? 'no-store' : 'public, max-age=86400'));
    readfile($sc); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $S = zg_porzadek(plik($zp));
    $g = isset($_GET['gmina']) ? preg_replace('/[^a-z]/', '', (string) $_GET['gmina']) : '';
    $st = isset($_GET['status']) ? (string) $_GET['status'] : '';
    $out = array();
    foreach ($S as $s) {
        if ($g !== '' && $s['gmina'] !== $g) continue;
        if ($st !== '' && $s['status'] !== $st) continue;
        $out[] = zg_publiczne($s, $mod);
    }
    usort($out, function ($a, $b) { return strcmp($a['pierwsze'], $b['pierwsze']); });
    header('Cache-Control: ' . ($mod ? 'no-store' : 'public, max-age=60'));
    odp(200, array('stan' => gmdate('c'), 'kategorie' => $KAT, 'sprawy' => $out));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') blad(405, 'Niedozwolona metoda.');
$multipart = isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') === 0;
$p = $multipart ? $_POST : json_decode((string) file_get_contents('php://input'), true);
if (!is_array($p) || (!$p && !$_FILES)) blad(400, 'Brak danych. Plik mógł być za duży dla serwera.');
$akcja = isset($_GET['akcja']) ? (string) $_GET['akcja'] : '';
$id = isset($p['id']) ? (string) $p['id'] : '';

if (in_array($akcja, array('zatwierdz', 'usun', 'zamknij', 'otworz'), true)) {
    if (!ma_klucz($cfg, isset($p['klucz']) ? $p['klucz'] : null)) blad(403, 'Brak uprawnień.');
    plik($zp, function ($d) use ($id, $akcja) {
        $n = array();
        foreach ($d as $s) {
            if ((string) $s['id'] !== $id) { $n[] = $s; continue; }
            if ($akcja === 'usun') { usun_plik($s); continue; }
            if ($akcja === 'zatwierdz') { $s['opis_ok'] = 1; if (!empty($s['plik'])) $s['media_ok'] = 1; }
            if ($akcja === 'zamknij') { $s['status'] = 'naprawione'; $s['naprawione_od'] = gmdate('c'); }
            if ($akcja === 'otworz') { $s['status'] = 'otwarte'; $s['naprawione_od'] = null; $s['naprawione_glosy'] = 0; $s['osn'] = array(); }
            $n[] = $s;
        }
        return $n;
    });
    odp(200, array('ok' => true));
}

if ($akcja === 'tez' || $akcja === 'naprawione') {
    if (!limit_ok($dir, $cfg, 'g', ZG_LIMIT_GLOS)) blad(429, 'Za dużo głosów. Spróbuj za godzinę.');
    $os = zg_osoba($cfg, $p); $wynik = null; $juz = false;
    plik($zp, function ($d) use ($id, $akcja, $os, &$wynik, &$juz) {
        foreach ($d as &$s) {
            if ((string) $s['id'] !== $id) continue;
            if ($akcja === 'tez') {
                if (in_array($os, $s['os'], true)) { $juz = true; $wynik = $s; return null; }
                $s['os'][] = $os; $s['os'] = array_slice($s['os'], -300); $s['n']++; $s['ostatnie'] = gmdate('c');
                if ($s['status'] === 'naprawione') { $s['status'] = 'otwarte'; $s['naprawione_od'] = null; $s['naprawione_glosy'] = 0; $s['osn'] = array(); }
            } else {
                if ($s['status'] === 'naprawione' || in_array($os, $s['osn'], true)) { $juz = true; $wynik = $s; return null; }
                $s['osn'][] = $os; $s['naprawione_glosy']++;
                if ($s['naprawione_glosy'] >= ZG_GLOSY_NAPRAWY) { $s['status'] = 'naprawione'; $s['naprawione_od'] = gmdate('c'); }
            }
            $wynik = $s; return $d;
        }
        return null;
    });
    if (!$wynik) blad(404, 'Nie ma takiego zgłoszenia.');
    odp(200, array('ok' => true, 'juz' => $juz, 'sprawa' => zg_publiczne($wynik, false)));
}

if ($akcja !== '') blad(400, 'Nieznana akcja.');
$kat = isset($p['kat']) ? (string) $p['kat'] : '';
if (!isset($KAT[$kat])) blad(422, 'Nieznana kategoria.');
$lat = isset($p['lat']) ? (float) $p['lat'] : 0; $lon = isset($p['lon']) ? (float) $p['lon'] : 0;
$gm = gmina_punktu($gminy, $lat, $lon);
if ($gm === null) blad(422, 'Miejsce jest poza powiatem wołomińskim.');
$opis = isset($p['opis']) ? trim(preg_replace('/\s+/u', ' ', strip_tags((string) $p['opis']))) : '';
$opis = mb_substr($opis, 0, 160);
$zrodlo = isset($p['zrodlo']) ? substr(preg_replace('/[^a-z0-9\-]/', '', strtolower((string) $p['zrodlo'])), 0, 30) : '';
$imp = ma_klucz($cfg, isset($p['klucz']) ? $p['klucz'] : null);   // import przez moderatora: data zdarzenia, opis od razu jawny, bez limitu
$kiedy = gmdate('c');
if ($imp && isset($p['kiedy'])) { $t = strtotime((string) $p['kiedy']); if ($t && $t <= time() + 600 && $t >= time() - ZG_DNI_OTWARTE * 86400) $kiedy = gmdate('c', $t); }
if (!$imp && !limit_ok($dir, $cfg, 'z', ZG_LIMIT_ZGL)) blad(429, 'Za dużo zgłoszeń. Spróbuj za godzinę.');
list($nazwa, $rodzaj, $media_blad) = zapisz_media($dir, $MEDIA, true);
$os = zg_osoba($cfg, $p); $wynik = null; $dolaczono = false;
plik($zp, function ($d) use ($kat, $lat, $lon, $gm, $opis, $zrodlo, $nazwa, $os, $imp, $kiedy, &$wynik, &$dolaczono) {
    $d = zg_porzadek($d); $best = null; $bd = ZG_PROMIEN_M;
    foreach ($d as $i => $s) { if ($s['kat'] !== $kat || $s['status'] !== 'otwarte') continue; $o = zg_odl($lat, $lon, $s['lat'], $s['lon']); if ($o <= $bd) { $bd = $o; $best = $i; } }
    if ($best !== null) {
        $s = &$d[$best]; $dolaczono = true;
        if (!in_array($os, $s['os'], true)) { $s['os'][] = $os; $s['n']++; }
        $s['ostatnie'] = gmdate('c');
        if ($opis !== '' && $s['opis'] === '') { $s['opis'] = $opis; $s['opis_ok'] = $imp ? 1 : 0; }
        if ($nazwa !== '' && empty($s['plik'])) { $s['plik'] = $nazwa; $s['media_ok'] = 0; } else if ($nazwa !== '') @unlink(__DIR__ . '/data/media/' . $nazwa);
        if ($zrodlo !== '' && !in_array($zrodlo, $s['zrodla'], true)) $s['zrodla'][] = $zrodlo;
        $wynik = $s; return $d;
    }
    $s = array('id' => 'z' . gmdate('ymdHis') . bin2hex(random_bytes(2)), 'kat' => $kat, 'lat' => round($lat, 5), 'lon' => round($lon, 5), 'gmina' => $gm,
        'pierwsze' => $kiedy, 'ostatnie' => $kiedy, 'n' => 1, 'status' => 'otwarte', 'naprawione_od' => null, 'naprawione_glosy' => 0,
        'opis' => $opis, 'opis_ok' => $imp ? 1 : 0, 'plik' => $nazwa, 'media_ok' => 0, 'zrodla' => $zrodlo !== '' ? array($zrodlo) : array(), 'os' => array($os), 'osn' => array());
    $d[] = $s; $wynik = $s; return $d;
});
odp(201, array('ok' => true, 'dolaczono' => $dolaczono, 'media_blad' => $media_blad, 'sprawa' => zg_publiczne($wynik, false)));
