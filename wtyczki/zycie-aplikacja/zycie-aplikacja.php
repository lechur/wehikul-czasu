<?php
/**
 * Plugin Name: Życie – Aplikacja czytelnika
 * Description: Instaluje aplikację dla czytelników (PWA) w folderze /aplikacja/ obok WordPressa: wiadomości z gminy, Pytanie dnia, Twoja okolica, mapa dzików, kontakt z redakcją. Aplikacja to zwykłe pliki – działa także po wyłączeniu wtyczki. Aktualizacja: wgraj nową wersję wtyczki („Zastąp obecną”) albo użyj Narzędzia → Aplikacja czytelnika.
 * Version: 1.4.0
 * Author: Życie Powiatu
 */
if (!defined('ABSPATH')) exit;

function zpw_app_dir() { return ABSPATH . 'aplikacja/'; }

/** Kopiuje pliki aplikacji z wtyczki do /aplikacja/. Zwraca '' albo komunikat błędu. */
function zpw_app_instaluj() {
    $d = zpw_app_dir(); $src = __DIR__ . '/app/';
    if (!wp_mkdir_p($d)) return 'Nie można utworzyć folderu ' . $d;
    $pliki = glob($src . '*');
    if (!$pliki) return 'Brak plików aplikacji we wtyczce.';
    foreach ($pliki as $f) if (is_file($f) && !@copy($f, $d . basename($f))) return 'Nie można zapisać ' . basename($f);
    $ht = "<IfModule mod_mime.c>\nAddType application/manifest+json .webmanifest\n</IfModule>\n<IfModule mod_headers.c>\n<FilesMatch \"^(sw\\.js|index\\.html)$\">\nHeader set Cache-Control \"no-cache\"\n</FilesMatch>\n</IfModule>\nDirectoryIndex index.html\n";
    @file_put_contents($d . '.htaccess', $ht);
    update_option('zpw_app_wersja', '1.4.0 · ' . current_time('Y-m-d H:i'), false);
    return '';
}
register_activation_hook(__FILE__, 'zpw_app_instaluj');
// „Zastąp obecną” nie uruchamia aktywacji – po podmianie wtyczki pliki odświeżą się same.
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    $v = (string) @filemtime(__DIR__ . '/app/index.html');
    if (get_option('zpw_app_mtime') !== $v) { if (zpw_app_instaluj() === '') update_option('zpw_app_mtime', $v, false); }
});

add_action('admin_menu', function () {
    add_management_page('Aplikacja czytelnika', 'Aplikacja czytelnika', 'manage_options', 'zpw-aplikacja', function () {
        if (isset($_POST['zpw_app_inst']) && check_admin_referer('zpw_app')) { $e = zpw_app_instaluj(); echo '<div class="notice notice-' . ($e ? 'error' : 'success') . '"><p>' . esc_html($e ? $e : 'Pliki aplikacji zapisane.') . '</p></div>'; }
        $u = home_url('/aplikacja/');
        echo '<div class="wrap"><h1>Aplikacja czytelnika</h1><p>Adres: <a href="' . esc_url($u) . '" target="_blank" rel="noopener">' . esc_html($u) . '</a></p><p>Zainstalowana wersja: <code>' . esc_html(get_option('zpw_app_wersja', 'brak')) . '</code></p><p>Statystyki: Google Analytics portalu, grupa treści „aplikacja”. Reklamy w aplikacji są wyłączone.</p><form method="post">';
        wp_nonce_field('zpw_app');
        echo '<p><button class="button button-primary" name="zpw_app_inst" value="1">Zapisz pliki aplikacji ponownie</button></p></form></div>';
    });
});
