<?php
/**
 * Plugin Name: Życie – podgląd szkiców przez link
 * Description: Pozwala otworzyć nieopublikowany szkic (draft/pending) przez tajny link z parametrem ?podglad=KLUCZ. Klucz jest losowy, osobny dla każdego wpisu. Szkic dalej nie jest publiczny, nie trafia na stronę główną, do kanałów RSS ani do wyszukiwarek (noindex).
 * Version: 1.0.0
 * Author: Życie Powiatu
 */
if (!defined('ABSPATH')) exit;

/** Returns (and creates on first use) the secret preview key of a post. */
function zpw_podglad_klucz($post_id) {
    $k = get_post_meta($post_id, '_zpw_podglad', true);
    if (!$k) { $k = wp_generate_password(20, false, false); update_post_meta($post_id, '_zpw_podglad', $k); }
    return $k;
}
function zpw_podglad_link($post_id) {
    return add_query_arg(array('p' => $post_id, 'podglad' => zpw_podglad_klucz($post_id)), home_url('/'));
}

/* Let anonymous visitors with a valid key see a draft/pending post. */
add_filter('posts_results', function ($posts, $q) {
    if (is_admin() || !$q->is_main_query() || empty($_GET['podglad']) || count($posts) !== 1) return $posts;
    $p = $posts[0];
    if (in_array($p->post_status, array('draft', 'pending', 'future'), true)
        && hash_equals((string) get_post_meta($p->ID, '_zpw_podglad', true), (string) wp_unslash($_GET['podglad']))) {
        $p->post_status = 'publish';            // only for this request, not saved
        add_action('wp_head', function () { echo "<meta name='robots' content='noindex,nofollow'>\n"; }, 1);
    }
    return $posts;
}, 10, 2);

/* Show the link in the editor sidebar (classic + block editor meta box). */
add_action('add_meta_boxes', function () {
    foreach (array('post', 'page') as $t) {
        add_meta_box('zpw_podglad', 'Podgląd przez link', function ($post) {
            if ($post->post_status === 'publish') { echo '<p>Wpis jest opublikowany.</p>'; return; }
            $u = esc_url(zpw_podglad_link($post->ID));
            echo "<p>Link dla osób bez logowania (szkic pozostaje nieopublikowany):</p><input type='text' readonly style='width:100%' value='$u' onclick='this.select()'>";
        }, $t, 'side');
    }
});

/* REST field so the link can be read/created via the API (admin only). */
add_action('rest_api_init', function () {
    foreach (array('post', 'page') as $t) {
        register_rest_field($t, 'zpw_podglad', array('get_callback' => function ($o) {
            return current_user_can('edit_post', $o['id']) ? zpw_podglad_link($o['id']) : null;
        }));
    }
});
