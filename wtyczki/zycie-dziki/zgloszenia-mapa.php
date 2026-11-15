<?php
/**
 * Mapa zgłoszeń mieszkańców (dziury, awarie…) z licznikami: [zycie_zgloszenia_mapa gmina="kobylka"]
 * Dane: mini-serwer /mieszkaniec-api/?zasob=zgloszenia (te same co w aplikacjach mieszkańców).
 * Jeśli na stronie jest formularz redakcji [zr_zglos], po jego udanym wysłaniu kategoria i miejsce
 * trafiają też anonimowo na tę mapę (mieszkaniec może to odznaczyć). Imię, e-mail i opis nie są wysyłane.
 */
if (!defined('ABSPATH')) exit;

add_shortcode('zycie_zgloszenia_mapa', function ($atts) {
    $atts = shortcode_atts(array('gmina' => ''), $atts);
    $G = zpw_dziki_gminy();
    $gm = sanitize_key($atts['gmina']);
    if ($gm !== '' && !isset($G[$gm])) $gm = '';
    wp_enqueue_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4');
    wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);
    $cfg = wp_json_encode(array('api' => esc_url_raw(home_url('/mieszkaniec-api/?zasob=zgloszenia')), 'gmina' => $gm, 'gminy' => $G, 'nazwa' => $gm !== '' ? $G[$gm]['n'] : 'powiat wołomiński'));
    ob_start(); ?>
<style>
.zgm{--ink:#14213d;--mut:#5b6577;--line:#d8dee8;--acc:#0e7c6b;--soft:#f3f6f9;width:100%;min-width:0;contain:inline-size;max-width:min(980px,100%);margin:0 auto 28px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;color:var(--ink);line-height:1.45}
.zgm *{box-sizing:border-box}.zgm [hidden]{display:none!important}.zgm button{font-family:inherit}
.zgm-hero{display:flex;justify-content:space-between;align-items:flex-end;gap:14px;background:linear-gradient(135deg,#14315a,#0e7c6b);color:#fff;border-radius:20px;padding:18px 20px;margin-bottom:12px}
.zgm-hero small{display:block;font-size:12.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;opacity:.85}
.zgm-hero b{display:block;font-size:42px;line-height:1.05;margin:4px 0}.zgm-hero span{font-size:14px;opacity:.95}
.zgm-st{text-align:right;max-width:50%}.zgm-st b{font-size:22px}
.zgm-bar{display:flex;gap:8px;overflow-x:auto;max-width:100%;padding:2px 0 10px}
.zgm-bar button{flex:none;display:flex;align-items:center;gap:6px;border:1.5px solid var(--line);background:#fff;color:var(--ink);border-radius:999px;padding:8px 14px;font-size:14px;font-weight:700;cursor:pointer}
.zgm-bar button i{width:10px;height:10px;border-radius:50%;background:var(--k,transparent)}.zgm-bar button.on{background:var(--ink);border-color:var(--ink);color:#fff}
.zgm-seg{display:grid;grid-template-columns:1fr 1fr;gap:4px;background:var(--soft);border-radius:12px;padding:4px;margin-bottom:10px}
.zgm-seg button{border:0;background:none;border-radius:9px;padding:10px;font-size:14.5px;font-weight:700;color:var(--mut);cursor:pointer}.zgm-seg button.on{background:#fff;color:var(--ink);box-shadow:0 1px 3px rgba(20,33,61,.15)}
.zgm-map{height:min(56vh,440px);min-height:300px;border-radius:16px;overflow:hidden;border:1px solid var(--line);background:#eef1f5}
.zgm-pt{display:grid;place-items:center;border-radius:50%;color:#fff;font:800 12px system-ui;border:2.5px solid #fff;box-shadow:0 2px 6px rgba(20,33,61,.35)}
.zgm-pt.fix{background:#fff!important;color:#0e7c6b;border-color:#0e7c6b}
.zgm-cta{display:block;width:100%;border:0;border-radius:14px;background:var(--acc);color:#fff;font-size:17px;font-weight:700;padding:15px;margin:12px 0;cursor:pointer;text-align:center;text-decoration:none!important}
.zgm-k{background:#fff;border:2px solid transparent;border-radius:18px;padding:14px;margin:10px 0;box-shadow:0 1px 2px rgba(20,33,61,.06),0 4px 16px rgba(20,33,61,.05);cursor:pointer}.zgm-k.on{border-color:var(--acc)}
.zgm-h{display:flex;gap:12px;align-items:center}.zgm-h i{flex:none;width:40px;height:40px;border-radius:12px;display:grid;place-items:center;font-style:normal;font-size:20px}.zgm-h b{display:block;font-size:15.5px}.zgm-h span{display:block;font-size:13px;color:var(--mut)}
.zgm-l{display:grid;grid-template-columns:1.4fr 1fr;gap:8px;margin:12px 0 8px}.zgm-l>div{background:var(--soft);border-radius:12px;padding:10px 12px}.zgm-l b{display:block;font-size:20px;line-height:1.15}.zgm-l span{font-size:12px;color:var(--mut)}
.zgm-fix{margin:10px 0 6px;color:#0e7c6b;font-weight:700;font-size:14px}.zgm-op{margin:6px 0;font-size:14.5px}.zgm-img{display:block;width:100%;max-height:240px;object-fit:cover;border-radius:12px;margin:8px 0}
.zgm-m{font-size:12.5px;color:var(--mut)}
.zgm-b{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:10px}.zgm-b button{border:1px solid var(--line);background:#fff;color:var(--ink);border-radius:12px;padding:10px 4px;font-size:14px;font-weight:700;cursor:pointer;min-height:44px}.zgm-b button:first-child{background:var(--acc);border-color:var(--acc);color:#fff}.zgm-b button:disabled{opacity:.6;cursor:default}
.zgm-e{color:var(--mut);text-align:center;padding:18px}.zgm-msg{position:sticky;bottom:12px;z-index:5;margin:8px auto;max-width:520px;background:#14213d;color:#fff;border-radius:12px;padding:10px 14px;font-size:14.5px;font-weight:600;text-align:center}
.zgm-note{font-size:13px;color:var(--mut);margin:10px 2px}
.zgm-pub{display:flex;gap:10px;align-items:flex-start;background:#e9f5f2;border-radius:12px;padding:10px 12px;margin:10px 0;font-size:14px;line-height:1.35}.zgm-pub input{width:20px;height:20px;flex:none;margin-top:1px;accent-color:#0e7c6b}
@media(max-width:420px){.zgm-hero b{font-size:36px}.zgm-l b{font-size:18px}}
</style>
<div class="zgm" id="zgm">
  <div class="zgm-hero" id="zgm-hero"><div><small>Wczytuję zgłoszenia…</small></div></div>
  <div class="zgm-seg" id="zgm-st"><button type="button" data-st="otwarte" class="on">Czekają na naprawę</button><button type="button" data-st="naprawione">Naprawione</button></div>
  <div class="zgm-bar" id="zgm-kat"></div>
  <div class="zgm-map" id="zgm-map"></div>
  <a class="zgm-cta" id="zgm-cta" href="#zglos-nowa">＋ Zgłoś nową sprawę</a>
  <div id="zgm-lista"></div>
  <div class="zgm-msg" id="zgm-msg" hidden></div>
  <p class="zgm-note">Licznik pokazuje czas od pierwszego zgłoszenia na tej mapie (z portalu i z aplikacji mieszkańców), a nie z rejestru urzędu. Zgłoszenia z tego samego miejsca łączą się w jeden punkt. Sprawa trafia do „Naprawionych”, gdy potwierdzą to dwie osoby albo redakcja. Opisy i zdjęcia pokazujemy po sprawdzeniu.</p>
</div>
<script>
(function () {
  var C = <?php echo $cfg; ?>;
  var K = {droga: ['🕳', 'Dziura w drodze lub chodniku', '#C2410C', 'Dziury'], oswietlenie: ['💡', 'Nie świeci latarnia', '#B45309', 'Latarnie'], woda: ['🚰', 'Woda, kanalizacja, zalana ulica', '#2563EB', 'Woda'], odpady: ['🗑', 'Śmieci, dzikie wysypisko', '#6B7280', 'Śmieci'], zielen: ['🌳', 'Zieleń, drzewa', '#15803D', 'Zieleń'], bezpieczenstwo: ['🚸', 'Znak, przejście, bezpieczeństwo', '#BE185D', 'Bezpieczeństwo'], potrzeba: ['✚', 'Potrzebna inwestycja', '#7C3AED', 'Potrzeby'], inne: ['✉', 'Inna sprawa', '#0891B2', 'Inne']};
  var ZR = {droga: 'droga', chodnik: 'droga', swiatlo: 'oswietlenie', smieci: 'odpady', ziel: 'zielen', ruch: 'bezpieczenstwo', woda: 'woda', inne: 'inne'};
  var S = [], st = 'otwarte', kat = '', wyb = null, map, warstwa, U = {};
  function $(i) { return document.getElementById(i); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; }); }
  function ls(k, v) { try { if (v === undefined) return JSON.parse(localStorage.getItem('zgm_' + k) || 'null'); localStorage.setItem('zgm_' + k, JSON.stringify(v)); } catch (e) {} return null; }
  function uid() { var u = ls('u'); if (!u) { u = Array.prototype.map.call(crypto.getRandomValues(new Uint8Array(12)), function (b) { return ('0' + b.toString(16)).slice(-2); }).join(''); ls('u', u); } return u; }
  function pl(n, a, b, c) { return n === 1 ? a : (n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 10 || n % 100 >= 20)) ? b : c; }
  function ile(od) { var m = Math.max(0, Math.floor((Date.now() - new Date(od)) / 60000)), d = Math.floor(m / 1440), h = Math.floor(m % 1440 / 60);
    return d >= 1 ? d + ' ' + (d === 1 ? 'dzień' : 'dni') + (d < 7 && h ? ' ' + h + ' godz.' : '') : h >= 1 ? h + ' godz. ' + (m % 60) + ' min' : (m % 60) + ' min'; }
  function msg(t) { var m = $('zgm-msg'); m.textContent = t; m.hidden = false; clearTimeout(msg.t); msg.t = setTimeout(function () { m.hidden = true; }, 4500); }
  function lista() { return S.filter(function (z) { return z.status === st && (!kat || z.kat === kat); }); }
  function ulica(z) {
    if (U[z.id] !== undefined) return; U[z.id] = '';
    fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=17&accept-language=pl&lat=' + z.lat + '&lon=' + z.lon).then(function (r) { return r.json(); }).then(function (j) {
      var a = j && j.address || {}, u = a.road || a.pedestrian || a.footway || '', m = a.city || a.town || a.village || '';
      U[z.id] = u ? (/^(aleja|al\.|plac|rondo|skwer|osiedle)/i.test(u) ? '' : 'ul. ') + u + (m ? ', ' + m : '') : m;
      var el = document.querySelector('[data-ul="' + z.id + '"]'); if (el) el.textContent = U[z.id];
    }).catch(function () {});
  }
  function karta(z) {
    var k = K[z.kat] || K.inne, otw = z.status === 'otwarte', tez = (ls('tez') || []).indexOf(z.id) >= 0 || (ls('moje') || []).indexOf(z.id) >= 0, nap = (ls('nap') || []).indexOf(z.id) >= 0;
    return '<div class="zgm-k' + (wyb === z.id ? ' on' : '') + '" data-id="' + esc(z.id) + '"><div class="zgm-h"><i style="background:' + k[2] + '">' + k[0] + '</i><div><b>' + esc(k[1]) + '</b><span data-ul="' + esc(z.id) + '">' + esc(U[z.id] || '') + '</span></div></div>'
      + (otw ? '<div class="zgm-l"><div><b class="zgm-c" data-od="' + esc(z.pierwsze) + '">' + ile(z.pierwsze) + '</b><span>od pierwszego zgłoszenia</span></div><div><b>' + z.n + '</b><span>' + pl(z.n, 'zgłoszenie', 'zgłoszenia', 'zgłoszeń') + '</span></div></div>'
        : '<div class="zgm-fix">✓ Naprawione ' + new Date(z.naprawione_od).toLocaleDateString('pl-PL', {day: 'numeric', month: 'long'}) + '</div>')
      + (z.opis ? '<p class="zgm-op">' + esc(z.opis) + '</p>' : '') + (z.plik ? '<img class="zgm-img" loading="lazy" alt="Zdjęcie zgłoszenia" src="' + C.api + '&plik=' + esc(z.plik) + '">' : '')
      + '<span class="zgm-m">Pierwsze zgłoszenie: ' + new Date(z.pierwsze).toLocaleDateString('pl-PL', {day: 'numeric', month: 'long', year: 'numeric'}) + (z.n > 1 ? ' · ostatnie: ' + ile(z.ostatnie) + ' temu' : '') + '</span>'
      + (otw ? '<div class="zgm-b"><button type="button" data-tez="' + esc(z.id) + '"' + (tez ? ' disabled>✓ Zgłoszone' : '>＋ Też to widzę') + '</button><button type="button" data-nap="' + esc(z.id) + '"' + (nap ? ' disabled>✓ Dziękujemy' : '>Już naprawione') + '</button></div>' : '') + '</div>';
  }
  function rysuj(dopasuj) {
    var O = S.filter(function (z) { return z.status === 'otwarte'; }), N = S.length - O.length, suma = O.reduce(function (a, z) { return a + z.n; }, 0),
        naj = O.slice().sort(function (a, b) { return a.pierwsze < b.pierwsze ? -1 : 1; })[0];
    $('zgm-hero').innerHTML = '<div><small>Czeka na naprawę · ' + esc(C.nazwa) + '</small><b>' + O.length + '</b><span>' + suma + ' ' + pl(suma, 'zgłoszenie', 'zgłoszenia', 'zgłoszeń') + ' mieszkańców' + (N ? ' · ' + N + ' naprawionych' : '') + '</span></div>'
      + (naj ? '<div class="zgm-st"><small>Najdłużej czeka</small><b class="zgm-c" data-od="' + esc(naj.pierwsze) + '">' + ile(naj.pierwsze) + '</b><span>' + esc((K[naj.kat] || K.inne)[1]) + '</span></div>' : '');
    var cnt = {}; O.forEach(function (z) { cnt[z.kat] = (cnt[z.kat] || 0) + 1; });
    $('zgm-kat').innerHTML = '<button type="button" data-k="" class="' + (!kat ? 'on' : '') + '">Wszystkie</button>' + Object.keys(K).filter(function (k) { return cnt[k] || kat === k; }).map(function (k) { return '<button type="button" data-k="' + k + '" class="' + (kat === k ? 'on' : '') + '" style="--k:' + K[k][2] + '"><i></i>' + K[k][3] + ' ' + (cnt[k] || 0) + '</button>'; }).join('');
    var L2 = lista().sort(function (a, b) { return a.pierwsze < b.pierwsze ? -1 : 1; });
    $('zgm-lista').innerHTML = L2.length ? L2.map(karta).join('') : '<p class="zgm-e">' + (st === 'otwarte' ? 'Nic nie czeka na naprawę' + (kat ? ' w tej kategorii' : '') + '. Widzisz problem? Zgłoś go jako pierwszy.' : 'Nic jeszcze nie oznaczono jako naprawione.') + '</p>';
    L2.forEach(ulica);
    warstwa.clearLayers(); var B = [];
    L2.forEach(function (z) { var k = K[z.kat] || K.inne, r = Math.round(Math.min(34, 20 + Math.sqrt(z.n) * 4));
      var m = L.marker([z.lat, z.lon], {icon: L.divIcon({className: '', html: '<div class="zgm-pt' + (z.status === 'naprawione' ? ' fix' : '') + '" style="width:' + r + 'px;height:' + r + 'px;background:' + k[2] + '">' + (z.status === 'naprawione' ? '✓' : (z.n > 1 ? z.n : k[0])) + '</div>', iconSize: [r, r], iconAnchor: [r / 2, r / 2]}), keyboard: false, title: k[1]}).addTo(warstwa);
      m.on('click', function () { wyb = z.id; rysuj(); var el = document.querySelector('.zgm-k.on'); if (el) el.scrollIntoView({behavior: 'smooth', block: 'center'}); }); B.push([z.lat, z.lon]); });
    if (dopasuj) { if (B.length) map.fitBounds(B, {padding: [40, 40], maxZoom: 16}); else if (C.gmina && C.gminy[C.gmina]) { var b = C.gminy[C.gmina].b; map.fitBounds([[b[0], b[1]], [b[2], b[3]]]); } }
  }
  function wczytaj(dop) {
    return fetch(C.api + (C.gmina ? '&gmina=' + C.gmina : ''), {cache: 'no-store'}).then(function (r) { return r.json(); }).then(function (j) { S = j.sprawy || []; rysuj(dop); })
      .catch(function () { $('zgm-hero').innerHTML = '<div><small>Mapa zgłoszeń</small><b style="font-size:22px">Brak połączenia</b><span>Spróbuj odświeżyć stronę za chwilę.</span></div>'; });
  }
  function glos(id, akcja, bt) {
    bt.disabled = true;
    fetch(C.api + '&akcja=' + akcja, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id: id, u: uid()})}).then(function (r) { return r.json().then(function (j) { return {ok: r.ok, j: j}; }); }).then(function (x) {
      if (!x.ok) { bt.disabled = false; msg(x.j.message || 'Nie udało się.'); return; }
      var kl = akcja === 'tez' ? 'tez' : 'nap', a = ls(kl) || []; a.push(id); ls(kl, a.slice(-300));
      for (var i = 0; i < S.length; i++) if (S[i].id === id) S[i] = x.j.sprawa;
      msg(x.j.juz ? 'Twój głos był już policzony.' : akcja === 'tez' ? 'Dziękujemy. Teraz ' + x.j.sprawa.n + ' ' + pl(x.j.sprawa.n, 'zgłoszenie', 'zgłoszenia', 'zgłoszeń') + '.' : x.j.sprawa.status === 'naprawione' ? 'Dziękujemy. Sprawa trafiła do naprawionych.' : 'Dziękujemy. Jeszcze jedna osoba musi potwierdzić naprawę.');
      rysuj();
    }).catch(function () { bt.disabled = false; msg('Brak połączenia.'); });
  }
  function start() {
    map = L.map('zgm-map', {scrollWheelZoom: false, zoomSnap: 0.5, maxZoom: 19}).setView([52.345, 21.24], 11);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '© OpenStreetMap'}).addTo(map);
    var gs = C.gmina ? [C.gmina] : Object.keys(C.gminy);
    gs.forEach(function (g) { L.polygon(C.gminy[g].p, {color: '#14315a', weight: 1.5, fill: false, opacity: .5, interactive: false}).addTo(map); });
    warstwa = L.layerGroup().addTo(map);
    $('zgm').addEventListener('click', function (e) {
      var b = e.target.closest('button');
      if (b) { if (b.dataset.st) { st = b.dataset.st; wyb = null; [].forEach.call($('zgm-st').children, function (x) { x.classList.toggle('on', x === b); }); rysuj(true); }
        else if (b.dataset.k !== undefined) { kat = b.dataset.k; wyb = null; rysuj(true); }
        else if (b.dataset.tez) glos(b.dataset.tez, 'tez', b); else if (b.dataset.nap) glos(b.dataset.nap, 'naprawione', b); return; }
      var k = e.target.closest('[data-id]'); if (k && !e.target.closest('a')) { var z = S.filter(function (x) { return x.id === k.dataset.id; })[0]; wyb = wyb === z.id ? null : z.id; rysuj(); if (wyb) { map.setView([z.lat, z.lon], 17); $('zgm-map').scrollIntoView({behavior: 'smooth', block: 'center'}); } }
    });
    setInterval(function () { [].forEach.call(document.querySelectorAll('.zgm-c'), function (b) { b.textContent = ile(b.dataset.od); }); }, 30000);
    wczytaj(true);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', formularz); else formularz();
  }
  // Formularz redakcji na tej samej stronie: po udanym wysłaniu dodaj kategorię i miejsce na mapę (anonimowo).
  function formularz() {
    var f = document.querySelector('.zrzg form');
    if (f) {
      var cel = document.createElement('label'); cel.className = 'zgm-pub';
      cel.innerHTML = '<input type="checkbox" checked> <span><b>Pokaż też na mapie zgłoszeń</b><br>Anonimowo: tylko kategoria i miejsce. Sąsiedzi zobaczą, że sprawa czeka, i dołożą swój głos.</span>';
      var przyc = f.querySelector('button[type=submit],button:not([type])'); f.insertBefore(cel, przyc || null);
      var dane = null;
      f.addEventListener('submit', function () { dane = {pub: cel.querySelector('input').checked, kat: ZR[f.kat ? f.kat.value : ''] || 'inne', lat: f.lat ? f.lat.value : '', lon: f.lng ? f.lng.value : ''}; }, true);
      var par = f.parentNode;
      new MutationObserver(function () {
        if (!dane || document.body.contains(f) || !par.querySelector('.ok')) return;
        var d = dane; dane = null; if (!d.pub || !d.lat || !d.lon) return;
        var fd = new FormData(); fd.append('kat', d.kat); fd.append('lat', d.lat); fd.append('lon', d.lon); fd.append('zrodlo', 'zyciepw-formularz'); fd.append('u', uid());
        fetch(C.api, {method: 'POST', body: fd}).then(function (r) { return r.json(); }).then(function (j) { if (j && j.sprawa) { var m = ls('moje') || []; m.push(j.sprawa.id); ls('moje', m.slice(-100)); par.querySelector('.ok').insertAdjacentHTML('beforeend', '<p style="margin:8px 0 0">' + (j.dolaczono ? 'To miejsce było już na mapie: teraz ' + j.sprawa.n + ' ' + pl(j.sprawa.n, 'zgłoszenie', 'zgłoszenia', 'zgłoszeń') + '.' : 'Sprawa jest też na mapie zgłoszeń powyżej.') + '</p>'); wczytaj(false); } }).catch(function () {});
      }).observe(par, {childList: true, subtree: true});
    }
  }
  if (window.L) start(); else window.addEventListener('load', start);
})();
</script>
<?php
    return ob_get_clean();
});
