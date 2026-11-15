<?php
/**
 * Kreator zgłoszenia dzików: [zycie_dziki_zglos gmina="kobylka"]
 * Trzy kroki: miejsce (lokalizacja albo mapa), zdjęcie lub film, zgłoszenie.
 * Po wysłaniu pokazuje kontakt do urzędu tej gminy, w której stoi pinezka.
 */
if (!defined('ABSPATH')) exit;

function zpw_dziki_kontakty() {
    static $k = null;
    if ($k === null) { $k = json_decode((string) @file_get_contents(__DIR__ . '/kontakty.json'), true); if (!is_array($k)) $k = array(); }
    return $k;
}

add_shortcode('zycie_dziki_zglos', function ($atts) {
    $atts = shortcode_atts(array('gmina' => ''), $atts);
    $gm = sanitize_key($atts['gmina']);
    $G = zpw_dziki_gminy();
    if ($gm !== '' && !isset($G[$gm])) $gm = '';
    wp_enqueue_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4');
    wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);
    $mapy = array();
    foreach ($G as $slug => $g) $mapy[$slug] = home_url('/dziki/' . $slug . '/');
    $cfg = wp_json_encode(array('api' => esc_url_raw(zpw_dziki_api_url()), 'gmina' => $gm, 'gminy' => $G, 'mapy' => $mapy, 'powiat' => home_url('/dziki/'), 'kontakty' => zpw_dziki_kontakty()));
    $tytul = $gm !== '' ? $G[$gm]['n'] : 'powiat wołomiński';
    ob_start(); ?>
<style>
.zgl{width:100%;min-width:0;contain:inline-size;--ink:#14213d;--mut:#5b6577;--line:#d8dee8;--acc:#0e7c6b;--acc2:#0b5e52;--navy:#14315a;--soft:#f3f6f9;max-width:min(560px,100%);margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;color:var(--ink);line-height:1.45}
.zgl *{box-sizing:border-box}.zgl [hidden]{display:none!important}
.zgl button{font-family:inherit;-webkit-tap-highlight-color:transparent}
.zgl-card{background:#fff;border:1px solid var(--line);border-radius:22px;box-shadow:0 10px 40px rgba(20,33,61,.10);overflow:hidden}
.zgl-top{padding:18px 20px 14px;background:linear-gradient(135deg,#14315a 0%,#0e7c6b 100%);color:#fff}
.zgl-top small{display:block;font-size:12.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;opacity:.8}
.zgl-top b{display:block;font-size:24px;line-height:1.2;margin:2px 0 14px}
.zgl-prog{display:grid;grid-template-columns:repeat(3,1fr);gap:6px}
.zgl-prog span{font-size:12.5px;font-weight:600;opacity:.6;transition:opacity .3s}
.zgl-prog span:before{content:"";display:block;height:5px;border-radius:5px;background:rgba(255,255,255,.28);margin-bottom:6px;background-image:linear-gradient(#fff,#fff);background-repeat:no-repeat;background-size:0 100%;transition:background-size .45s cubic-bezier(.2,.8,.2,1)}
.zgl-prog span.on{opacity:1}.zgl-prog span.on:before{background-size:100% 100%}
.zgl-body{padding:20px}
.zgl-step{animation:zglIn .35s cubic-bezier(.2,.8,.2,1)}
@keyframes zglIn{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
.zgl h3{font-size:20px;margin:0 0 4px;color:var(--ink)}.zgl p.s{margin:0 0 16px;color:var(--mut);font-size:15px}
.zgl-loc{position:relative;display:flex;align-items:center;gap:14px;width:100%;border:0;border-radius:18px;background:var(--acc);color:#fff;padding:18px;font-size:18px;font-weight:700;text-align:left;cursor:pointer;box-shadow:0 8px 22px rgba(14,124,107,.35);transition:transform .15s}
.zgl-loc:active{transform:scale(.98)}
.zgl-loc i{flex:none;position:relative;width:46px;height:46px;border-radius:50%;background:rgba(255,255,255,.18);display:grid;place-items:center;font-style:normal;font-size:22px}
.zgl-loc i:after{content:"";position:absolute;inset:0;border-radius:50%;border:2px solid rgba(255,255,255,.7);animation:zglPulse 1.8s ease-out infinite}
@keyframes zglPulse{from{transform:scale(1);opacity:.9}to{transform:scale(1.9);opacity:0}}
.zgl-loc small{display:block;font-size:13.5px;font-weight:500;opacity:.9}
.zgl-loc.busy i:after{animation-duration:.7s}
.zgl-alt{display:block;width:100%;margin-top:10px;border:1px solid var(--line);background:#fff;border-radius:14px;padding:14px;font-size:16px;font-weight:600;color:var(--navy);cursor:pointer}
.zgl-mapw{position:relative;height:min(52vh,420px);min-height:300px;border-radius:16px;overflow:hidden;border:1px solid var(--line);background:#eef1f5;margin-bottom:12px}
.zgl-map{position:absolute;inset:0}
.zgl-pin{position:absolute;left:50%;top:50%;width:40px;height:52px;margin:-52px 0 0 -20px;z-index:500;pointer-events:none;transition:transform .18s cubic-bezier(.2,.8,.2,1);filter:drop-shadow(0 6px 6px rgba(20,33,61,.35))}
.zgl-pin.up{transform:translateY(-12px)}
.zgl-dot{position:absolute;left:50%;top:50%;width:10px;height:4px;margin:-2px 0 0 -5px;border-radius:50%;background:rgba(20,33,61,.35);z-index:499;pointer-events:none}
.zgl-me{position:absolute;right:10px;bottom:10px;z-index:600;width:48px;height:48px;border-radius:14px;border:0;background:#fff;box-shadow:0 2px 10px rgba(20,33,61,.3);font-size:21px;cursor:pointer;color:var(--navy)}
.zgl-tip{position:absolute;left:50%;top:10px;transform:translateX(-50%);z-index:600;background:rgba(20,33,61,.9);color:#fff;font-size:13.5px;font-weight:600;padding:7px 13px;border-radius:999px;white-space:nowrap;pointer-events:none}
.zgl-where{display:flex;align-items:center;gap:12px;background:var(--soft);border-radius:14px;padding:12px 14px;margin-bottom:14px;min-height:62px}
.zgl-where i{flex:none;width:38px;height:38px;border-radius:12px;background:#fff;display:grid;place-items:center;font-style:normal;font-size:19px}
.zgl-where b{display:block;font-size:16px}.zgl-where span{font-size:13.5px;color:var(--mut)}
.zgl-where.zle{background:#fdecea}.zgl-where.zle b{color:#b3261e}
.zgl-btns{display:flex;gap:10px;margin-top:6px}
.zgl-btns button{flex:1;border-radius:14px;padding:15px 10px;font-size:17px;font-weight:700;cursor:pointer;transition:transform .12s,opacity .2s}
.zgl-btns button:active{transform:scale(.98)}
.zgl-go{border:0;background:var(--acc);color:#fff;box-shadow:0 6px 16px rgba(14,124,107,.3)}.zgl-go:disabled{opacity:.45;box-shadow:none;cursor:default}
.zgl-back{flex:0 0 auto!important;min-width:54px;border:1px solid var(--line);background:#fff;color:var(--ink)}
.zgl-tiles{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px}
.zgl-tile{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;min-height:118px;border:1.5px dashed #b6bfcc;border-radius:18px;background:var(--soft);font-size:16px;font-weight:700;color:var(--navy);cursor:pointer;text-align:center;padding:12px;transition:border-color .2s,background .2s}
.zgl-tile:hover{border-color:var(--acc);background:#e9f5f2}
.zgl-tile i{font-style:normal;font-size:32px;line-height:1}
.zgl-gal{grid-column:1/-1;min-height:0;flex-direction:row;padding:14px;font-size:15.5px}.zgl-gal i{font-size:20px}
.zgl-prev{position:relative;border-radius:18px;overflow:hidden;background:#0b1526;margin-bottom:10px}
.zgl-prev img,.zgl-prev video{display:block;width:100%;max-height:340px;object-fit:contain}
.zgl-prev button{position:absolute;top:10px;right:10px;border:0;border-radius:999px;background:rgba(11,21,38,.75);color:#fff;font-size:14px;font-weight:700;padding:8px 14px;cursor:pointer}
.zgl-prev em{position:absolute;left:10px;bottom:10px;font-style:normal;background:rgba(14,124,107,.95);color:#fff;font-size:13px;font-weight:700;padding:6px 12px;border-radius:999px}
.zgl-note{font-size:13.5px;color:var(--mut);margin:0 0 14px}
.zgl-lab{font-size:12.5px;font-weight:700;color:var(--mut);text-transform:uppercase;letter-spacing:.06em;margin:14px 0 8px}
.zgl-lab:first-of-type{margin-top:0}
.zgl-ch{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
.zgl-ch.t3{grid-template-columns:repeat(3,1fr)}
.zgl-ch button{display:flex;flex-direction:column;align-items:center;gap:4px;border:1.5px solid var(--line);background:#fff;border-radius:14px;padding:11px 2px;font-size:13.5px;font-weight:700;color:var(--ink);cursor:pointer;transition:all .15s}
.zgl-ch button i{font-style:normal;font-size:22px;line-height:1.1}
.zgl-ch button.on{border-color:var(--navy);background:var(--navy);color:#fff;transform:translateY(-2px);box-shadow:0 6px 14px rgba(20,49,90,.3)}
.zgl-ch.t3 button{padding:12px 2px;font-size:14px}
.zgl-sumc{display:flex;gap:12px;align-items:center;border:1px solid var(--line);border-radius:14px;padding:10px 12px;margin:16px 0 4px;font-size:14.5px}
.zgl-sumc img{width:52px;height:52px;border-radius:10px;object-fit:cover;flex:none}
.zgl-sumc .ph{width:52px;height:52px;border-radius:10px;background:var(--soft);display:grid;place-items:center;font-size:22px;flex:none}
.zgl-sumc span{color:var(--mut);display:block;font-size:13px}
.zgl-err{color:#b3261e;font-size:14.5px;font-weight:600;min-height:20px;margin:8px 0 0}
.zgl-spin{display:inline-block;width:18px;height:18px;border:3px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:zglSp .7s linear infinite;vertical-align:-3px;margin-right:8px}
@keyframes zglSp{to{transform:rotate(360deg)}}
.zgl-done{text-align:center}
.zgl-ok{width:84px;height:84px;margin:6px auto 12px;border-radius:50%;background:var(--acc);display:grid;place-items:center;animation:zglPop .5s cubic-bezier(.2,1.6,.4,1);box-shadow:0 0 0 12px rgba(14,124,107,.12)}
.zgl-ok svg{width:42px;height:42px;stroke:#fff;stroke-width:4;fill:none;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:60;stroke-dashoffset:60;animation:zglDraw .5s .25s ease-out forwards}
@keyframes zglPop{from{transform:scale(.3);opacity:0}to{transform:none;opacity:1}}@keyframes zglDraw{to{stroke-dashoffset:0}}
.zgl-urz{text-align:left;background:var(--soft);border-radius:18px;padding:16px;margin:18px 0 12px}
.zgl-urz b{font-size:17px}.zgl-urz p{margin:4px 0 12px;font-size:14.5px;color:var(--mut)}
.zgl-act{display:flex;align-items:center;gap:12px;width:100%;border:1px solid var(--line);background:#fff;border-radius:14px;padding:13px 14px;margin-top:8px;font-size:16px;font-weight:700;color:var(--ink);text-decoration:none;cursor:pointer;text-align:left}
.zgl-act i{flex:none;width:38px;height:38px;border-radius:12px;background:var(--soft);display:grid;place-items:center;font-style:normal;font-size:19px}
.zgl-act span{display:block;font-size:13.5px;font-weight:500;color:var(--mut)}
.zgl-act.main{background:var(--navy);border-color:var(--navy);color:#fff}.zgl-act.main i{background:rgba(255,255,255,.15)}.zgl-act.main span{color:rgba(255,255,255,.8)}
.zgl-sos{display:flex;align-items:center;gap:12px;background:#fff7e0;border-radius:16px;padding:12px 16px;margin:14px 0;font-size:14.5px}
.zgl-sos a{flex:none;background:#b3261e;color:#fff;font-weight:800;text-decoration:none;border-radius:12px;padding:10px 16px;font-size:17px}
.zgl-foot{font-size:13px;color:var(--mut);margin:12px 4px}
.zgl-foot a{color:var(--acc2)}
@media(max-width:420px){.zgl-body{padding:16px}.zgl-top b{font-size:21px}.zgl-ch button{font-size:12.5px}}
@media(prefers-reduced-motion:reduce){.zgl *{animation:none!important;transition:none!important}}
</style>
<div class="zgl" id="zgl">
  <div class="zgl-card">
    <div class="zgl-top">
      <small>Zgłoś dziki</small>
      <b id="zgl-tyt"><?php echo esc_html($tytul); ?></b>
      <div class="zgl-prog"><span class="on" data-p="1">1. Miejsce</span><span data-p="2">2. Zdjęcie</span><span data-p="3">3. Zgłoszenie</span></div>
    </div>
    <div class="zgl-body">
      <div class="zgl-step" id="zgl-s1a">
        <h3>Gdzie są dziki?</h3>
        <p class="s">Jedno dotknięcie wystarczy. Miejsce poprawisz na mapie.</p>
        <button type="button" class="zgl-loc" id="zgl-gps"><i>◎</i><span>Zlokalizuj mnie<small id="zgl-gps-s">Dziki są tu, gdzie stoję</small></span></button>
        <button type="button" class="zgl-alt" id="zgl-manual">Wskażę miejsce na mapie</button>
      </div>
      <div class="zgl-step" id="zgl-s1b" hidden>
        <div class="zgl-mapw"><div class="zgl-map" id="zgl-map"></div><div class="zgl-dot"></div>
          <svg class="zgl-pin" id="zgl-pin" viewBox="0 0 40 52"><path d="M20 0C9 0 0 8.7 0 19.5 0 34 20 52 20 52s20-18 20-32.5C40 8.7 31 0 20 0z" fill="#b3261e"/><circle cx="20" cy="19" r="8" fill="#fff"/></svg>
          <div class="zgl-tip" id="zgl-tip">Przesuń mapę, aby ustawić pinezkę</div>
          <button type="button" class="zgl-me" id="zgl-me" aria-label="Pokaż moje położenie">◎</button>
        </div>
        <div class="zgl-where" id="zgl-where"><i>📍</i><div><b id="zgl-adr">Ustalam miejsce…</b><span id="zgl-gm"></span></div></div>
        <div class="zgl-btns"><button type="button" class="zgl-back" id="zgl-b1" aria-label="Wstecz">←</button><button type="button" class="zgl-go" id="zgl-n1">To tutaj, dalej</button></div>
      </div>
      <div class="zgl-step" id="zgl-s2" hidden>
        <h3>Masz zdjęcie lub film?</h3>
        <p class="s">Nieobowiązkowo. Rób je tylko z bezpiecznej odległości.</p>
        <div id="zgl-pick">
          <div class="zgl-tiles">
            <label class="zgl-tile"><input type="file" accept="image/*" capture="environment" hidden data-m><i>📷</i>Zrób zdjęcie</label>
            <label class="zgl-tile"><input type="file" accept="video/*" capture="environment" hidden data-m><i>🎥</i>Nagraj film</label>
            <label class="zgl-tile zgl-gal"><input type="file" accept="image/*,video/*" hidden data-m><i>🖼</i>Wybierz z telefonu</label>
          </div>
        </div>
        <div class="zgl-prev" id="zgl-prev" hidden><div id="zgl-prev-m"></div><em id="zgl-prev-t"></em><button type="button" id="zgl-rm">Usuń</button></div>
        <p class="zgl-note">Zdjęcie lub film pojawi się na mapie po sprawdzeniu przez redakcję. Nie wysyłaj nagrań, na których widać ludzi. Film: do 40 MB, około 20 sekund.</p>
        <div class="zgl-err" id="zgl-e2"></div>
        <div class="zgl-btns"><button type="button" class="zgl-back" id="zgl-b2" aria-label="Wstecz">←</button><button type="button" class="zgl-go" id="zgl-n2">Pomiń</button></div>
      </div>
      <div class="zgl-step" id="zgl-s3" hidden>
        <h3>Ostatni krok</h3>
        <p class="s">Trzy dotknięcia i gotowe.</p>
        <div class="zgl-lab">Ile?</div>
        <div class="zgl-ch" id="zgl-ile"><button type="button" data-v="1 dzik"><i>🐗</i>1</button><button type="button" data-v="2–5 dzików" class="on"><i>🐗🐗</i>2–5</button><button type="button" data-v="więcej niż 5 dzików"><i>🐗＋</i>ponad 5</button><button type="button" data-v="locha z młodymi"><i>🐖</i>z młodymi</button></div>
        <div class="zgl-lab">Co robią?</div>
        <div class="zgl-ch" id="zgl-co"><button type="button" data-v="przechodzą" class="on"><i>🚶</i>przechodzą</button><button type="button" data-v="żerują, ryją"><i>🌱</i>ryją</button><button type="button" data-v="są przy śmietnikach"><i>🗑</i>śmietniki</button><button type="button" data-v="zachowują się agresywnie"><i>⚠️</i>agresywne</button></div>
        <div class="zgl-lab">Kiedy?</div>
        <div class="zgl-ch t3" id="zgl-kiedy"><button type="button" data-v="0" class="on">teraz</button><button type="button" data-v="60">godzinę temu</button><button type="button" data-v="180">dziś wcześniej</button></div>
        <div class="zgl-sumc"><div id="zgl-sum-m"></div><div><b id="zgl-sum-a"></b><span id="zgl-sum-g"></span></div></div>
        <div class="zgl-err" id="zgl-e3"></div>
        <div class="zgl-btns"><button type="button" class="zgl-back" id="zgl-b3" aria-label="Wstecz">←</button><button type="button" class="zgl-go" id="zgl-send">Zgłoś dziki</button></div>
      </div>
      <div class="zgl-step zgl-done" id="zgl-s4" hidden>
        <div class="zgl-ok"><svg viewBox="0 0 48 48"><path d="M12 25l9 9 16-19"/></svg></div>
        <h3>Dziękujemy! Zgłoszenie jest na mapie</h3>
        <p class="s" id="zgl-done-s"></p>
        <div class="zgl-urz" id="zgl-urz"></div>
        <a class="zgl-act" id="zgl-mapa" href="#"><i>🗺</i><div>Zobacz mapę obserwacji<span id="zgl-mapa-s"></span></div></a>
        <button type="button" class="zgl-act" id="zgl-share"><i>↗</i><div>Ostrzeż sąsiadów<span>Wyślij link do mapy</span></div></button>
        <button type="button" class="zgl-act" id="zgl-again"><i>＋</i><div>Zgłoś kolejną obserwację</div></button>
      </div>
    </div>
  </div>
  <div class="zgl-sos"><a href="tel:112">112</a><div><b>Dziki atakują, są ranne albo blokują drogę?</b><br>Nie podchodź. Najpierw zadzwoń.</div></div>
  <p class="zgl-foot">Zgłoszenie jest anonimowe: zapisujemy miejsce, czas, liczbę i zachowanie dzików. Obserwacje znikają z mapy po <?php echo (int) ZPW_DZIKI_DNI; ?> dniach. To zgłoszenia mieszkańców, a nie oficjalne dane urzędów. <a href="<?php echo esc_url(home_url('/dziki/')); ?>">Mapa całego powiatu</a></p>
</div>
<script>
(function () {
  var C = <?php echo $cfg; ?>, G = C.gminy, K = C.kontakty || {};
  function $(i) { return document.getElementById(i); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; }); }
  var S = {ll: null, gm: null, adr: '', media: null, url: null, film: false}, map = null, tGeo = null, nGeo = 0;

  function krok(id, p) {
    ['zgl-s1a', 'zgl-s1b', 'zgl-s2', 'zgl-s3', 'zgl-s4'].forEach(function (x) { $(x).hidden = x !== id; });
    [].forEach.call(document.querySelectorAll('.zgl-prog span'), function (s) { s.classList.toggle('on', +s.dataset.p <= p); });
    var r = $('zgl').getBoundingClientRect(); if (r.top < 0) window.scrollBy({top: r.top - 12, behavior: 'smooth'});
  }
  function wGminie(lat, lon) {
    for (var s in G) { var b = G[s].b; if (lat < b[0] || lat > b[2] || lon < b[1] || lon > b[3]) continue;
      var P = G[s].p; for (var k = 0; k < P.length; k++) { var r = P[k], inx = false;
        for (var i = 0, j = r.length - 1; i < r.length; j = i++) { if ((r[i][1] > lon) !== (r[j][1] > lon) && lat < (r[j][0] - r[i][0]) * (lon - r[i][1]) / (r[j][1] - r[i][1]) + r[i][0]) inx = !inx; }
        if (inx) return s; } }
    return null;
  }
  function miejsce() {
    var c = map.getCenter(); S.ll = [+c.lat.toFixed(5), +c.lng.toFixed(5)]; S.gm = wGminie(c.lat, c.lng);
    var w = $('zgl-where'); w.classList.toggle('zle', !S.gm); $('zgl-n1').disabled = !S.gm;
    if (!S.gm) { $('zgl-adr').textContent = 'To miejsce jest poza powiatem wołomińskim'; $('zgl-gm').textContent = 'Przesuń mapę na teren powiatu.'; return; }
    $('zgl-gm').textContent = 'Gmina ' + G[S.gm].n; $('zgl-tyt').textContent = G[S.gm].n;
    S.adr = ''; $('zgl-adr').textContent = 'Wybrane miejsce';
    clearTimeout(tGeo); var n = ++nGeo, ll = S.ll;
    // nazwa ulicy tylko do podpowiedzi i do e-maila; na serwer trafiają same współrzędne
    tGeo = setTimeout(function () {
      fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=18&addressdetails=1&accept-language=pl&lat=' + ll[0] + '&lon=' + ll[1]).then(function (r) { return r.json(); }).then(function (j) {
        if (n !== nGeo || !j || !j.address) return; var a = j.address, u = a.road || a.pedestrian || a.footway || a.path || '', m = a.city || a.town || a.village || a.hamlet || a.suburb || '';
        var t = u ? ((/^(aleja|al\.|plac|rondo|skwer|osiedle|droga)/i.test(u) ? '' : 'ul. ') + u + (m ? ', ' + m : '')) : (m ? 'okolice: ' + m : '');
        if (t) { S.adr = t; $('zgl-adr').textContent = t; }
      }).catch(function () {});
    }, 900);
  }
  function mapa(lat, lon, zoom) {
    krok('zgl-s1b', 1);
    if (!map) {
      map = L.map('zgl-map', {zoomControl: true, attributionControl: true, maxZoom: 19, minZoom: 9});
      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '© OpenStreetMap'}).addTo(map);
      for (var s in G) L.polygon(G[s].p, {color: '#14315a', weight: 1.5, fill: false, opacity: .45, interactive: false}).addTo(map);
      map.on('movestart', function () { $('zgl-pin').classList.add('up'); $('zgl-tip').hidden = true; });
      map.on('moveend', function () { $('zgl-pin').classList.remove('up'); miejsce(); });
    }
    setTimeout(function () { map.invalidateSize(); map.setView([lat, lon], zoom, {animate: false}); miejsce(); }, 60);
  }
  function srodek() { if (C.gmina && G[C.gmina]) { var b = G[C.gmina].b; return [(b[0] + b[2]) / 2, (b[1] + b[3]) / 2, 14]; } return [52.345, 21.24, 11]; }
  function gps(bt, potem, blad) {
    if (!navigator.geolocation) { blad(); return; }
    bt.disabled = true; bt.classList.add('busy');
    navigator.geolocation.getCurrentPosition(function (p) { bt.disabled = false; bt.classList.remove('busy'); potem(p.coords.latitude, p.coords.longitude); },
      function () { bt.disabled = false; bt.classList.remove('busy'); blad(); }, {enableHighAccuracy: true, timeout: 12000, maximumAge: 15000});
  }
  $('zgl-gps').onclick = function () { $('zgl-gps-s').textContent = 'Szukam Twojego położenia…';
    gps(this, function (a, b) { $('zgl-gps-s').textContent = 'Dziki są tu, gdzie stoję'; mapa(a, b, 18); $('zgl-tip').textContent = 'Popraw, jeśli dziki są obok'; $('zgl-tip').hidden = false; },
      function () { $('zgl-gps-s').textContent = 'Nie udało się ustalić położenia. Wskaż miejsce na mapie.'; var c = srodek(); mapa(c[0], c[1], c[2]); }); };
  $('zgl-manual').onclick = function () { var c = srodek(); mapa(c[0], c[1], c[2]); $('zgl-tip').textContent = 'Przesuń mapę, aby ustawić pinezkę'; $('zgl-tip').hidden = false; };
  $('zgl-me').onclick = function () { gps(this, function (a, b) { map.setView([a, b], 18); }, function () { $('zgl-tip').textContent = 'Nie udało się ustalić położenia'; $('zgl-tip').hidden = false; }); };
  $('zgl-b1').onclick = function () { krok('zgl-s1a', 1); };
  $('zgl-n1').onclick = function () { if (S.gm) krok('zgl-s2', 2); };

  function bezMedia() { if (S.url) URL.revokeObjectURL(S.url); S.media = null; S.url = null; $('zgl-prev').hidden = true; $('zgl-pick').hidden = false; $('zgl-prev-m').innerHTML = ''; $('zgl-n2').textContent = 'Pomiń'; [].forEach.call(document.querySelectorAll('.zgl [data-m]'), function (i) { i.value = ''; }); }
  function jestMedia(blob, nazwa, film) { if (S.url) URL.revokeObjectURL(S.url); S.media = {blob: blob, nazwa: nazwa}; S.film = film; S.url = URL.createObjectURL(blob);
    $('zgl-prev-m').innerHTML = film ? '<video src="' + S.url + '" controls muted playsinline preload="metadata"></video>' : '<img alt="Podgląd zdjęcia" src="' + S.url + '">';
    $('zgl-prev-t').textContent = film ? '✓ Film dodany' : '✓ Zdjęcie dodane'; $('zgl-prev').hidden = false; $('zgl-pick').hidden = true; $('zgl-n2').textContent = 'Dalej'; }
  [].forEach.call(document.querySelectorAll('.zgl [data-m]'), function (inp) { inp.onchange = function () { var f = this.files[0]; $('zgl-e2').textContent = ''; if (!f) return;
    if (f.type.indexOf('video') === 0) { if (f.size > 40 * 1048576) { bezMedia(); $('zgl-e2').textContent = 'Film jest za duży (najwyżej 40 MB, około 20 sekund). Nagraj krótszy.'; return; } jestMedia(f, f.name || 'dziki.mp4', true); return; }
    // zdjęcie zmniejszamy w telefonie: szybciej się wysyła i nie zawiera danych o aparacie ani położeniu
    var img = new Image(), u = URL.createObjectURL(f);
    img.onload = function () { var k = Math.min(1, 1600 / Math.max(img.width, img.height)), c = document.createElement('canvas'); c.width = Math.round(img.width * k); c.height = Math.round(img.height * k); c.getContext('2d').drawImage(img, 0, 0, c.width, c.height); URL.revokeObjectURL(u); c.toBlob(function (b) { if (b) jestMedia(b, 'dziki.jpg', false); else $('zgl-e2').textContent = 'Nie udało się odczytać zdjęcia.'; }, 'image/jpeg', .82); };
    img.onerror = function () { URL.revokeObjectURL(u); bezMedia(); $('zgl-e2').textContent = 'Nie udało się odczytać zdjęcia. Spróbuj innego.'; }; img.src = u; }; });
  $('zgl-rm').onclick = bezMedia;
  $('zgl-b2').onclick = function () { krok('zgl-s1b', 1); setTimeout(function () { map.invalidateSize(); }, 60); };
  $('zgl-n2').onclick = function () {
    $('zgl-sum-m').innerHTML = S.media && !S.film ? '<img alt="" src="' + S.url + '">' : '<div class="ph">' + (S.media ? '🎥' : '📍') + '</div>';
    $('zgl-sum-a').textContent = S.adr || 'Wybrane miejsce'; $('zgl-sum-g').textContent = 'Gmina ' + G[S.gm].n + (S.media ? (S.film ? ' · z filmem' : ' · ze zdjęciem') : '');
    $('zgl-e3').textContent = ''; krok('zgl-s3', 3); };
  ['zgl-ile', 'zgl-co', 'zgl-kiedy'].forEach(function (id) { [].forEach.call($(id).children, function (b) { b.onclick = function () { [].forEach.call($(id).children, function (x) { x.classList.toggle('on', x === b); }); }; }); });
  function wyb(id) { return $(id).querySelector('.on').dataset.v; }
  $('zgl-b3').onclick = function () { krok('zgl-s2', 2); };

  function gotowe(o, blad) {
    var g = G[o.gm], k = K[o.gm] || {}, h = '';
    $('zgl-done-s').textContent = blad ? 'Dodaliśmy obserwację bez pliku: ' + blad : (o.media ? 'Zdjęcie lub film pojawi się po sprawdzeniu przez redakcję.' : 'Sąsiedzi zobaczą je od razu. Zniknie samo po 14 dniach.');
    var kiedy = o.czas.toLocaleString('pl-PL', {day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit'});
    var tresc = 'Dzień dobry,\n\nzgłaszam obecność dzików na terenie gminy ' + g.n + '.\n\nMiejsce: ' + (o.adr || 'według współrzędnych') + '\nWspółrzędne: ' + o.ll[0] + ', ' + o.ll[1] + ' (https://www.google.com/maps?q=' + o.ll[0] + ',' + o.ll[1] + ')\nKiedy: ' + kiedy + '\nIle: ' + o.ile + '\nZachowanie: ' + o.co + '\n\nProszę o podjęcie działań przewidzianych w takich sytuacjach i o informację o sposobie załatwienia zgłoszenia.\n\nZ poważaniem\n(imię i nazwisko, adres)\n\nZgłoszenie przygotowane na ' + C.powiat;
    h += '<b>Powiadom też urząd</b><p>Mapa ostrzega sąsiadów, ale to urząd może zlecić odłów albo płoszenie. ' + (k.dziki ? esc(k.dziki) : 'Wyślij gotowe zgłoszenie jednym dotknięciem.') + '</p>';
    if (k.email) h += '<a class="zgl-act main" href="mailto:' + esc(k.email) + '?subject=' + encodeURIComponent('Zgłoszenie: dziki, ' + (o.adr || g.n)) + '&body=' + encodeURIComponent(tresc) + '"><i>✉</i><div>Wyślij gotowy e-mail<span>' + esc(k.urzad || 'Urząd') + (o.media ? ' · zdjęcie dołącz w wiadomości' : '') + '</span></div></a>';
    if (k.straz) h += '<a class="zgl-act" href="tel:' + esc(String(k.straz).replace(/[^\d+]/g, '')) + '"><i>🛡</i><div>Straż Miejska: ' + esc(k.straz) + '<span>Gdy dziki są teraz między domami</span></div></a>';
    if (k.tel) h += '<a class="zgl-act" href="tel:' + esc(String(k.tel).replace(/[^\d+]/g, '')) + '"><i>☎</i><div>' + esc(k.urzad || 'Urząd') + ': ' + esc(k.tel) + '<span>W godzinach pracy urzędu</span></div></a>';
    if (!k.email && !k.tel && !k.straz) h += '<div class="zgl-act"><i>☎</i><div>Zadzwoń do urzędu gminy ' + esc(g.n) + '<span>Numer znajdziesz na stronie urzędu</span></div></div>';
    $('zgl-urz').innerHTML = h;
    $('zgl-mapa').href = C.mapy[o.gm] || C.powiat; $('zgl-mapa-s').textContent = 'Gmina ' + g.n;
    $('zgl-share').onclick = function () { var d = {title: 'Dziki: ' + g.n, text: 'Uwaga, dziki' + (o.adr ? ' (' + o.adr + ')' : ' w gminie ' + g.n) + '. Mapa obserwacji:', url: C.mapy[o.gm] || C.powiat};
      if (navigator.share) navigator.share(d).catch(function () {}); else if (navigator.clipboard) navigator.clipboard.writeText(d.text + ' ' + d.url).then(function () { $('zgl-share').querySelector('span').textContent = 'Skopiowano link'; }); };
    krok('zgl-s4', 3);
  }
  $('zgl-send').onclick = function () {
    var bt = this, e = $('zgl-e3'); if (!S.ll || !S.gm) { krok('zgl-s1b', 1); return; }
    var czas = new Date(Date.now() - (+wyb('zgl-kiedy')) * 60000), o = {ll: S.ll, gm: S.gm, adr: S.adr, ile: wyb('zgl-ile'), co: wyb('zgl-co'), czas: czas, media: !!S.media};
    var fd = new FormData(); fd.append('lat', o.ll[0]); fd.append('lon', o.ll[1]); fd.append('ile', o.ile); fd.append('co', o.co); fd.append('kiedy', czas.toISOString()); fd.append('zrodlo', 'zyciepw'); if (S.media) fd.append('plik', S.media.blob, S.media.nazwa);
    bt.disabled = true; bt.innerHTML = '<i class="zgl-spin"></i>Wysyłam…'; e.textContent = '';
    fetch(C.api, {method: 'POST', body: fd}).then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return {ok: r.ok, j: j}; }); }).then(function (x) {
      bt.disabled = false; bt.textContent = 'Zgłoś dziki';
      if (x.ok) { if (x.j.gmina && G[x.j.gmina]) o.gm = x.j.gmina; o.media = o.media && !x.j.media_blad; gotowe(o, x.j.media_blad || ''); bezMedia(); }
      else e.textContent = x.j.message || 'Nie udało się wysłać zgłoszenia. Spróbuj ponownie.';
    }).catch(function () { bt.disabled = false; bt.textContent = 'Zgłoś dziki'; e.textContent = 'Brak połączenia z internetem. Spróbuj ponownie za chwilę.'; });
  };
  $('zgl-again').onclick = function () { bezMedia(); S.adr = ''; krok('zgl-s1a', 1); };
})();
</script>
<?php
    return ob_get_clean();
});
