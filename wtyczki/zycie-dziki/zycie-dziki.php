<?php
/**
 * Plugin Name: Życie – Mapa dzików
 * Description: Mapy obserwacji dzików dla powiatu wołomińskiego i każdej gminy: [zycie_dziki_mapa] albo [zycie_dziki_mapa gmina="kobylka"]. Dane pochodzą z samodzielnego mini-serwera /mieszkaniec-api/ (niezależnego od WordPressa), do którego zgłaszają też aplikacje mieszkańców. Wtyczka instaluje ten mini-serwer, pokazuje mapy i pozwala moderować wpisy.
 * Version: 2.2.3
 * Author: Życie Powiatu
 */
if (!defined('ABSPATH')) exit;

const ZPW_DZIKI_DNI = 14;
function zpw_dziki_ile() { return array('1 dzik', '2–5 dzików', 'więcej niż 5 dzików', 'locha z młodymi'); }
function zpw_dziki_co() { return array('przechodzą', 'żerują, ryją', 'są przy śmietnikach', 'zachowują się agresywnie'); }
function zpw_dziki_api_dir() { return ABSPATH . 'mieszkaniec-api/'; }
function zpw_dziki_api_url() { return home_url('/mieszkaniec-api/?zasob=dziki'); }
function zpw_dziki_gminy() {
    static $g = null;
    if ($g === null) { $g = json_decode((string) file_get_contents(__DIR__ . '/api/gminy.json'), true); if (!is_array($g)) $g = array(); }
    return $g;
}

/**
 * Instaluje (albo aktualizuje) mini-serwer w folderze /mieszkaniec-api/ obok WordPressa.
 * Mini-serwer nie ładuje WordPressa i trzyma dane we własnym pliku, więc działa także po wyłączeniu wtyczki.
 * Dane (data/) i klucz moderacji (config.php) nie są nadpisywane.
 */
function zpw_dziki_instaluj_api() {
    $d = zpw_dziki_api_dir();
    if (!wp_mkdir_p($d . 'data')) return 'Nie można utworzyć folderu ' . $d;
    foreach (array('index.php', 'zgloszenia.php', 'gminy.json') as $f) if (!@copy(__DIR__ . '/api/' . $f, $d . $f)) return 'Nie można zapisać ' . $f;
    @copy(__DIR__ . '/api/data.htaccess', $d . 'data/.htaccess');
    if (!is_file($d . 'data/index.html')) @file_put_contents($d . 'data/index.html', '');
    if (!is_file($d . 'config.php')) {
        $klucz = wp_generate_password(48, false, false);
        if (!@file_put_contents($d . 'config.php', "<?php return array('klucz' => '" . $klucz . "');\n")) return 'Nie można zapisać config.php';
    }
    return '';
}
function zpw_dziki_klucz() {
    $f = zpw_dziki_api_dir() . 'config.php';
    $c = is_file($f) ? (array) include $f : array();
    return isset($c['klucz']) ? (string) $c['klucz'] : '';
}
register_activation_hook(__FILE__, 'zpw_dziki_instaluj_api');

function zpw_dziki_lista() {
    $r = wp_remote_get(zpw_dziki_api_url() . '&klucz=' . rawurlencode(zpw_dziki_klucz()), array('timeout' => 15));
    if (is_wp_error($r) || wp_remote_retrieve_response_code($r) !== 200) return null;
    $j = json_decode(wp_remote_retrieve_body($r), true);
    return isset($j['obserwacje']) && is_array($j['obserwacje']) ? $j['obserwacje'] : null;
}

// Podgląd zdjęcia/filmu dla moderatora (także przed zatwierdzeniem). Klucz mini-serwera nie trafia do przeglądarki.
add_action('admin_post_zpw_dziki_podglad', function () {
    if (!current_user_can('moderate_comments')) wp_die('Brak uprawnień.', 403);
    check_admin_referer('zpw_dziki_podglad');
    $n = isset($_GET['plik']) ? (string) $_GET['plik'] : '';
    if (!preg_match('/^[a-f0-9]{24}\.[a-z0-9]{3,4}$/', $n)) wp_die('Brak pliku.', 404);
    $r = wp_remote_get(zpw_dziki_api_url() . '&plik=' . $n . '&klucz=' . rawurlencode(zpw_dziki_klucz()), array('timeout' => 60));
    if (is_wp_error($r) || wp_remote_retrieve_response_code($r) !== 200) wp_die('Brak pliku.', 404);
    nocache_headers();
    header('Content-Type: ' . wp_remote_retrieve_header($r, 'content-type'));
    echo wp_remote_retrieve_body($r); exit;
});

// Po aktualizacji wtyczki pliki mini-serwera są odświeżane (dane i klucz zostają).
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    if (get_option('zpw_dziki_api_wersja') !== '2.2.1' && zpw_dziki_instaluj_api() === '') update_option('zpw_dziki_api_wersja', '2.2.1');
    // 2.2.1: naprawa – strona główna /dziki/ nie powstała, bo adres zajmowało zdjęcie; strony gmin trafiły pod to zdjęcie
    if (get_option('zpw_dziki_naprawa') !== '2.2.1' && !zpw_dziki_strona('dziki') && zpw_dziki_strona('dziki/kobylka') === null && get_page_by_path('dziki/kobylka', OBJECT, array('page', 'attachment'))) {
        $w = zpw_dziki_utworz_strony(); update_option('zpw_dziki_naprawa', '2.2.1');
        set_transient('zpw_dziki_komunikat', $w, 300);
    } else if (get_option('zpw_dziki_naprawa') !== '2.2.1') update_option('zpw_dziki_naprawa', '2.2.1');
});
add_action('admin_notices', function () { $k = get_transient('zpw_dziki_komunikat'); if ($k) { delete_transient('zpw_dziki_komunikat'); echo '<div class="notice notice-success"><p><b>Mapa dzików:</b> ' . esc_html($k) . '</p></div>'; } });

// Moderacja: redaktorzy i administratorzy (uprawnienie moderate_comments).
add_action('admin_menu', function () {
    add_menu_page('Mapa dzików', 'Mapa dzików', 'moderate_comments', 'zycie-dziki', 'zpw_dziki_admin', 'dashicons-location-alt', 58);
    add_submenu_page('zycie-dziki', 'Zgłoszenia mieszkańców', 'Zgłoszenia (dziury, awarie)', 'moderate_comments', 'zycie-zgloszenia', 'zpw_zgl_admin');
});
function zpw_dziki_admin() {
    if (!current_user_can('moderate_comments')) return;
    $G = zpw_dziki_gminy();
    if (isset($_POST['zpw_dziki_usun']) && check_admin_referer('zpw_dziki_usun')) {
        $r = wp_remote_post(zpw_dziki_api_url() . '&akcja=usun', array('timeout' => 15, 'headers' => array('Content-Type' => 'application/json'), 'body' => wp_json_encode(array('id' => sanitize_text_field(wp_unslash($_POST['zpw_dziki_usun'])), 'klucz' => zpw_dziki_klucz()))));
        $ok = !is_wp_error($r) && wp_remote_retrieve_response_code($r) === 200;
        echo '<div class="notice notice-' . ($ok ? 'success' : 'error') . '"><p>' . ($ok ? 'Obserwacja usunięta.' : 'Nie udało się usunąć obserwacji.') . '</p></div>';
    }
    if (isset($_POST['zpw_dziki_zatw']) && check_admin_referer('zpw_dziki_usun')) {
        $r = wp_remote_post(zpw_dziki_api_url() . '&akcja=zatwierdz', array('timeout' => 15, 'headers' => array('Content-Type' => 'application/json'), 'body' => wp_json_encode(array('id' => sanitize_text_field(wp_unslash($_POST['zpw_dziki_zatw'])), 'klucz' => zpw_dziki_klucz()))));
        $ok = !is_wp_error($r) && wp_remote_retrieve_response_code($r) === 200;
        echo '<div class="notice notice-' . ($ok ? 'success' : 'error') . '"><p>' . ($ok ? 'Zdjęcie/film zatwierdzone – widoczne na mapie.' : 'Nie udało się zatwierdzić.') . '</p></div>';
    }
    if (isset($_POST['zpw_dziki_api']) && check_admin_referer('zpw_dziki_narz') && current_user_can('manage_options')) { $e = zpw_dziki_instaluj_api(); echo '<div class="notice notice-' . ($e ? 'error' : 'success') . '"><p>' . esc_html($e ? $e : 'Mini-serwer zainstalowany: ' . home_url('/mieszkaniec-api/')) . '</p></div>'; }
    if (isset($_POST['zpw_dziki_strony']) && check_admin_referer('zpw_dziki_narz') && current_user_can('publish_pages')) { echo '<div class="notice notice-success"><p>' . esc_html(zpw_dziki_utworz_strony()) . '</p></div>'; }
    $rows = zpw_dziki_lista();
    echo '<div class="wrap"><h1>Mapa dzików – obserwacje z ostatnich ' . (int) ZPW_DZIKI_DNI . ' dni</h1>';
    echo '<form method="post" style="margin:10px 0">'; wp_nonce_field('zpw_dziki_narz');
    echo '<button class="button button-primary" name="zpw_dziki_strony" value="1">Utwórz strony map i zgłoszeń (powiat + 12 gmin)</button> <button class="button" name="zpw_dziki_api" value="1">Zainstaluj ponownie mini-serwer</button> <span class="description">Strony: /dziki/ i /dziki/&lt;gmina&gt;/. Istniejących nie zmienia.</span></form>';
    echo '<p>Shortcode: <code>[zycie_dziki_mapa]</code> (powiat) albo <code>[zycie_dziki_mapa gmina="kobylka"]</code>. Dane trzyma samodzielny mini-serwer <code>' . esc_html(home_url('/mieszkaniec-api/')) . '</code>, do którego zgłaszają też aplikacje mieszkańców. Wpisy starsze niż ' . (int) ZPW_DZIKI_DNI . ' dni znikają same. Zgłoszenia nie zawierają danych osobowych. <strong>Zdjęcia i filmy są widoczne na mapie dopiero po zatwierdzeniu</strong>: nie zatwierdzaj, jeśli widać na nich twarze, tablice rejestracyjne albo cokolwiek poza dzikami.</p>';
    if ($rows === null) { echo '<div class="notice notice-error"><p>Mini-serwer nie odpowiada. Użyj przycisku „Zainstaluj ponownie mini-serwer”.</p></div></div>'; return; }
    echo '<table class="widefat striped"><thead><tr><th>Kiedy</th><th>Gmina</th><th>Miejsce</th><th>Ile</th><th>Zachowanie</th><th>Zdjęcie / film</th><th>Źródło</th><th></th></tr></thead><tbody>';
    if (!$rows) echo '<tr><td colspan="8">Brak obserwacji.</td></tr>';
    foreach ($rows as $r) {
        echo '<tr><td>' . esc_html(wp_date('j.m.Y H:i', strtotime($r['kiedy']))) . '</td><td>' . esc_html(isset($G[$r['gmina']]) ? $G[$r['gmina']]['n'] : $r['gmina']) . '</td><td><a target="_blank" rel="noopener" href="' . esc_url('https://www.google.com/maps?q=' . $r['lat'] . ',' . $r['lon']) . '">' . esc_html($r['lat'] . ', ' . $r['lon']) . '</a></td><td>' . esc_html($r['ile']) . '</td><td>' . esc_html($r['co']) . '</td><td>';
        if (!empty($r['plik'])) echo '<a target="_blank" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=zpw_dziki_podglad&plik=' . $r['plik']), 'zpw_dziki_podglad')) . '">' . ($r['media'] === 'film' ? 'Obejrzyj film' : 'Zobacz zdjęcie') . '</a> – ' . (!empty($r['media_ok']) ? 'zatwierdzone' : '<strong>czeka</strong>');
        echo '</td><td>' . esc_html(isset($r['zrodlo']) ? $r['zrodlo'] : '') . '</td><td><form method="post">';
        wp_nonce_field('zpw_dziki_usun');
        if (!empty($r['plik']) && empty($r['media_ok'])) echo '<button class="button button-primary" name="zpw_dziki_zatw" value="' . esc_attr($r['id']) . '">Zatwierdź zdjęcie/film</button> ';
        echo '<button class="button" name="zpw_dziki_usun" value="' . esc_attr($r['id']) . '" onclick="return confirm(\'Usunąć tę obserwację?\')">Usuń</button></form></td></tr>';
    }
    echo '</tbody></table></div>';
}

function zpw_zgl_url() { return home_url('/mieszkaniec-api/?zasob=zgloszenia'); }
// Podgląd zdjęcia zgłoszenia dla moderatora.
add_action('admin_post_zpw_zgl_podglad', function () {
    if (!current_user_can('moderate_comments')) wp_die('Brak uprawnień.', 403);
    check_admin_referer('zpw_zgl_podglad');
    $n = isset($_GET['plik']) ? (string) $_GET['plik'] : '';
    if (!preg_match('/^[a-f0-9]{24}\.[a-z0-9]{3,4}$/', $n)) wp_die('Brak pliku.', 404);
    $r = wp_remote_get(zpw_zgl_url() . '&plik=' . $n . '&klucz=' . rawurlencode(zpw_dziki_klucz()), array('timeout' => 60));
    if (is_wp_error($r) || wp_remote_retrieve_response_code($r) !== 200) wp_die('Brak pliku.', 404);
    nocache_headers(); header('Content-Type: ' . wp_remote_retrieve_header($r, 'content-type')); echo wp_remote_retrieve_body($r); exit;
});
function zpw_zgl_admin() {
    if (!current_user_can('moderate_comments')) return;
    $G = zpw_dziki_gminy();
    $akcje = array('zatwierdz' => 'Zatwierdzono opis i zdjęcie.', 'zamknij' => 'Oznaczono jako naprawione.', 'otworz' => 'Przywrócono do otwartych.', 'usun' => 'Usunięto zgłoszenie.');
    if (isset($_POST['zpw_zgl_akcja'], $_POST['zpw_zgl_id']) && check_admin_referer('zpw_zgl')) {
        $a = sanitize_key(wp_unslash($_POST['zpw_zgl_akcja']));
        if (isset($akcje[$a])) {
            $r = wp_remote_post(zpw_zgl_url() . '&akcja=' . $a, array('timeout' => 15, 'headers' => array('Content-Type' => 'application/json'), 'body' => wp_json_encode(array('id' => sanitize_text_field(wp_unslash($_POST['zpw_zgl_id'])), 'klucz' => zpw_dziki_klucz()))));
            $ok = !is_wp_error($r) && wp_remote_retrieve_response_code($r) === 200;
            echo '<div class="notice notice-' . ($ok ? 'success' : 'error') . '"><p>' . esc_html($ok ? $akcje[$a] : 'Nie udało się wykonać operacji.') . '</p></div>';
        }
    }
    $r = wp_remote_get(zpw_zgl_url() . '&klucz=' . rawurlencode(zpw_dziki_klucz()), array('timeout' => 15));
    $j = (!is_wp_error($r) && wp_remote_retrieve_response_code($r) === 200) ? json_decode(wp_remote_retrieve_body($r), true) : null;
    echo '<div class="wrap"><h1>Zgłoszenia mieszkańców</h1><p>Dziury, awarie i inne sprawy z aplikacji mieszkańców. Zgłoszenia z tego samego miejsca (ok. 35 m) i tej samej kategorii łączą się w jedną sprawę z licznikiem. <strong>Opis i zdjęcie są publiczne dopiero po zatwierdzeniu</strong>: nie zatwierdzaj, jeśli zawierają dane osobowe, twarze albo tablice rejestracyjne. Sprawa przechodzi do naprawionych, gdy potwierdzą to 2 osoby albo moderator.</p>';
    if (!$j || !isset($j['sprawy'])) { echo '<div class="notice notice-error"><p>Mini-serwer nie odpowiada. Na stronie „Mapa dzików” użyj przycisku „Zainstaluj ponownie mini-serwer”.</p></div></div>'; return; }
    $K = isset($j['kategorie']) ? $j['kategorie'] : array(); $S = $j['sprawy'];
    usort($S, function ($a, $b) { $x = (empty($b['opis_ok']) && ($b['opis'] !== '' || !empty($b['plik']))) - (empty($a['opis_ok']) && ($a['opis'] !== '' || !empty($a['plik']))); return $x ?: strcmp($a['pierwsze'], $b['pierwsze']); });
    echo '<table class="widefat striped"><thead><tr><th>Kategoria</th><th>Gmina</th><th>Miejsce</th><th>Pierwsze zgłoszenie</th><th>Zgłoszeń</th><th>Status</th><th>Opis / zdjęcie</th><th></th></tr></thead><tbody>';
    if (!$S) echo '<tr><td colspan="8">Brak zgłoszeń.</td></tr>';
    foreach ($S as $s) {
        $czeka = empty($s['opis_ok']) && ($s['opis'] !== '' || !empty($s['plik']));
        echo '<tr><td>' . esc_html(isset($K[$s['kat']]) ? $K[$s['kat']] : $s['kat']) . '</td><td>' . esc_html(isset($G[$s['gmina']]) ? $G[$s['gmina']]['n'] : $s['gmina']) . '</td><td><a target="_blank" rel="noopener" href="' . esc_url('https://www.google.com/maps?q=' . $s['lat'] . ',' . $s['lon']) . '">mapa</a></td><td>' . esc_html(wp_date('j.m.Y', strtotime($s['pierwsze']))) . ' (' . (int) floor((time() - strtotime($s['pierwsze'])) / 86400) . ' dni)</td><td>' . (int) $s['n'] . '</td><td>' . esc_html($s['status'] === 'naprawione' ? 'naprawione ' . wp_date('j.m.Y', strtotime($s['naprawione_od'])) : 'otwarte' . ($s['naprawione_glosy'] ? ' (' . (int) $s['naprawione_glosy'] . ' głos za naprawą)' : '')) . '</td><td>' . esc_html($s['opis']);
        if (!empty($s['plik'])) echo ' <a target="_blank" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=zpw_zgl_podglad&plik=' . $s['plik']), 'zpw_zgl_podglad')) . '">zdjęcie</a>';
        if ($czeka) echo ' – <strong>czeka na zatwierdzenie</strong>';
        echo '</td><td><form method="post" style="display:flex;gap:4px;flex-wrap:wrap">'; wp_nonce_field('zpw_zgl'); echo '<input type="hidden" name="zpw_zgl_id" value="' . esc_attr($s['id']) . '">';
        if ($czeka) echo '<button class="button button-primary" name="zpw_zgl_akcja" value="zatwierdz">Zatwierdź</button>';
        echo $s['status'] === 'otwarte' ? '<button class="button" name="zpw_zgl_akcja" value="zamknij">Naprawione</button>' : '<button class="button" name="zpw_zgl_akcja" value="otworz">Otwórz ponownie</button>';
        echo '<button class="button" name="zpw_zgl_akcja" value="usun" onclick="return confirm(\'Usunąć to zgłoszenie?\')">Usuń</button></form></td></tr>';
    }
    echo '</tbody></table></div>';
}

function zpw_dziki_strona($path) { return get_page_by_path($path, OBJECT, array('page')); } // bez załączników (zdjęć) o tym samym adresie
function zpw_dziki_utworz_strony() {
    $nowe = 0; $info = '';
    $rodzic = zpw_dziki_strona('dziki');
    if (!$rodzic) {
        // Adres /dziki/ może zajmować stare zdjęcie z biblioteki mediów; zmieniamy tylko jego adres strony załącznika (plik zostaje).
        $zal = get_page_by_path('dziki', OBJECT, array('attachment'));
        if ($zal) { wp_update_post(array('ID' => $zal->ID, 'post_name' => 'dziki-zdjecie-' . $zal->ID)); $info = ' Adres /dziki/ zajmowało zdjęcie z biblioteki mediów (ID ' . $zal->ID . '); jego strona załącznika ma teraz adres /dziki-zdjecie-' . $zal->ID . '/, plik zdjęcia się nie zmienił.'; }
        $id = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'dziki', 'post_title' => 'Dziki w powiecie wołomińskim – mapa obserwacji', 'post_content' => '[zycie_dziki_mapa]'));
        if (!$id || is_wp_error($id)) return 'Nie udało się utworzyć strony głównej.';
        if (get_post_field('post_name', $id) !== 'dziki') return 'Strona główna dostała adres ' . get_permalink($id) . ' zamiast /dziki/. Sprawdź, co zajmuje adres /dziki/.';
        $nowe++; $rid = $id;
        // strony utworzone wcześniej pod zdjęciem przenosimy pod nową stronę główną
        if ($zal) foreach (get_posts(array('post_type' => 'page', 'post_status' => 'any', 'post_parent' => $zal->ID, 'numberposts' => -1)) as $c) { wp_update_post(array('ID' => $c->ID, 'post_parent' => $rid)); $info .= ''; }
    } else $rid = $rodzic->ID;
    foreach (zpw_dziki_gminy() as $slug => $g) {
        if (zpw_dziki_strona('dziki/' . $slug)) continue;
        $id = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_parent' => $rid, 'post_name' => $slug, 'post_title' => 'Dziki: ' . $g['n'] . ' – mapa obserwacji', 'post_content' => '[zycie_dziki_mapa gmina="' . $slug . '"]'));
        if ($id && !is_wp_error($id)) $nowe++;
    }
    // strony zgłoszeń: /dziki/zglos/ (gmina ustalana z położenia) i /dziki/<gmina>/zglos/
    if (!zpw_dziki_strona('dziki/zglos')) { $id = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_parent' => $rid, 'post_name' => 'zglos', 'post_title' => 'Zgłoś dziki w powiecie wołomińskim', 'post_content' => '[zycie_dziki_zglos]')); if ($id && !is_wp_error($id)) $nowe++; }
    foreach (zpw_dziki_gminy() as $slug => $g) {
        if (zpw_dziki_strona('dziki/' . $slug . '/zglos')) continue;
        $ro = zpw_dziki_strona('dziki/' . $slug); if (!$ro) continue;
        $id = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_parent' => $ro->ID, 'post_name' => 'zglos', 'post_title' => 'Zgłoś dziki: ' . $g['n'], 'post_content' => '[zycie_dziki_zglos gmina="' . $slug . '"]'));
        if ($id && !is_wp_error($id)) $nowe++;
    }
    return 'Utworzono stron: ' . $nowe . '. Mapa powiatu: ' . home_url('/dziki/') . ' · Zgłoszenia: ' . home_url('/dziki/zglos/') . '.' . $info;
}

// Mapa na stronie: [zycie_dziki_mapa] (powiat) albo [zycie_dziki_mapa gmina="kobylka"]
require_once __DIR__ . '/zglos.php';

add_shortcode('zycie_dziki_mapa', function ($atts) {
    $atts = shortcode_atts(array('gmina' => ''), $atts);
    $gm = sanitize_key($atts['gmina']);
    $G = zpw_dziki_gminy();
    if ($gm !== '' && !isset($G[$gm])) $gm = '';
    wp_enqueue_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4');
    wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);
    $linki = array();
    foreach ($G as $slug => $g) $linki[$slug] = home_url('/dziki/' . $slug . '/');
    $cfg = wp_json_encode(array('api' => esc_url_raw(zpw_dziki_api_url()), 'ile' => zpw_dziki_ile(), 'co' => zpw_dziki_co(), 'dni' => ZPW_DZIKI_DNI, 'gmina' => $gm, 'gminy' => $G, 'linki' => $linki, 'powiat' => home_url('/dziki/')));
    ob_start(); ?>
<style>
.zdz{max-width:min(980px,100%);width:100%;min-width:0;contain:inline-size;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;color:#14213d}
.zdz *{box-sizing:border-box}
.zdz-nav{max-width:100%;display:flex;gap:8px;overflow-x:auto;padding:2px 0 10px;scrollbar-width:thin}
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
.zdz-file{display:block;border:1.5px dashed #9aa3b2;border-radius:12px;padding:12px;text-align:center;font-size:15px;font-weight:600;color:#14315a;cursor:pointer;margin:0 0 10px}.zdz-file.ma{border-style:solid;border-color:#0e7c6b;color:#0b5e52;background:#e6f4f1}
.zdz .leaflet-popup-content img,.zdz .leaflet-popup-content video{display:block;width:220px;max-width:100%;border-radius:8px;margin-top:6px}
.zdz-big{display:flex;align-items:center;gap:14px;background:linear-gradient(135deg,#14315a,#0e7c6b);color:#fff!important;text-decoration:none!important;border-radius:16px;padding:14px 18px;margin:12px 0;box-shadow:0 8px 22px rgba(20,49,90,.25)}.zdz-big span{font-size:30px}.zdz-big div{flex:1}.zdz-big b{display:block;font-size:18px}.zdz-big small{font-size:13.5px;opacity:.9}.zdz-big em{font-style:normal;font-size:24px}
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
      <div id="zdz-k0">
        <div class="s" style="margin:0 0 8px;color:#14213d;font-weight:600">Gdzie są dziki?</div>
        <div class="zdz-row"><button type="button" class="zdz-ok" id="zdz-here">◎ Tu, gdzie jestem</button><button type="button" class="zdz-no" id="zdz-pick">Wskażę na mapie</button></div>
        <div class="zdz-row" style="margin-top:8px"><button type="button" class="zdz-no" id="zdz-cancel0" style="border:0;font-weight:600;padding:8px">Anuluj</button></div>
      </div>
      <div id="zdz-k1" hidden>
        <div class="s" style="margin:0 0 8px;color:#14213d;font-weight:600">Pinezka w dobrym miejscu? Przesuń ją palcem albo dotknij mapy ponownie.</div>
        <div class="zdz-row"><button type="button" class="zdz-no" id="zdz-cancel">Anuluj</button><button type="button" class="zdz-ok" id="zdz-next">Tak, dalej</button></div>
      </div>
      <div id="zdz-k2" hidden>
        <div class="zdz-lab">Ile?</div><div class="zdz-seg" id="zdz-ile"></div>
        <div class="zdz-lab">Co robią?</div><div class="zdz-seg" id="zdz-co"></div>
        <label class="zdz-file" id="zdz-fl"><input type="file" id="zdz-plik" accept="image/*,video/*" hidden><span id="zdz-fn">＋ Dodaj zdjęcie lub film (nieobowiązkowo)</span></label>
        <div class="zdz-row"><button type="button" class="zdz-no" id="zdz-back">Popraw miejsce</button><button type="button" class="zdz-ok" id="zdz-add">Dodaj obserwację</button></div>
        <div class="s" id="zdz-msg" style="margin:8px 0 0"></div>
      </div>
    </div>
  </div>
  <div class="zdz-leg"><span><i style="background:#d21f1f"></i>dziś i wczoraj</span><span><i style="background:#f08a24"></i>2–7 dni temu</span><span><i style="background:#9aa3b2"></i>8–<?php echo (int) ZPW_DZIKI_DNI; ?> dni temu</span></div>
  <a class="zdz-big" href="<?php echo esc_url(home_url('/dziki/' . ($gm !== '' ? $gm . '/' : '') . 'zglos/')); ?>"><span>🐗</span><div><b>Zgłoś dziki w 3 krokach</b><small>Zlokalizuj mnie · zdjęcie lub film · zgłoszenie i kontakt do urzędu</small></div><em>→</em></a>
  <div class="zdz-warn"><b>Dziki blisko ludzi, ranne albo agresywne?</b> Nie podchodź. Zadzwoń pod 112 albo do swojego urzędu gminy.</div>
  <p class="zdz-note">Zgłoszenia mieszkańców, niezweryfikowane. Dodając obserwację, nie podajesz żadnych danych o sobie: zapisujemy tylko miejsce, czas, liczbę i zachowanie dzików. Zdjęcia i filmy pojawiają się na mapie po sprawdzeniu przez redakcję; nie wysyłaj nagrań, na których widać ludzi. Punkty znikają po <?php echo (int) ZPW_DZIKI_DNI; ?> dniach. Granice gmin: BDOT10k (GUGiK). Mieszkasz w Kobyłce? <a href="https://lechur.github.io/wehikul-czasu/kobylka/#dziki">Zgłaszaj w Aplikacji mieszkańca Kobyłki</a>.</p>
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
  var map = L.map('zdz-map', {scrollWheelZoom: false, zoomSnap: 0.5, maxBounds: L.latLngBounds([all[0] - 0.08, all[1] - 0.15], [all[2] + 0.08, all[3] + 0.15]), minZoom: 9});
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
    fetch(C.api + (C.gmina ? '&gmina=' + C.gmina : ''), {cache: 'no-store'}).then(function (r) { return r.json(); }).then(function (d) {
      layer.clearLayers(); var now = Date.now(), tydz = 0, O = d.obserwacje || [];
      O.slice().reverse().forEach(function (o) {
        var t = new Date(o.kiedy), w = (now - t.getTime()) / 864e5; if (w <= 7) tydz++;
        L.circleMarker([o.lat, o.lon], {radius: w < 2 ? 11 : 9, color: '#fff', weight: 2.5, fillColor: kolor(w), fillOpacity: 0.95})
          .bindPopup('<b>' + esc(o.ile) + '</b>, ' + esc(o.co) + '<br>' + kiedy(t) + (G[o.gmina] ? '<br>' + esc(G[o.gmina].n) : '') + (o.plik ? (o.media === 'film' ? '<video controls preload="none" playsinline src="' + C.api + '&plik=' + o.plik + '"></video>' : '<img loading="lazy" alt="Zdjęcie dzików" src="' + C.api + '&plik=' + o.plik + '">') : ''), {minWidth: o.plik ? 220 : 50}).addTo(layer);
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
  function krok(n) { $('zdz-k0').hidden = n !== 0; $('zdz-k1').hidden = n !== 1; $('zdz-k2').hidden = n !== 2; }
  function pl(n, a, b, c) { return n === 1 ? a : (n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14)) ? b : c; }
  var media = null;
  function wyczyscPlik() { media = null; $('zdz-plik').value = ''; $('zdz-fl').classList.remove('ma'); $('zdz-fn').textContent = '＋ Dodaj zdjęcie lub film (nieobowiązkowo)'; }
  function stop() { adding = false; krok(0); wyczyscPlik(); if (pin) { map.removeLayer(pin); pin = null; } $('zdz-sheet').style.display = 'none'; $('zdz-cta').style.display = ''; hint(''); $('zdz-msg').textContent = ''; }
  function setPin(ll) {
    if (!pin) { pin = L.marker(ll, {icon: ikona, draggable: true, autoPan: true}).addTo(map); } else pin.setLatLng(ll);
    $('zdz-sheet').style.display = 'block'; krok(1);
    if (map.getZoom() < 17) { hint('Powiększam…'); map.flyTo(ll, 17.5, {duration: 0.6}); map.once('moveend', function () { hint(''); }); } else hint('');
  }
  $('zdz-start').onclick = function () { $('zdz-cta').style.display = 'none'; $('zdz-sheet').style.display = 'block'; krok(0); };
  $('zdz-cancel0').onclick = stop;
  $('zdz-pick').onclick = function () { adding = true; $('zdz-sheet').style.display = 'none'; hint('Dotknij mapy tam, gdzie są dziki'); map.scrollWheelZoom.enable(); };
  $('zdz-here').onclick = function () { var bt = this; if (!navigator.geolocation) { $('zdz-pick').click(); return; } bt.disabled = true; bt.textContent = 'Szukam Twojego położenia…';
    navigator.geolocation.getCurrentPosition(function (p) { bt.disabled = false; bt.textContent = '◎ Tu, gdzie jestem'; adding = true; map.scrollWheelZoom.enable(); setPin(L.latLng(p.coords.latitude, p.coords.longitude)); },
      function () { bt.disabled = false; bt.textContent = '◎ Tu, gdzie jestem'; $('zdz-pick').click(); hint('Nie udało się ustalić położenia. Dotknij mapy tam, gdzie są dziki'); }, {enableHighAccuracy: true, timeout: 12000}); };
  // zdjęcie jest zmniejszane w telefonie (bez danych o aparacie i położeniu); film wysyłany bez zmian, do 40 MB
  $('zdz-plik').onchange = function () { var f = this.files[0]; if (!f) { wyczyscPlik(); return; }
    function ok(b, nazwa) { media = {blob: b, nazwa: nazwa}; $('zdz-fl').classList.add('ma'); $('zdz-fn').textContent = '✓ ' + (f.type.indexOf('video') === 0 ? 'Film' : 'Zdjęcie') + ' dodane (dotknij, aby zmienić)'; }
    if (f.type.indexOf('video') === 0) { if (f.size > 40 * 1048576) { wyczyscPlik(); $('zdz-msg').textContent = 'Film jest za duży (najwyżej 40 MB, ok. 20 sekund).'; return; } ok(f, f.name); return; }
    var img = new Image(), u = URL.createObjectURL(f); img.onload = function () { var k = Math.min(1, 1600 / Math.max(img.width, img.height)), c = document.createElement('canvas'); c.width = Math.round(img.width * k); c.height = Math.round(img.height * k); c.getContext('2d').drawImage(img, 0, 0, c.width, c.height); URL.revokeObjectURL(u); c.toBlob(function (b) { ok(b, 'dziki.jpg'); }, 'image/jpeg', 0.82); };
    img.onerror = function () { wyczyscPlik(); $('zdz-msg').textContent = 'Nie udało się odczytać zdjęcia.'; }; img.src = u; };
  $('zdz-cancel').onclick = stop; $('zdz-next').onclick = function () { krok(2); hint(''); }; $('zdz-back').onclick = function () { krok(1); };
  map.on('click', function (e) { if (adding) setPin(e.latlng); });
  $('zdz-add').onclick = function () {
    var m = $('zdz-msg'); if (!pin) { m.textContent = 'Najpierw dotknij mapy.'; return; }
    var ll = pin.getLatLng(), bt = $('zdz-add'); bt.disabled = true; m.textContent = 'Wysyłam…';
    var fd = new FormData(); fd.append('lat', ll.lat); fd.append('lon', ll.lng); fd.append('ile', sel.ile); fd.append('co', sel.co); fd.append('kiedy', new Date().toISOString()); fd.append('zrodlo', 'zyciepw'); if (media) fd.append('plik', media.blob, media.nazwa);
    var mial = !!media;
    fetch(C.api, {method: 'POST', body: fd})
      .then(function (r) { return r.json().then(function (j) { return {ok: r.ok, j: j}; }); })
      .then(function (x) { bt.disabled = false; if (x.ok) { stop(); hint(x.j.media_blad ? 'Dodano bez pliku: ' + x.j.media_blad : mial ? 'Dodano. Zdjęcie/film pojawi się po sprawdzeniu' : 'Dodano. Dziękujemy!'); setTimeout(function () { hint(''); }, 4500); load(); } else m.textContent = x.j && x.j.message ? x.j.message : 'Nie udało się dodać.'; })
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
