<?php
/**
 * Mini-serwer aplikacji mieszkańca (obserwacje dzików, powiat wołomiński).
 * Samodzielny: nie korzysta z WordPressa ani z bazy danych. Dane leżą w pliku data/dziki.json.
 * Przenosiny na inny serwer = skopiowanie tego folderu.
 *
 * GET  ?zasob=dziki[&gmina=kobylka]                     lista obserwacji z ostatnich 14 dni
 * POST ?zasob=dziki   JSON {lat, lon, ile, co, kiedy, zrodlo}   nowa obserwacja (anonimowa)
 *      albo multipart/form-data z tymi polami i plikiem "plik" (zdjęcie lub film) – media czekają na zatwierdzenie
 * GET  ?zasob=dziki&plik=<nazwa>                         zatwierdzone zdjęcie/film (niezatwierdzone tylko z &klucz=)
 * POST ?zasob=dziki&akcja=usun|zatwierdz   JSON {id, klucz}   moderacja (klucz z config.php)
 *
 * ?zasob=zgloszenia – wspólna mapa zgłoszeń (dziury, awarie, oświetlenie…), opis w pliku zgloszenia.php
 */
const MAX_FOTO = 8388608;    // 8 MB
const MAX_FILM = 41943040;   // 40 MB
$MEDIA = array('image/jpeg' => array('foto', 'jpg'), 'image/png' => array('foto', 'png'), 'image/webp' => array('foto', 'webp'), 'video/mp4' => array('film', 'mp4'), 'video/quicktime' => array('film', 'mov'), 'video/webm' => array('film', 'webm'));
const DNI = 14;
const LIMIT_NA_GODZINE = 5;
$ILE = array('1 dzik', '2–5 dzików', 'więcej niż 5 dzików', 'locha z młodymi');
$CO = array('przechodzą', 'żerują, ryją', 'są przy śmietnikach', 'zachowują się agresywnie');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

function odp($kod, $dane) { http_response_code($kod); echo json_encode($dane, JSON_UNESCAPED_UNICODE); exit; }
function blad($kod, $msg) { odp($kod, array('message' => $msg)); }

$ZASOB = isset($_GET['zasob']) ? (string) $_GET['zasob'] : '';
if ($ZASOB !== 'dziki' && $ZASOB !== 'zgloszenia') blad(404, 'Nieznany zasób.');
$dir = __DIR__ . '/data';
if (!is_dir($dir) && !@mkdir($dir, 0755, true)) blad(500, 'Brak katalogu danych.');
$cfg = is_file(__DIR__ . '/config.php') ? (array) include __DIR__ . '/config.php' : array();
$gminy = json_decode((string) file_get_contents(__DIR__ . '/gminy.json'), true);
if (!is_array($gminy)) blad(500, 'Brak granic gmin.');

function gmina_punktu($gminy, $lat, $lon) {
    foreach ($gminy as $slug => $g) {
        $b = $g['b'];
        if ($lat < $b[0] || $lat > $b[2] || $lon < $b[1] || $lon > $b[3]) continue;
        foreach ($g['p'] as $ring) {
            $in = false; $n = count($ring);
            for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
                $yi = $ring[$i][0]; $xi = $ring[$i][1]; $yj = $ring[$j][0]; $xj = $ring[$j][1];
                if ((($yi > $lat) !== ($yj > $lat)) && ($lon < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi)) $in = !$in;
            }
            if ($in) return $slug;
        }
    }
    return null;
}

/** Otwiera plik JSON z blokadą; $zmiana(array) zwraca nową tablicę albo null (bez zapisu). */
function plik($sciezka, $zmiana = null) {
    $f = fopen($sciezka, 'c+');
    if (!$f) blad(500, 'Nie można otworzyć danych.');
    flock($f, $zmiana ? LOCK_EX : LOCK_SH);
    $txt = stream_get_contents($f);
    $dane = json_decode($txt === '' ? '[]' : $txt, true);
    if (!is_array($dane)) $dane = array();
    if ($zmiana) {
        $nowe = $zmiana($dane);
        if (is_array($nowe)) { ftruncate($f, 0); rewind($f); fwrite($f, json_encode(array_values($nowe), JSON_UNESCAPED_UNICODE)); fflush($f); $dane = $nowe; }
    }
    flock($f, LOCK_UN); fclose($f);
    return $dane;
}
function usun_plik($o) { if (!empty($o['plik']) && preg_match('/^[a-f0-9]{24}\.[a-z0-9]{3,4}$/', $o['plik'])) @unlink(__DIR__ . '/data/media/' . $o['plik']); }
function swieze($dane) { $od = time() - DNI * 86400; $n = array(); foreach ($dane as $o) { if (isset($o['kiedy']) && strtotime($o['kiedy']) >= $od) $n[] = $o; else usun_plik($o); } return $n; }
function ma_klucz($cfg, $k) { return !empty($cfg['klucz']) && is_string($k) && hash_equals((string) $cfg['klucz'], $k); }

/** Limit nadużyć: przez godzinę trzymany jest tylko skrót (hash) adresu IP, nie sam adres. */
function limit_ok($dir, $cfg, $rodzaj, $max) {
    $sol = isset($cfg['klucz']) ? $cfg['klucz'] : 'brak';
    $h = substr(hash('sha256', $rodzaj . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '') . $sol . gmdate('Y-m-d-H')), 0, 24);
    $godz = gmdate('Y-m-d-H'); $ok = true;
    plik($dir . '/limit.json', function ($d) use ($h, $godz, $max, &$ok) {
        $n = array();
        foreach ($d as $r) if (isset($r['g']) && $r['g'] === $godz) $n[] = $r;
        foreach ($n as &$r) if ($r['h'] === $h) { if ($r['n'] >= $max) { $ok = false; return null; } $r['n']++; return $n; }
        $n[] = array('h' => $h, 'g' => $godz, 'n' => 1);
        return $n;
    });
    return $ok;
}
/** Zapisuje przesłany plik po sprawdzeniu rzeczywistego typu. Zwraca [nazwa, rodzaj, błąd]. */
function zapisz_media($dir, $MEDIA, $tylko_foto) {
    if (empty($_FILES['plik']) || !is_array($_FILES['plik'])) return array('', '', '');
    $f = $_FILES['plik'];
    if ($f['error'] !== UPLOAD_ERR_OK) return array('', '', 'Plik był za duży albo przesyłanie się nie powiodło.');
    $mime = function_exists('finfo_open') ? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $f['tmp_name']) : '';
    if (!isset($MEDIA[$mime]) || ($tylko_foto && $MEDIA[$mime][0] !== 'foto')) return array('', '', 'Ten rodzaj pliku nie jest przyjmowany.');
    if ($f['size'] > ($MEDIA[$mime][0] === 'foto' ? MAX_FOTO : MAX_FILM)) return array('', '', 'Plik jest za duży.');
    if (!is_dir($dir . '/media')) @mkdir($dir . '/media', 0755, true);
    $nazwa = bin2hex(random_bytes(12)) . '.' . $MEDIA[$mime][1];
    if (!@move_uploaded_file($f['tmp_name'], $dir . '/media/' . $nazwa)) return array('', '', 'Nie udało się zapisać pliku.');
    return array($nazwa, $MEDIA[$mime][0], '');
}

if ($ZASOB === 'zgloszenia') { require __DIR__ . '/zgloszenia.php'; exit; }

$plik = $dir . '/dziki.json';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['plik'])) {
    $nazwa = (string) $_GET['plik'];
    if (!preg_match('/^[a-f0-9]{24}\.[a-z0-9]{3,4}$/', $nazwa)) blad(404, 'Brak pliku.');
    $mod = ma_klucz($cfg, isset($_GET['klucz']) ? $_GET['klucz'] : null); $jest = false;
    foreach (plik($plik) as $o) if (isset($o['plik']) && $o['plik'] === $nazwa && ($mod || (isset($o['media_ok']) && $o['media_ok']))) $jest = true;
    $sc = $dir . '/media/' . $nazwa;
    if (!$jest || !is_file($sc)) blad(404, 'Brak pliku.');
    $typy = array('jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'mp4' => 'video/mp4', 'mov' => 'video/quicktime', 'webm' => 'video/webm');
    $ext = pathinfo($nazwa, PATHINFO_EXTENSION);
    header('Content-Type: ' . (isset($typy[$ext]) ? $typy[$ext] : 'application/octet-stream'));
    header('Content-Length: ' . filesize($sc)); header('Cache-Control: ' . ($mod ? 'no-store' : 'public, max-age=86400')); header('Content-Disposition: inline');
    readfile($sc); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mod = ma_klucz($cfg, isset($_GET['klucz']) ? $_GET['klucz'] : null);
    $O = swieze(plik($plik));
    if (!$mod) foreach ($O as &$o) { if (!empty($o['plik']) && empty($o['media_ok'])) { unset($o['plik']); $o['media'] = ''; } unset($o['media_ok']); } unset($o);
    $g = isset($_GET['gmina']) ? preg_replace('/[^a-z]/', '', (string) $_GET['gmina']) : '';
    if ($g !== '' && isset($gminy[$g])) $O = array_values(array_filter($O, function ($o) use ($g) { return $o['gmina'] === $g; }));
    usort($O, function ($a, $b) { return strcmp($b['kiedy'], $a['kiedy']); });
    header('Cache-Control: ' . ($mod ? 'no-store' : 'public, max-age=60'));
    odp(200, array('dni' => DNI, 'stan' => gmdate('c'), 'obserwacje' => $O));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') blad(405, 'Niedozwolona metoda.');
$multipart = isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') === 0;
$p = $multipart ? $_POST : json_decode((string) file_get_contents('php://input'), true);
if (!is_array($p) || (!$p && !$_FILES)) blad(400, 'Brak danych. Plik mógł być za duży dla serwera.');

if (isset($_GET['akcja']) && $_GET['akcja'] === 'usun') {
    if (!ma_klucz($cfg, isset($p['klucz']) ? $p['klucz'] : null)) blad(403, 'Brak uprawnień.');
    $id = isset($p['id']) ? (string) $p['id'] : '';
    plik($plik, function ($d) use ($id) { $n = array(); foreach ($d as $o) { if ((string) $o['id'] === $id) usun_plik($o); else $n[] = $o; } return $n; });
    odp(200, array('ok' => true));
}
if (isset($_GET['akcja']) && $_GET['akcja'] === 'zatwierdz') {
    if (!ma_klucz($cfg, isset($p['klucz']) ? $p['klucz'] : null)) blad(403, 'Brak uprawnień.');
    $id = isset($p['id']) ? (string) $p['id'] : '';
    plik($plik, function ($d) use ($id) { foreach ($d as &$o) if ((string) $o['id'] === $id && !empty($o['plik'])) $o['media_ok'] = 1; return $d; });
    odp(200, array('ok' => true));
}

$lat = isset($p['lat']) ? (float) $p['lat'] : 0; $lon = isset($p['lon']) ? (float) $p['lon'] : 0;
$gm = gmina_punktu($gminy, $lat, $lon);
if ($gm === null) blad(422, 'Miejsce jest poza powiatem wołomińskim.');
$ile = isset($p['ile']) ? (string) $p['ile'] : ''; $co = isset($p['co']) ? (string) $p['co'] : '';
if (!in_array($ile, $ILE, true) || !in_array($co, $CO, true)) blad(422, 'Nieznana wartość.');
$ts = isset($p['kiedy']) ? strtotime((string) $p['kiedy']) : time();
if (!$ts || $ts > time() + 600 || $ts < time() - 3 * 86400) $ts = time();
$zrodlo = isset($p['zrodlo']) ? substr(preg_replace('/[^a-z0-9\-]/', '', strtolower((string) $p['zrodlo'])), 0, 30) : '';

if (!limit_ok($dir, $cfg, '', LIMIT_NA_GODZINE)) blad(429, 'Za dużo zgłoszeń. Spróbuj za godzinę.');

$nowy = array('id' => gmdate('ymdHis') . bin2hex(random_bytes(3)), 'lat' => round($lat, 4), 'lon' => round($lon, 4), 'gmina' => $gm, 'ile' => $ile, 'co' => $co, 'kiedy' => gmdate('c', $ts), 'zrodlo' => $zrodlo, 'media' => '');
// Zdjęcie lub film: sprawdzany jest rzeczywisty typ pliku; media są widoczne publicznie dopiero po zatwierdzeniu przez moderatora.
list($nazwa, $rodzaj, $media_blad) = zapisz_media($dir, $MEDIA, false);
if ($nazwa !== '') { $nowy['plik'] = $nazwa; $nowy['media'] = $rodzaj; $nowy['media_ok'] = 0; }
plik($plik, function ($d) use ($nowy) { $d = swieze($d); $d[] = $nowy; return $d; });   // stare wpisy są przy okazji usuwane z pliku
odp(201, array('ok' => true, 'id' => $nowy['id'], 'gmina' => $gm, 'media' => $nowy['media'], 'media_blad' => $media_blad));
