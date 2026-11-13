<?php
/**
 * Plugin Name: Życie – plakaty z Twojej okolicy
 * Description: Konfigurator plakatów (mapa „Tu mieszkamy”, niebo w ważnym dniu, miasto z lotu ptaka) z podglądem wydruku i zamówieniem. Shortcode: [zycie_plakaty]
 * Version: 0.1.0
 * Author: Życie Powiatu / Logos s.c.
 */
if (!defined('ABSPATH')) exit;
add_action('init', function(){
  register_post_type('zpw_plakat_zam', ['label'=>'Zamówienia plakatów','public'=>false,'show_ui'=>true,'menu_icon'=>'dashicons-format-image','supports'=>['title','editor','custom-fields'],'capability_type'=>'post','map_meta_cap'=>true]);
});
add_shortcode('zycie_plakaty', function($atts){
  $a = shortcode_atts(['wydawca'=>get_bloginfo('name'),'kontakt'=>get_option('admin_email'),'tel'=>''], $atts);
  $u = plugin_dir_url(__FILE__);
  wp_enqueue_script('zycie-plakaty', $u.'app.js', [], '0.1.0', true);
  wp_add_inline_script('zycie-plakaty', 'window.ZPW_PLAKATY='.wp_json_encode([
    'dataUrl'=>$u, 'wydawca'=>$a['wydawca'], 'portal'=>wp_parse_url(home_url(),PHP_URL_HOST), 'kontakt'=>$a['kontakt'], 'tel'=>$a['tel'],
    'orderUrl'=>rest_url('zycie-plakaty/v1/zamowienie'), 'nonce'=>wp_create_nonce('wp_rest')]).';', 'before');
  ob_start(); ?>
<style>
:root{--ink:#1d2430;--mut:#6b7280;--line:#e5e1d8;--acc:#C8553D;--bg:#faf8f4}
*{box-sizing:border-box}.zp-wrap{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Arial,sans-serif;color:var(--ink);background:var(--bg)}
.zp-head{padding:22px 24px 8px;max-width:1240px;margin:0 auto}
.zp-head h1{font-family:Georgia,serif;font-weight:400;font-size:32px;margin:0 0 6px}
.zp-head p{margin:0;color:var(--mut);max-width:760px;line-height:1.5}
.zp{display:grid;grid-template-columns:minmax(0,1fr) 380px;gap:24px;max-width:1240px;margin:0 auto;padding:12px 24px 40px}
@media(max-width:900px){.zp{grid-template-columns:1fr}}
.zp-tabs{display:flex;gap:8px;flex-wrap:wrap;max-width:1240px;margin:8px auto 0;padding:0 24px}
.zp-tab{border:1px solid var(--line);background:#fff;border-radius:999px;padding:9px 16px;font-size:14px;cursor:pointer}
.zp-tab.on{background:var(--ink);color:#fff;border-color:var(--ink)}
.zp-tab small{display:block;font-size:11px;opacity:.7}
#zp-stage{background:#ece8e0;border-radius:14px;min-height:640px;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:12px;position:relative;transition:background .3s}
#zp-stage.wall{background:linear-gradient(#d9d3c7 0 78%,#b9a88f 78% 100%)}
.zp-frame{padding:0;transition:all .2s;line-height:0}
.zp-frame.bez{box-shadow:0 10px 30px rgba(0,0,0,.18)}
.zp-frame.rama-czarna{padding:10px;background:#1b1b1b;box-shadow:0 14px 36px rgba(0,0,0,.3)}
.zp-frame.rama-biała{padding:10px;background:#f7f7f5;box-shadow:0 14px 36px rgba(0,0,0,.22)}
.zp-frame.rama-dąb{padding:10px;background:linear-gradient(135deg,#b88b5a,#9a6e40);box-shadow:0 14px 36px rgba(0,0,0,.25)}
#zp-canvas{cursor:crosshair;display:block}
#zp-wallnote{position:absolute;bottom:12px;left:0;right:0;text-align:center;font-size:12px;color:#5b5348}
.zp-tools{display:flex;gap:8px;justify-content:center;margin-top:12px;flex-wrap:wrap}
button,.btn{font:inherit;border:1px solid var(--ink);background:#fff;color:var(--ink);border-radius:8px;padding:9px 14px;cursor:pointer}
button.pri{background:var(--acc);border-color:var(--acc);color:#fff;font-weight:600}
aside{background:#fff;border:1px solid var(--line);border-radius:14px;padding:18px;align-self:start;position:sticky;top:12px}
aside h3{margin:16px 0 8px;font-size:13px;text-transform:uppercase;letter-spacing:.06em;color:var(--mut)}
aside h3:first-child{margin-top:0}
label{display:block;font-size:13px;margin:8px 0 3px;color:#374151}
input,select,textarea{width:100%;font:inherit;padding:8px 10px;border:1px solid #d6d2c8;border-radius:8px;background:#fff}
.row{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.hint{font-size:12px;color:var(--mut);margin:4px 0}
.price{display:flex;justify-content:space-between;align-items:baseline;border-top:1px solid var(--line);margin-top:14px;padding-top:12px}
.price b{font-size:26px;font-family:Georgia,serif}
#zp-msg{font-size:13px;margin-top:10px;line-height:1.45}#zp-msg pre{white-space:pre-wrap;background:#f4f2ee;padding:8px;border-radius:6px;font-size:12px}
#zp-print{position:fixed;inset:0;background:rgba(15,15,15,.88);display:none;flex-direction:column;align-items:center;z-index:9;overflow:auto;padding:20px}
#zp-print img{max-width:none;width:min(1400px,180vw);background:#fff}
.zp-print-info{color:#eee;font-size:13px;margin:8px 0}
.zp-x{position:fixed;top:12px;right:16px;background:#fff}
.chk{display:flex;gap:8px;align-items:flex-start;font-size:12px;color:#4b5563}.chk input{width:auto;margin-top:2px}
.zp-foot{max-width:1240px;margin:0 auto;padding:0 24px 30px;font-size:12px;color:var(--mut);line-height:1.5}
</style>
<div class="zp-wrap">
<div class="zp-head">
  <h1>Plakaty z Twojej okolicy</h1>
  <p>Twoja ulica, Twoje niebo, Twoje miasto z lotu ptaka. Ustaw plakat, zobacz go na ścianie i podejrzyj plik do druku. Drukujemy lokalnie i wysyłamy w 5–7 dni roboczych.</p>
</div>
<div class="zp-tabs">
  <button class="zp-tab" data-p="mapa">Tu mieszkamy<small>mapa z Twoim domem · od 79 zł</small></button>
  <button class="zp-tab" data-p="niebo">Niebo w ważnym dniu<small>gwiazdy nad miastem · od 79 zł</small></button>
  <button class="zp-tab" data-p="duet">Duet<small>mapa + niebo · od 149 zł</small></button>
  <button class="zp-tab" data-p="miasto">Miasto z lotu ptaka<small>wiek budynków, rzeźba terenu · od 79 zł</small></button>
</div>
<div class="zp">
  <div>
    <div id="zp-stage"><div id="zp-load" class="hint"></div><div id="zp-frame" class="zp-frame bez"><canvas id="zp-canvas"></canvas></div><div id="zp-wallnote"></div></div>
    <div class="zp-tools">
      <button id="zp-wall">Zobacz na ścianie</button>
      <button id="zp-printbtn">Podgląd wydruku</button>
      <button id="zp-dl">Pobierz podgląd (JPG)</button>
    </div>
    <p class="hint" style="text-align:center" data-for="mapa duet">Kliknij na mapie, żeby przesunąć znacznik na swój dom.</p>
  </div>
  <aside>
    <h3>Miejsce</h3>
    <label>Miasto</label><select id="zp-miasto"></select>
    <div data-for="mapa duet"><label>Skala (szerokość mapy, m)</label><input id="zp-zoom" type="range" min="600" max="6000" step="100"></div>
    <div data-for="miasto"><label>Mapa</label><select id="zp-miastoprod"><option value="wzrost">Jak rosło miasto 2001–2024</option><option value="rzezba">Rzeźba terenu (lidar)</option><option value="budynki">Każdy budynek</option></select>
      <p class="hint">Na razie Kobyłka i Marki. Kolejne miasta powiatu dochodzą co tydzień.</p></div>
    <div data-for="mapa duet">
      <h3>Napis</h3>
      <label>Tytuł</label><input id="zp-tytul" maxlength="28">
      <label>Podtytuł</label><input id="zp-podtytul" maxlength="48">
      <div class="row"><div><label>Styl</label><select id="zp-styl"></select></div>
      <div><label>Znacznik</label><select id="zp-pin"><option value="serce">serce</option><option value="kropka">kropka</option></select></div></div>
    </div>
    <div data-for="niebo duet">
      <h3>Niebo</h3>
      <div class="row"><div><label>Data</label><input id="zp-data" type="date" min="1900-01-01" max="2100-12-31"></div><div><label>Godzina</label><input id="zp-godz" type="time"></div></div>
      <label>Tytuł</label><input id="zp-skytytul" maxlength="32">
      <label>Imiona lub podpis</label><input id="zp-skypodtytul" maxlength="40">
      <label>Styl</label><select id="zp-skystyl"></select>
      <p class="hint">Gwiazdy liczymy dokładnie dla wybranego miasta, dnia i godziny.</p>
    </div>
    <h3>Wydruk</h3>
    <div class="row"><div><label>Format</label><select id="zp-format"><option value="30x40">30×40 cm</option><option value="50x70">50×70 cm</option></select></div>
    <div><label>Wykończenie</label><select id="zp-rama"><option value="plakat">sam plakat</option><option value="rama">w ramie</option></select></div></div>
    <div class="row"><div><label>Rama</label><select id="zp-ramakolor"><option value="czarna">czarna</option><option value="biała">biała</option><option value="dąb">dąb</option></select></div>
    <div><label>Ilość</label><input id="zp-ilosc" type="number" min="1" max="20"></div></div>
    <div class="price"><span><span id="zp-cena-opis" class="hint"></span><br>cena</span><b id="zp-cena"></b></div>
    <h3>Zamówienie</h3>
    <form id="zp-form">
      <label>Imię i nazwisko</label><input name="imie" required>
      <div class="row"><div><label>E-mail</label><input name="email" type="email" required></div><div><label>Telefon</label><input name="telefon" required></div></div>
      <label>Dostawa</label><select name="dostawa" id="zp-dost"><option value="paczkomat">Paczkomat InPost (+15 zł)</option><option value="kurier">Kurier (+19 zł)</option><option value="odbior">Odbiór w redakcji (0 zł)</option></select>
      <label>Adres lub kod paczkomatu</label><input name="adres">
      <label>Uwagi</label><textarea name="uwagi" rows="2"></textarea>
      <label class="chk"><input type="checkbox" name="zgoda" value="1"> Zgadzam się na przetwarzanie moich danych w celu realizacji zamówienia. Plakat jest robiony na moje zamówienie, więc nie przysługuje mi zwrot bez wady.</label>
      <div class="price"><span>Razem z dostawą</span><b id="zp-razem"></b></div>
      <button class="pri" type="submit" style="width:100%;margin-top:10px">Zamawiam: projekt do akceptacji w 24 h</button>
      <p class="hint">Płacisz dopiero po akceptacji projektu (link Przelewy24 od wydawcy).</p>
      <div id="zp-msg"></div>
    </form>
  </aside>
</div>
<div class="zp-foot">Mapy: Główny Urząd Geodezji i Kartografii (BDOT10k, NMT, ortofotomapy), opracowanie redakcji. Gwiazdy: HYG Database (CC BY-SA 4.0), gwiazdozbiory: Stellarium (CC BY-SA). Wydawca i sprzedawca: wydawca portalu powiatowego.</div>
<div id="zp-print"><button class="zp-x">Zamknij ✕</button><div class="zp-print-info"></div><img alt="Podgląd wydruku"></div>
</div>
<?php return ob_get_clean();
});
add_action('rest_api_init', function(){
  register_rest_route('zycie-plakaty/v1','/zamowienie',['methods'=>'POST','permission_callback'=>'__return_true','callback'=>function($r){
    $d = $r->get_json_params(); $k = isset($d['klient']) ? $d['klient'] : [];
    $email = sanitize_email($k['email'] ?? ''); $imie = sanitize_text_field($k['imie'] ?? '');
    if (!$email || !$imie || empty($k['zgoda'])) return new WP_Error('brak','Brak danych lub zgody',['status'=>400]);
    $ip = $_SERVER['REMOTE_ADDR'] ?? ''; $key = 'zpw_plk_'.md5($ip);
    if (get_transient($key) >= 5) return new WP_Error('limit','Za dużo zamówień, spróbuj później',['status'=>429]);
    set_transient($key, (int)get_transient($key)+1, HOUR_IN_SECONDS);
    $kod = sanitize_text_field($d['kod'] ?? ('ZP-'.time()));
    $opis = sanitize_textarea_field($d['opis'] ?? '');
    $cena = floatval($d['cena'] ?? 0);
    $tresc = "Zamówienie $kod\n$opis\nCena z dostawą: $cena zł\nKlient: $imie, $email, ".sanitize_text_field($k['telefon']??'')."\nDostawa: ".sanitize_text_field($k['dostawa']??'').' '.sanitize_text_field($k['adres']??'')."\nUwagi: ".sanitize_textarea_field($k['uwagi']??'');
    $id = wp_insert_post(['post_type'=>'zpw_plakat_zam','post_status'=>'private','post_title'=>"$kod – $imie",'post_content'=>$tresc]);
    update_post_meta($id,'konfiguracja',wp_json_encode($d['konfiguracja'] ?? []));
    update_post_meta($id,'status_zamowienia','nowe');
    wp_mail(get_option('admin_email'), "Nowe zamówienie plakatu $kod", $tresc);
    wp_mail($email, "Twoje zamówienie $kod – ".get_bloginfo('name'), "Dziękujemy! Przyjęliśmy zamówienie $kod.\n\n$opis\n\nW ciągu 24 godzin wyślemy projekt do akceptacji i link do płatności.\n\n".get_bloginfo('name'));
    return ['ok'=>true,'kod'=>$kod];
  }]);
});
