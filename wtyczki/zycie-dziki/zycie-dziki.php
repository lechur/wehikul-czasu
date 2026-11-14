<?php
/**
 * Plugin Name: Życie – Mapa dzików
 * Description: Wspólna mapa obserwacji dzików w powiecie wołomińskim: mapa każdej gminy i całego powiatu. Shortcode [zycie_dziki_mapa] albo [zycie_dziki_mapa gmina="kobylka"]. Przyjmuje anonimowe zgłoszenia ze strony i z Aplikacji mieszkańca Kobyłki. Bez danych osobowych; wpisy znikają po 14 dniach.
 * Version: 1.1.0
 * Author: Życie Powiatu
 */
if (!defined('ABSPATH')) exit;

const ZPW_DZIKI_DNI = 14;          // jak długo obserwacja jest widoczna
const ZPW_DZIKI_LIMIT = 5;         // zgłoszeń na godzinę z jednego urządzenia
const ZPW_DZIKI_ORIGINS = array('https://lechur.github.io', 'https://www.zyciepw.pl', 'https://zyciepw.pl');

function zpw_dziki_table() { global $wpdb; return $wpdb->prefix . 'zycie_dziki'; }
function zpw_dziki_ile() { return array('1 dzik', '2–5 dzików', 'więcej niż 5 dzików', 'locha z młodymi'); }
function zpw_dziki_co() { return array('przechodzą', 'żerują, ryją', 'są przy śmietnikach', 'zachowują się agresywnie'); }

function zpw_dziki_gminy() {
    static $g = null;
    if ($g === null) { $g = json_decode((string) file_get_contents(__DIR__ . '/gminy.json'), true); if (!is_array($g)) $g = array(); }
    return $g;
}
// W której gminie powiatu leży punkt (granice z BDOT10k, GUGiK). Null = poza powiatem.
function zpw_dziki_gmina($lat, $lon) {
    foreach (zpw_dziki_gminy() as $slug => $g) {
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

register_activation_hook(__FILE__, function () {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $t = zpw_dziki_table();
    dbDelta("CREATE TABLE $t (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        lat decimal(7,4) NOT NULL,
        lon decimal(7,4) NOT NULL,
        gmina varchar(20) NOT NULL DEFAULT '',
        ile varchar(40) NOT NULL,
        co varchar(40) NOT NULL,
        kiedy datetime NOT NULL,
        dodano datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY kiedy (kiedy),
        KEY gmina (gmina)
    ) " . $wpdb->get_charset_collate() . ";");
    if (!wp_next_scheduled('zpw_dziki_sprzatanie')) wp_schedule_event(time() + 3600, 'daily', 'zpw_dziki_sprzatanie');
});
register_deactivation_hook(__FILE__, function () { wp_clear_scheduled_hook('zpw_dziki_sprzatanie'); });

// Stare obserwacje są usuwane z bazy, nie tylko ukrywane.
add_action('zpw_dziki_sprzatanie', function () {
    global $wpdb;
    $wpdb->query($wpdb->prepare('DELETE FROM ' . zpw_dziki_table() . ' WHERE kiedy < %s', gmdate('Y-m-d H:i:s', time() - (ZPW_DZIKI_DNI + 1) * DAY_IN_SECONDS)));
});

function zpw_dziki_cors() {
    $o = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    if (in_array($o, ZPW_DZIKI_ORIGINS, true)) {
        header('Access-Control-Allow-Origin: ' . $o);
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
    }
}

function zpw_dziki_lista($gmina = '') {
    global $wpdb;
    $od = gmdate('Y-m-d H:i:s', time() - ZPW_DZIKI_DNI * DAY_IN_SECONDS);
    if ($gmina !== '' && isset(zpw_dziki_gminy()[$gmina])) $rows = $wpdb->get_results($wpdb->prepare('SELECT id, lat, lon, gmina, ile, co, kiedy FROM ' . zpw_dziki_table() . ' WHERE kiedy >= %s AND gmina = %s ORDER BY kiedy DESC LIMIT 1000', $od, $gmina), ARRAY_A);
    else $rows = $wpdb->get_results($wpdb->prepare('SELECT id, lat, lon, gmina, ile, co, kiedy FROM ' . zpw_dziki_table() . ' WHERE kiedy >= %s ORDER BY kiedy DESC LIMIT 2000', $od), ARRAY_A);
    $out = array();
    foreach ((array) $rows as $r) {
        $out[] = array('id' => (int) $r['id'], 'lat' => (float) $r['lat'], 'lon' => (float) $r['lon'], 'gmina' => $r['gmina'], 'ile' => $r['ile'], 'co' => $r['co'], 'kiedy' => gmdate('c', strtotime($r['kiedy'] . ' UTC')));
    }
    return $out;
}

add_action('rest_api_init', function () {
    add_filter('rest_pre_serve_request', function ($served, $result, $request) {
        if (strpos($request->get_route(), '/zycie-dziki/') === 0) zpw_dziki_cors();
        return $served;
    }, 10, 3);

    register_rest_route('zycie-dziki/v1', '/obserwacje', array(
        array(
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => function (WP_REST_Request $req) {
                $r = new WP_REST_Response(array('dni' => ZPW_DZIKI_DNI, 'stan' => gmdate('c'), 'obserwacje' => zpw_dziki_lista(sanitize_key((string) $req->get_param('gmina')))));
                $r->header('Cache-Control', 'public, max-age=60');
                return $r;
            },
        ),
        array(
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => function (WP_REST_Request $req) {
                global $wpdb;
                $p = $req->get_json_params();
                if (!is_array($p)) return new WP_Error('zle_dane', 'Brak danych.', array('status' => 400));
                $lat = isset($p['lat']) ? (float) $p['lat'] : 0; $lon = isset($p['lon']) ? (float) $p['lon'] : 0;
                $gmina = zpw_dziki_gmina($lat, $lon);
                if ($gmina === null) return new WP_Error('poza_powiatem', 'Miejsce jest poza powiatem wołomińskim.', array('status' => 422));
                $ile = isset($p['ile']) ? (string) $p['ile'] : ''; $co = isset($p['co']) ? (string) $p['co'] : '';
                if (!in_array($ile, zpw_dziki_ile(), true) || !in_array($co, zpw_dziki_co(), true)) return new WP_Error('zle_dane', 'Nieznana wartość.', array('status' => 422));
                $ts = isset($p['kiedy']) ? strtotime((string) $p['kiedy']) : time();
                if (!$ts || $ts > time() + 600 || $ts < time() - 3 * DAY_IN_SECONDS) $ts = time();
                // Ograniczenie nadużyć: przez godzinę trzymany jest tylko skrót (hash) adresu, nie sam adres IP.
                $klucz = 'zpw_dz_' . substr(hash('sha256', (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '') . wp_salt('nonce') . gmdate('Y-m-d-H')), 0, 24);
                $n = (int) get_transient($klucz);
                if ($n >= ZPW_DZIKI_LIMIT) return new WP_Error('za_duzo', 'Za dużo zgłoszeń. Spróbuj za godzinę.', array('status' => 429));
                set_transient($klucz, $n + 1, HOUR_IN_SECONDS);
                // Miejsce zapisywane z dokładnością ok. 10 m (pinezka stawiana na mapie).
                $ok = $wpdb->insert(zpw_dziki_table(), array('lat' => round($lat, 4), 'lon' => round($lon, 4), 'gmina' => $gmina, 'ile' => $ile, 'co' => $co, 'kiedy' => gmdate('Y-m-d H:i:s', $ts), 'dodano' => gmdate('Y-m-d H:i:s')), array('%f', '%f', '%s', '%s', '%s', '%s', '%s'));
                if (!$ok) return new WP_Error('blad', 'Nie udało się zapisać.', array('status' => 500));
                return new WP_REST_Response(array('ok' => true, 'id' => (int) $wpdb->insert_id, 'gmina' => $gmina), 201);
            },
        ),
    ));
});

// Moderacja: redaktorzy i administratorzy (uprawnienie moderate_comments).
add_action('admin_menu', function () {
    add_menu_page('Mapa dzików', 'Mapa dzików', 'moderate_comments', 'zycie-dziki', 'zpw_dziki_admin', 'dashicons-location-alt', 58);
});
function zpw_dziki_admin() {
    global $wpdb;
    if (!current_user_can('moderate_comments')) return;
    if (isset($_POST['zpw_dziki_usun']) && check_admin_referer('zpw_dziki_usun')) {
        $wpdb->delete(zpw_dziki_table(), array('id' => (int) $_POST['zpw_dziki_usun']), array('%d'));
        echo '<div class="notice notice-success"><p>Obserwacja usunięta.</p></div>';
    }
    $rows = zpw_dziki_lista();
    echo '<div class="wrap"><h1>Mapa dzików – obserwacje z ostatnich ' . (int) ZPW_DZIKI_DNI . ' dni</h1>';
    if (isset($_POST['zpw_dziki_strony']) && check_admin_referer('zpw_dziki_strony') && current_user_can('publish_pages')) { echo '<div class="notice notice-success"><p>' . esc_html(zpw_dziki_utworz_strony()) . '</p></div>'; }
    echo '<form method="post" style="margin:10px 0">'; wp_nonce_field('zpw_dziki_strony'); echo '<button class="button button-primary" name="zpw_dziki_strony" value="1">Utwórz strony map (powiat + 12 gmin)</button> <span class="description">Tworzy opublikowane strony /dziki/ i /dziki/&lt;gmina&gt;/. Istniejących nie zmienia.</span></form>';
    echo '<p>Mapa na stronie: shortcode <code>[zycie_dziki_mapa]</code> (powiat) albo <code>[zycie_dziki_mapa gmina="kobylka"]</code>. Wpisy starsze niż ' . (int) ZPW_DZIKI_DNI . ' dni są usuwane automatycznie. Zgłoszenia nie zawierają danych osobowych.</p>';
    echo '<table class="widefat striped"><thead><tr><th>Kiedy</th><th>Gmina</th><th>Miejsce</th><th>Ile</th><th>Zachowanie</th><th></th></tr></thead><tbody>';
    if (!$rows) echo '<tr><td colspan="6">Brak obserwacji.</td></tr>';
    foreach ($rows as $r) {
        echo '<tr><td>' . esc_html(wp_date('j.m.Y H:i', strtotime($r['kiedy']))) . '</td><td>' . esc_html(isset(zpw_dziki_gminy()[$r['gmina']]) ? zpw_dziki_gminy()[$r['gmina']]['n'] : $r['gmina']) . '</td><td><a target="_blank" rel="noopener" href="' . esc_url('https://www.google.com/maps?q=' . $r['lat'] . ',' . $r['lon']) . '">' . esc_html($r['lat'] . ', ' . $r['lon']) . '</a></td><td>' . esc_html($r['ile']) . '</td><td>' . esc_html($r['co']) . '</td><td><form method="post">';
        wp_nonce_field('zpw_dziki_usun');
        echo '<button class="button" name="zpw_dziki_usun" value="' . (int) $r['id'] . '" onclick="return confirm(\'Usunąć tę obserwację?\')">Usuń</button></form></td></tr>';
    }
    echo '</tbody></table></div>';
}

function zpw_dziki_utworz_strony() {
    $rodzic = get_page_by_path('dziki');
    $nowe = 0;
    if (!$rodzic) {
        $id = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'dziki', 'post_title' => 'Dziki w powiecie wołomińskim – mapa obserwacji', 'post_content' => '[zycie_dziki_mapa]'));
        if (!$id || is_wp_error($id)) return 'Nie udało się utworzyć strony głównej.';
        $nowe++; $rid = $id;
    } else $rid = $rodzic->ID;
    foreach (zpw_dziki_gminy() as $slug => $g) {
        if (get_page_by_path('dziki/' . $slug)) continue;
        $id = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_parent' => $rid, 'post_name' => $slug, 'post_title' => 'Dziki: ' . $g['n'] . ' – mapa obserwacji', 'post_content' => '[zycie_dziki_mapa gmina="' . $slug . '"]'));
        if ($id && !is_wp_error($id)) $nowe++;
    }
    return 'Utworzono stron: ' . $nowe . '. Mapa powiatu: ' . home_url('/dziki/');
}

// Mapa na stronie: [zycie_dziki_mapa] (powiat) albo [zycie_dziki_mapa gmina="kobylka"]
add_shortcode('zycie_dziki_mapa', function ($atts) {
    $atts = shortcode_atts(array('gmina' => ''), $atts);
    $gm = sanitize_key($atts['gmina']);
    $G = zpw_dziki_gminy();
    if ($gm !== '' && !isset($G[$gm])) $gm = '';
    wp_enqueue_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4');
    wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);
    $linki = array();
    foreach ($G as $slug => $g) $linki[$slug] = home_url('/dziki/' . $slug . '/');
    $cfg = wp_json_encode(array('api' => esc_url_raw(rest_url('zycie-dziki/v1/obserwacje')), 'ile' => zpw_dziki_ile(), 'co' => zpw_dziki_co(), 'dni' => ZPW_DZIKI_DNI, 'gmina' => $gm, 'gminy' => $G, 'linki' => $linki, 'powiat' => home_url('/dziki/')));
    ob_start(); ?>
<style>
.zdz{max-width:980px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;color:#14213d}
.zdz *{box-sizing:border-box}
.zdz-nav{display:flex;gap:8px;overflow-x:auto;padding:2px 0 10px;scrollbar-width:thin}
.zdz-nav a{flex:none;padding:9px 14px;border-radius:999px;border:1px solid #cbd2dc;background:#fff;color:#14213d;text-decoration:none;font-size:15px;font-weight:600;white-space:nowrap}
.zdz-nav a.on{background:#14315a;border-color:#14315a;color:#fff}
.zdz-sum{font-size:18px;font-weight:700;margin:4px 0 10px}
.zdz-wrap{position:relative;border-radius:16px;overflow:hidden;border:1px solid #d8dee8;background:#eef1f5}
.zdz-map{height:min(68vh,620px);min-height:380px}
.zdz-wrap.full{position:fixed;inset:0;z-index:99999;border-radius:0;border:0}.zdz-wrap.full .zdz-map{height:100%}
.zdz-tools{position:absolute;top:10px;right:10px;z-index:1000;display:flex;flex-direction:column;gap:8px}
.zdz-tools button{width:46px;height:46px;border-radius:12px;border:0;background:#fff;box-shadow:0 2px 8px rgba(20,33,61,.25);font-size:20px;cursor:pointer;color:#14213d}
.zdz-hint[hidden]{display:none}
.zdz-hint{position:absolute;left:50%;top:10px;transform:translateX(-50%);z-index:1000;background:rgba(20,33,61,.92);color:#fff;font-size:14px;font-weight:600;padding:8px 14px;border-radius:999px;white-space:nowrap;max-width:calc(100% - 130px);overflow:hidden;text-overflow:ellipsis;pointer-events:none}
.zdz-cta{position:absolute;left:12px;right:12px;bottom:14px;z-index:1000;display:flex;justify-content:center;pointer-events:none}
.zdz-cta button{pointer-events:auto;border:0;border-radius:14px;background:#0e7c6b;color:#fff;font-size:17px;font-weight:700;padding:14px 22px;box-shadow:0 6px 18px rgba(14,124,107,.4);cursor:pointer}
.zdz-sheet{position:absolute;left:0;right:0;bottom:0;z-index:1001;background:#fff;border-radius:18px 18px 0 0;box-shadow:0 -8px 30px rgba(20,33,61,.25);padding:14px 16px 16px;display:none}
.zdz-sheet b.t{display:block;font-size:17px;margin-bottom:2px}.zdz-sheet .s{font-size:13.5px;color:#5b6577;margin-bottom:8px}
.zdz-seg{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin:4px 0 10px}
.zdz-seg button{border:1px solid #cbd2dc;background:#fff;border-radius:10px;padding:10px 2px;font-size:14px;font-weight:600;color:#14213d;cursor:pointer}
.zdz-seg button.on{background:#14315a;border-color:#14315a;color:#fff}
.zdz-lab{font-size:13px;font-weight:700;color:#5b6577;text-transform:uppercase;letter-spacing:.05em}
.zdz-row{display:flex;gap:8px}.zdz-row button{flex:1;border-radius:12px;padding:13px;font-size:16px;font-weight:700;cursor:pointer}
.zdz-ok{border:0;background:#0e7c6b;color:#fff}.zdz-no{border:1px solid #cbd2dc;background:#fff;color:#14213d}
.zdz-leg{display:flex;flex-wrap:wrap;gap:14px;font-size:14px;color:#5b6577;margin:10px 0}.zdz-leg i{display:inline-block;width:14px;height:14px;border-radius:50%;margin-right:6px;vertical-align:-2px;border:2px solid #fff;box-shadow:0 0 0 1px #9aa3b2}
.zdz-note{font-size:14px;color:#5b6577;line-height:1.5}
.zdz-warn{background:#fff7e0;border-radius:14px;padding:12px 16px;margin:12px 0;font-size:15px}
.zdz-pin{width:34px;height:44px;margin:-44px 0 0 -17px}
.zdz .leaflet-control-zoom a{width:44px;height:44px;line-height:44px;font-size:24px}
.zdz .leaflet-popup-content{font-size:15px;line-height:1.4}
.zdz-label{background:none;border:0;box-shadow:none;font-weight:700;font-size:13px;color:#14315a;text-shadow:0 0 3px #fff,0 0 3px #fff,0 0 3px #fff}
</style>
<div class="zdz">
  <nav class="zdz-nav" id="zdz-nav" aria-label="Gminy"></nav>
  <p class="zdz-sum" id="zdz-sum">Wczytuję obserwacje…</p>
  <div class="zdz-wrap" id="zdz-wrap">
    <div class="zdz-map" id="zdz-map"></div>
    <div class="zdz-hint" id="zdz-hint" hidden></div>
    <div class="zdz-tools"><button type="button" id="zdz-full" title="Pełny ekran" aria-label="Pełny ekran">⛶</button><button type="button" id="zdz-loc" title="Pokaż moje położenie" aria-label="Pokaż moje położenie">◎</button></div>
    <div class="zdz-cta" id="zdz-cta"><button type="button" id="zdz-start">Widzę dziki – dodaj obserwację</button></div>
    <div class="zdz-sheet" id="zdz-sheet">
      <div id="zdz-k1">
        <div class="s" style="margin:0 0 8px;color:#14213d;font-weight:600">Pinezka w dobrym miejscu? Przesuń ją palcem albo dotknij mapy ponownie.</div>
        <div class="zdz-row"><button type="button" class="zdz-no" id="zdz-cancel">Anuluj</button><button type="button" class="zdz-ok" id="zdz-next">Tak, dalej</button></div>
      </div>
      <div id="zdz-k2" hidden>
        <div class="zdz-lab">Ile?</div><div class="zdz-seg" id="zdz-ile"></div>
        <div class="zdz-lab">Co robią?</div><div class="zdz-seg" id="zdz-co"></div>
        <div class="zdz-row"><button type="button" class="zdz-no" id="zdz-back">Popraw miejsce</button><button type="button" class="zdz-ok" id="zdz-add">Dodaj obserwację</button></div>
        <div class="s" id="zdz-msg" style="margin:8px 0 0"></div>
      </div>
    </div>
  </div>
  <div class="zdz-leg"><span><i style="background:#d21f1f"></i>dziś i wczoraj</span><span><i style="background:#f08a24"></i>2–7 dni temu</span><span><i style="background:#9aa3b2"></i>8–<?php echo (int) ZPW_DZIKI_DNI; ?> dni temu</span></div>
  <div class="zdz-warn"><b>Dziki blisko ludzi, ranne albo agresywne?</b> Nie podchodź. Zadzwoń pod 112 albo do swojego urzędu gminy.</div>
  <p class="zdz-note">Zgłoszenia mieszkańców, niezweryfikowane. Dodając obserwację, nie podajesz żadnych danych o sobie: zapisujemy tylko miejsce, czas, liczbę i zachowanie dzików. Punkty znikają po <?php echo (int) ZPW_DZIKI_DNI; ?> dniach. Granice gmin: BDOT10k (GUGiK). Mieszkasz w Kobyłce? <a href="https://lechur.github.io/wehikul-czasu/kobylka/#dziki">Zgłaszaj w Aplikacji mieszkańca Kobyłki</a>.</p>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var C = <?php echo $cfg; ?>, G = C.gminy, cur = C.gmina && G[C.gmina] ? G[C.gmina] : null;
  var $ = function (id) { return document.getElementById(id); };
  function esc(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
  // nawigacja po gminach
  var nav = $('zdz-nav'), h = '<a href="' + C.powiat + '"' + (cur ? '' : ' class="on"') + '>Cały powiat</a>';
  Object.keys(G).forEach(function (s) { h += '<a href="' + C.linki[s] + '"' + (s === C.gmina ? ' class="on"' : '') + '>' + esc(G[s].n) + '</a>'; });
  nav.innerHTML = h; var on = nav.querySelector('.on'); if (on && on.scrollIntoView) on.scrollIntoView({inline: 'center', block: 'nearest'});
  // mapa
  var all = [52.2562, 21.0646, 52.5561, 21.738], b = cur ? cur.b : all, bounds = L.latLngBounds([b[0], b[1]], [b[2], b[3]]);
  var map = L.map('zdz-map', {scrollWheelZoom: false, zoomSnap: 0.5, maxBounds: L.latLngBounds([all[0] - 0.08, all[1] - 0.15], [all[2] + 0.08, all[3] + 0.15]), minZoom: 10});
  map.fitBounds(bounds, {padding: [10, 10]});
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'}).addTo(map);
  map.on('focus click', function () { map.scrollWheelZoom.enable(); });
  // granice gmin
  Object.keys(G).forEach(function (s) {
    var me = s === C.gmina, poly = L.polygon(G[s].p, {color: '#14315a', weight: me ? 3 : 1.5, opacity: me ? 0.9 : 0.55, fill: !!cur && !me, fillColor: '#14213d', fillOpacity: 0.12, interactive: false}).addTo(map);
    if (!cur) L.marker(poly.getBounds().getCenter(), {interactive: false, icon: L.divIcon({className: 'zdz-label', html: esc(G[s].n), iconSize: [120, 18]})}).addTo(map);
  });
  // obserwacje
  var layer = L.layerGroup().addTo(map);
  function kolor(w) { return w < 2 ? '#d21f1f' : w <= 7 ? '#f08a24' : '#9aa3b2'; }
  function kiedy(d) { return d.toLocaleString('pl-PL', {weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit'}); }
  function load() {
    fetch(C.api + (C.gmina ? '?gmina=' + C.gmina : ''), {cache: 'no-store'}).then(function (r) { return r.json(); }).then(function (d) {
      layer.clearLayers(); var now = Date.now(), tydz = 0, O = d.obserwacje || [];
      O.slice().reverse().forEach(function (o) {
        var t = new Date(o.kiedy), w = (now - t.getTime()) / 864e5; if (w <= 7) tydz++;
        L.circleMarker([o.lat, o.lon], {radius: w < 2 ? 11 : 9, color: '#fff', weight: 2.5, fillColor: kolor(w), fillOpacity: 0.95})
          .bindPopup('<b>' + esc(o.ile) + '</b>, ' + esc(o.co) + '<br>' + kiedy(t) + (G[o.gmina] ? '<br>' + esc(G[o.gmina].n) : '')).addTo(layer);
      });
      var gdzie = cur ? cur.n : 'powiat wołomiński';
      $('zdz-sum').textContent = O.length ? gdzie + ': ' + tydz + ' ' + pl(tydz, 'obserwacja', 'obserwacje', 'obserwacji') + ' dzików w ostatnich 7 dniach, ' + O.length + ' w ostatnich ' + C.dni + ' dniach' : gdzie + ': w ostatnich ' + C.dni + ' dniach nikt nie zgłosił dzików';
    }).catch(function () { $('zdz-sum').textContent = 'Nie udało się wczytać obserwacji.'; });
  }
  // dodawanie: pinezka stawiana dotknięciem, przesuwana, z automatycznym powiększeniem
  var pin = null, adding = false, sel = {ile: C.ile[1], co: C.co[0]};
  var ikona = L.divIcon({className: '', html: '<svg class="zdz-pin" viewBox="0 0 34 44"><path d="M17 43C17 43 2 27 2 16a15 15 0 0 1 30 0c0 11-15 27-15 27z" fill="#0e7c6b" stroke="#fff" stroke-width="2.5"/><circle cx="17" cy="16" r="6" fill="#fff"/></svg>', iconSize: [0, 0]});
  function seg(id, arr, key, kr) { var el = $(id); el.innerHTML = arr.map(function (v, i) { return '<button type="button" data-v="' + esc(v) + '"' + (v === sel[key] ? ' class="on"' : '') + '>' + esc(kr[i]) + '</button>'; }).join('');
    el.onclick = function (e) { var bt = e.target.closest('button'); if (!bt) return; sel[key] = bt.dataset.v; [].forEach.call(el.children, function (x) { x.classList.toggle('on', x === bt); }); }; }
  seg('zdz-ile', C.ile, 'ile', ['1', '2–5', 'ponad 5', 'z młodymi']); seg('zdz-co', C.co, 'co', ['przechodzą', 'ryją', 'śmietniki', 'agresywne']);
  function hint(t) { var e = $('zdz-hint'); e.hidden = !t; e.textContent = t || ''; }
  function krok(n) { $('zdz-k1').hidden = n !== 1; $('zdz-k2').hidden = n !== 2; }
  function pl(n, a, b, c) { return n === 1 ? a : (n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14)) ? b : c; }
  function stop() { adding = false; krok(1); if (pin) { map.removeLayer(pin); pin = null; } $('zdz-sheet').style.display = 'none'; $('zdz-cta').style.display = ''; hint(''); $('zdz-msg').textContent = ''; }
  function setPin(ll) {
    if (!pin) { pin = L.marker(ll, {icon: ikona, draggable: true, autoPan: true}).addTo(map); } else pin.setLatLng(ll);
    $('zdz-sheet').style.display = 'block'; krok(1);
    if (map.getZoom() < 17) { hint('Powiększam…'); map.flyTo(ll, 17.5, {duration: 0.6}); map.once('moveend', function () { hint(''); }); } else hint('');
  }
  $('zdz-start').onclick = function () { adding = true; $('zdz-cta').style.display = 'none'; hint('Dotknij mapy tam, gdzie są dziki'); map.scrollWheelZoom.enable(); };
  $('zdz-cancel').onclick = stop; $('zdz-next').onclick = function () { krok(2); hint(''); }; $('zdz-back').onclick = function () { krok(1); };
  map.on('click', function (e) { if (adding) setPin(e.latlng); });
  $('zdz-add').onclick = function () {
    var m = $('zdz-msg'); if (!pin) { m.textContent = 'Najpierw dotknij mapy.'; return; }
    var ll = pin.getLatLng(), bt = $('zdz-add'); bt.disabled = true; m.textContent = 'Wysyłam…';
    fetch(C.api, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({lat: ll.lat, lon: ll.lng, ile: sel.ile, co: sel.co, kiedy: new Date().toISOString()})})
      .then(function (r) { return r.json().then(function (j) { return {ok: r.ok, j: j}; }); })
      .then(function (x) { bt.disabled = false; if (x.ok) { stop(); hint('Dodano. Dziękujemy!'); setTimeout(function () { hint(''); }, 3000); load(); } else m.textContent = x.j && x.j.message ? x.j.message : 'Nie udało się dodać.'; })
      .catch(function () { bt.disabled = false; m.textContent = 'Brak połączenia. Spróbuj ponownie.'; });
  };
  // pełny ekran i położenie
  $('zdz-full').onclick = function () { var w = $('zdz-wrap'), f = w.classList.toggle('full'); document.documentElement.style.overflow = f ? 'hidden' : ''; this.textContent = f ? '✕' : '⛶'; setTimeout(function () { map.invalidateSize(); }, 60); };
  var ja = null;
  $('zdz-loc').onclick = function () { if (!navigator.geolocation) return; hint('Szukam Twojego położenia…');
    navigator.geolocation.getCurrentPosition(function (p) { var ll = [p.coords.latitude, p.coords.longitude]; hint(adding ? 'Dotknij mapy tam, gdzie są dziki' : '');
      if (ja) map.removeLayer(ja); ja = L.circleMarker(ll, {radius: 8, color: '#fff', weight: 3, fillColor: '#2563eb', fillOpacity: 1, interactive: false}).addTo(map); map.flyTo(ll, 17, {duration: 0.6}); },
      function () { hint('Nie udało się ustalić położenia'); setTimeout(function () { hint(adding ? 'Dotknij mapy tam, gdzie są dziki' : ''); }, 2500); }, {enableHighAccuracy: true, timeout: 12000}); };
  load();
});
</script>
<?php return ob_get_clean();
});
