<?php
/**
 * Plugin Name: Życie – Mapa dzików
 * Description: Wspólna mapa obserwacji dzików (Kobyłka). Przyjmuje anonimowe zgłoszenia z Aplikacji mieszkańca Kobyłki i ze strony, pokazuje je na mapie (shortcode [zycie_dziki_mapa]). Bez danych osobowych; wpisy znikają po 14 dniach.
 * Version: 1.0.0
 * Author: Życie Powiatu
 */
if (!defined('ABSPATH')) exit;

const ZPW_DZIKI_DNI = 14;          // jak długo obserwacja jest widoczna
const ZPW_DZIKI_LIMIT = 5;         // zgłoszeń na godzinę z jednego urządzenia
const ZPW_DZIKI_BBOX = array(52.300, 52.385, 21.140, 21.265); // lat min, lat max, lon min, lon max (Kobyłka z marginesem)
const ZPW_DZIKI_ORIGINS = array('https://lechur.github.io', 'https://www.zyciepw.pl', 'https://zyciepw.pl');

function zpw_dziki_table() { global $wpdb; return $wpdb->prefix . 'zycie_dziki'; }
function zpw_dziki_ile() { return array('1 dzik', '2–5 dzików', 'więcej niż 5 dzików', 'locha z młodymi'); }
function zpw_dziki_co() { return array('przechodzą', 'żerują, ryją', 'są przy śmietnikach', 'zachowują się agresywnie'); }

register_activation_hook(__FILE__, function () {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $t = zpw_dziki_table();
    dbDelta("CREATE TABLE $t (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        lat decimal(6,3) NOT NULL,
        lon decimal(6,3) NOT NULL,
        ile varchar(40) NOT NULL,
        co varchar(40) NOT NULL,
        kiedy datetime NOT NULL,
        dodano datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY kiedy (kiedy)
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

function zpw_dziki_lista() {
    global $wpdb;
    $od = gmdate('Y-m-d H:i:s', time() - ZPW_DZIKI_DNI * DAY_IN_SECONDS);
    $rows = $wpdb->get_results($wpdb->prepare('SELECT id, lat, lon, ile, co, kiedy FROM ' . zpw_dziki_table() . ' WHERE kiedy >= %s ORDER BY kiedy DESC LIMIT 500', $od), ARRAY_A);
    $out = array();
    foreach ((array) $rows as $r) {
        $out[] = array('id' => (int) $r['id'], 'lat' => (float) $r['lat'], 'lon' => (float) $r['lon'], 'ile' => $r['ile'], 'co' => $r['co'], 'kiedy' => gmdate('c', strtotime($r['kiedy'] . ' UTC')));
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
            'callback' => function () {
                $r = new WP_REST_Response(array('dni' => ZPW_DZIKI_DNI, 'stan' => gmdate('c'), 'obserwacje' => zpw_dziki_lista()));
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
                $b = ZPW_DZIKI_BBOX;
                if ($lat < $b[0] || $lat > $b[1] || $lon < $b[2] || $lon > $b[3]) return new WP_Error('poza_miastem', 'Miejsce jest poza Kobyłką.', array('status' => 422));
                $ile = isset($p['ile']) ? (string) $p['ile'] : ''; $co = isset($p['co']) ? (string) $p['co'] : '';
                if (!in_array($ile, zpw_dziki_ile(), true) || !in_array($co, zpw_dziki_co(), true)) return new WP_Error('zle_dane', 'Nieznana wartość.', array('status' => 422));
                $ts = isset($p['kiedy']) ? strtotime((string) $p['kiedy']) : time();
                if (!$ts || $ts > time() + 600 || $ts < time() - 3 * DAY_IN_SECONDS) $ts = time();
                // Ograniczenie nadużyć: przez godzinę trzymany jest tylko skrót (hash) adresu, nie sam adres IP.
                $klucz = 'zpw_dz_' . substr(hash('sha256', (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '') . wp_salt('nonce') . gmdate('Y-m-d-H')), 0, 24);
                $n = (int) get_transient($klucz);
                if ($n >= ZPW_DZIKI_LIMIT) return new WP_Error('za_duzo', 'Za dużo zgłoszeń. Spróbuj za godzinę.', array('status' => 429));
                set_transient($klucz, $n + 1, HOUR_IN_SECONDS);
                // Miejsce zaokrąglone do ok. 100 m.
                $ok = $wpdb->insert(zpw_dziki_table(), array('lat' => round($lat, 3), 'lon' => round($lon, 3), 'ile' => $ile, 'co' => $co, 'kiedy' => gmdate('Y-m-d H:i:s', $ts), 'dodano' => gmdate('Y-m-d H:i:s')), array('%f', '%f', '%s', '%s', '%s', '%s'));
                if (!$ok) return new WP_Error('blad', 'Nie udało się zapisać.', array('status' => 500));
                return new WP_REST_Response(array('ok' => true, 'id' => (int) $wpdb->insert_id), 201);
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
    echo '<p>Mapa na stronie: shortcode <code>[zycie_dziki_mapa]</code>. Wpisy starsze niż ' . (int) ZPW_DZIKI_DNI . ' dni są usuwane automatycznie. Zgłoszenia nie zawierają danych osobowych.</p>';
    echo '<table class="widefat striped"><thead><tr><th>Kiedy</th><th>Miejsce</th><th>Ile</th><th>Zachowanie</th><th></th></tr></thead><tbody>';
    if (!$rows) echo '<tr><td colspan="5">Brak obserwacji.</td></tr>';
    foreach ($rows as $r) {
        echo '<tr><td>' . esc_html(wp_date('j.m.Y H:i', strtotime($r['kiedy']))) . '</td><td><a target="_blank" rel="noopener" href="' . esc_url('https://www.google.com/maps?q=' . $r['lat'] . ',' . $r['lon']) . '">' . esc_html($r['lat'] . ', ' . $r['lon']) . '</a></td><td>' . esc_html($r['ile']) . '</td><td>' . esc_html($r['co']) . '</td><td><form method="post">';
        wp_nonce_field('zpw_dziki_usun');
        echo '<button class="button" name="zpw_dziki_usun" value="' . (int) $r['id'] . '" onclick="return confirm(\'Usunąć tę obserwację?\')">Usuń</button></form></td></tr>';
    }
    echo '</tbody></table></div>';
}

// Mapa na stronie: [zycie_dziki_mapa]
add_shortcode('zycie_dziki_mapa', function () {
    wp_enqueue_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4');
    wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);
    $api = esc_url_raw(rest_url('zycie-dziki/v1/obserwacje'));
    $cfg = wp_json_encode(array('api' => $api, 'ile' => zpw_dziki_ile(), 'co' => zpw_dziki_co(), 'dni' => ZPW_DZIKI_DNI));
    ob_start(); ?>
<div class="zpw-dziki" style="max-width:860px;margin:0 auto">
  <p id="zpw-dz-sum" style="font-weight:600;margin:0 0 10px">Wczytuję obserwacje…</p>
  <div id="zpw-dz-map" style="height:460px;border-radius:14px;overflow:hidden;background:#eef1f5"></div>
  <p style="font-size:14px;color:#5b6577;margin:8px 0 16px">Zgłoszenia mieszkańców, niezweryfikowane. Miejsce jest zaokrąglone do ok. 100 m. Punkty znikają po <?php echo (int) ZPW_DZIKI_DNI; ?> dniach. Im jaśniejszy punkt, tym starsza obserwacja.</p>
  <details style="background:#fff7e0;border-radius:14px;padding:14px 16px;margin-bottom:16px"><summary style="font-weight:700;cursor:pointer">Widzisz dziki? Dodaj obserwację</summary>
    <p style="margin:10px 0 6px">Dotknij mapy w miejscu, gdzie widzisz dziki, a potem wybierz:</p>
    <p style="margin:6px 0"><label>Ile? <select id="zpw-dz-ile"></select></label> &nbsp; <label>Co robią? <select id="zpw-dz-co"></select></label></p>
    <p style="margin:6px 0"><button type="button" id="zpw-dz-add" style="padding:10px 18px;border:0;border-radius:10px;background:#0e7c6b;color:#fff;font-weight:700;cursor:pointer">Dodaj do mapy</button> <span id="zpw-dz-msg" style="margin-left:8px"></span></p>
    <p style="font-size:13px;color:#5b6577;margin:6px 0 0">Nie podajesz żadnych danych o sobie. Dziki blisko ludzi, ranne albo agresywne: zadzwoń 112 albo do Urzędu Miasta, tel. 22 760 70 16.</p>
  </details>
  <p style="margin:0"><a href="https://lechur.github.io/wehikul-czasu/kobylka/#dziki" style="font-weight:700">Zgłaszaj wygodniej w Aplikacji mieszkańca Kobyłki</a></p>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var C = <?php echo $cfg; ?>, map = L.map('zpw-dz-map', {scrollWheelZoom: false}).setView([52.3395, 21.196], 13), layer = L.layerGroup().addTo(map), pin = null;
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 18, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'}).addTo(map);
  function esc(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
  function fill(id, a) { var s = document.getElementById(id); a.forEach(function (v) { var o = document.createElement('option'); o.textContent = v; s.appendChild(o); }); }
  fill('zpw-dz-ile', C.ile); fill('zpw-dz-co', C.co);
  function load() {
    fetch(C.api, {cache: 'no-store'}).then(function (r) { return r.json(); }).then(function (d) {
      layer.clearLayers(); var now = Date.now(), tydz = 0;
      d.obserwacje.forEach(function (o) {
        var wiek = (now - new Date(o.kiedy).getTime()) / 864e5; if (wiek <= 7) tydz++;
        L.circleMarker([o.lat, o.lon], {radius: 9, color: '#7a3b12', weight: 1, fillColor: '#c2410c', fillOpacity: Math.max(0.2, 0.9 - wiek / C.dni * 0.7)})
          .bindPopup('<b>' + esc(o.ile) + '</b><br>' + esc(o.co) + '<br>' + new Date(o.kiedy).toLocaleString('pl-PL', {day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit'})).addTo(layer);
      });
      document.getElementById('zpw-dz-sum').textContent = d.obserwacje.length ? 'Obserwacje dzików w Kobyłce: ' + tydz + ' w ostatnich 7 dniach, ' + d.obserwacje.length + ' w ostatnich ' + C.dni + ' dniach.' : 'W ostatnich ' + C.dni + ' dniach nikt nie zgłosił dzików.';
    }).catch(function () { document.getElementById('zpw-dz-sum').textContent = 'Nie udało się wczytać obserwacji.'; });
  }
  map.on('click', function (e) { if (pin) map.removeLayer(pin); pin = L.marker(e.latlng).addTo(map); });
  document.getElementById('zpw-dz-add').onclick = function () {
    var m = document.getElementById('zpw-dz-msg');
    if (!pin) { m.textContent = 'Najpierw dotknij mapy.'; return; }
    var ll = pin.getLatLng();
    fetch(C.api, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({lat: ll.lat, lon: ll.lng, ile: document.getElementById('zpw-dz-ile').value, co: document.getElementById('zpw-dz-co').value, kiedy: new Date().toISOString()})})
      .then(function (r) { return r.json().then(function (j) { return {ok: r.ok, j: j}; }); })
      .then(function (x) { if (x.ok) { m.textContent = 'Dodano. Dziękujemy.'; map.removeLayer(pin); pin = null; load(); } else { m.textContent = x.j && x.j.message ? x.j.message : 'Nie udało się dodać.'; } })
      .catch(function () { m.textContent = 'Brak połączenia.'; });
  };
  load();
});
</script>
<?php return ob_get_clean();
});
