<?php
/**
 * Rozkłady jazdy gminy: autobusy lokalne, pociągi KM i czasy przesiadek.
 * [zycie_rozklady gmina="kobylka"]  (opcjonalnie dane="https://…/komunikacja.json")
 * Dane to ten sam plik, z którego korzysta aplikacja mieszkańców (GitHub Pages, CORS otwarty).
 */
if (!defined('ABSPATH')) exit;

function zpw_rozklady_zrodla() {
    $o = array();
    foreach (zpw_dziki_gminy() as $k => $g) $o[$k] = array('n' => $g['n'], 'dane' => 'https://lechur.github.io/wehikul-czasu/rozklady/' . $k . '.json', 'app' => '');
    if (isset($o['kobylka'])) $o['kobylka']['app'] = 'https://lechur.github.io/wehikul-czasu/kobylka/#kom';
    return $o;
}

add_shortcode('zycie_rozklady', function ($atts) {
    $atts = shortcode_atts(array('gmina' => 'kobylka', 'dane' => ''), $atts);
    $Z = zpw_rozklady_zrodla();
    $gm = sanitize_key($atts['gmina']);
    if (!isset($Z[$gm]) && $atts['dane'] === '') {
        return '<p>Rozkłady jazdy dla tej gminy przygotowujemy. Na razie zajrzyj do <a href="https://www.wtp.waw.pl/rozklady-jazdy/" rel="noopener">rozkładów ZTM</a> i <a href="https://www.mazowieckie.com.pl/" rel="noopener">Kolei Mazowieckich</a>.</p>';
    }
    $src = $atts['dane'] !== '' ? esc_url_raw($atts['dane']) : $Z[$gm]['dane'];
    wp_enqueue_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4');
    wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);
    $cfg = wp_json_encode(array('dane' => $src, 'nazwa' => isset($Z[$gm]) ? $Z[$gm]['n'] : '', 'app' => isset($Z[$gm]) ? $Z[$gm]['app'] : ''));
    $id = 'zrj' . wp_rand(1000, 9999);
    ob_start(); ?>
<style>
.zrj{--ink:#14213d;--mut:#5b6577;--line:#d8dee8;--acc:#0e7c6b;--navy:#14315a;--soft:#f3f6f9;--ok:#0e7c6b;--styk:#d99a00;--dl:#e07b39;--bdl:#c2410c;--zle:#b42318;width:100%;min-width:0;contain:inline-size;max-width:min(980px,100%);margin:0 auto 28px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;color:var(--ink);line-height:1.45}
.zrj *{box-sizing:border-box}.zrj [hidden]{display:none!important}.zrj button,.zrj select{font-family:inherit}
.zrj-hero{background:linear-gradient(135deg,#14315a,#0e7c6b);color:#fff;border-radius:20px;padding:18px 20px;margin-bottom:12px}
.zrj-hero small{display:block;font-size:12.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;opacity:.85}
.zrj-hero h2{color:#fff!important;font-size:26px;line-height:1.15;margin:4px 0 6px!important}.zrj-hero p{margin:0;font-size:14.5px;opacity:.95}
.zrj-clock{float:right;font-size:30px;font-weight:800;font-variant-numeric:tabular-nums;margin-left:12px}
.zrj-tabs{display:grid;grid-template-columns:repeat(4,1fr);gap:4px;background:var(--soft);border-radius:14px;padding:4px;margin:0 0 12px;position:sticky;top:0;z-index:5}
.zrj-tabs button{border:0;background:none;border-radius:10px;padding:11px 4px;font-size:14px;font-weight:700;color:var(--mut);cursor:pointer;min-height:44px}.zrj-tabs button.on{background:#fff;color:var(--ink);box-shadow:0 1px 3px rgba(20,33,61,.15)}
.zrj-card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:14px;margin:0 0 12px;box-shadow:0 4px 16px rgba(20,33,61,.05)}
.zrj h3{font-size:18px!important;margin:16px 0 8px!important}
.zrj-seg{display:flex;gap:4px;background:var(--soft);border-radius:12px;padding:4px;margin:0 0 10px;overflow-x:auto}
.zrj-seg button{flex:1 0 auto;border:0;background:none;border-radius:9px;padding:9px 10px;font-size:14px;font-weight:700;color:var(--mut);cursor:pointer;min-height:42px}.zrj-seg button.on{background:#fff;color:var(--ink);box-shadow:0 1px 3px rgba(20,33,61,.15)}
.zrj-chips{display:flex;gap:6px;flex-wrap:wrap;margin:0 0 10px}
.zrj-chips button{display:flex;align-items:center;gap:6px;border:1.5px solid var(--line);background:#fff;color:var(--ink);border-radius:999px;padding:8px 13px;font-size:14px;font-weight:700;cursor:pointer;min-height:42px}
.zrj-chips button i{width:11px;height:11px;border-radius:50%;background:var(--k,var(--navy))}.zrj-chips button.on{background:var(--ink);border-color:var(--ink);color:#fff}
.zrj-lnk{border:0;background:none;color:var(--acc);font-weight:700;font-size:14.5px;padding:6px 0;cursor:pointer;text-decoration:underline}
.zrj-doj h4{margin:0 0 6px!important;font-size:17px!important}
.zrj-sl{display:flex;flex-direction:column;gap:6px;margin:8px 0 10px}
.zrj-sl button{display:flex;gap:10px;align-items:center;text-align:left;border:1.5px solid var(--line);background:#fff;border-radius:12px;padding:9px 11px;cursor:pointer;color:var(--ink);font-size:14px;min-height:44px}
.zrj-sl button b{flex:none;min-width:44px;font-size:13px;background:var(--soft);border-radius:8px;padding:4px 6px;text-align:center}
.zrj-sl button.on{border-color:var(--acc);background:#e8f4f1}
.zrj-czas{display:flex;gap:14px;align-items:baseline;background:linear-gradient(135deg,#e8f4f1,#f3f6f9);border-radius:14px;padding:12px 14px;margin:8px 0}
.zrj-czas b{font-size:30px;color:var(--acc);line-height:1}.zrj-czas span{font-size:14px;color:var(--mut)}
.zrj-kroki{margin:6px 0 10px;padding:0;list-style:none;counter-reset:k}
.zrj-kroki li{counter-increment:k;display:grid;grid-template-columns:30px 1fr auto;gap:8px;align-items:start;padding:8px 0;border-bottom:1px solid var(--soft);font-size:14.5px}
.zrj-kroki li:before{content:counter(k);display:grid;place-items:center;width:26px;height:26px;border-radius:50%;background:var(--navy);color:#fff;font-size:12.5px;font-weight:800}
.zrj-kroki li small{color:var(--mut);white-space:nowrap}
.zrj-gr{flex-basis:100%;font-size:12px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:var(--mut);margin:6px 0 0}
.zrj label{display:block;font-size:13px;font-weight:700;color:var(--mut);margin:6px 0 4px}
.zrj select{width:100%;font-size:16px;padding:11px 12px;border:1.5px solid var(--line);border-radius:12px;background:#fff;color:var(--ink);margin:0 0 10px}
.zrj-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;border:0;border-radius:12px;background:var(--acc);color:#fff!important;font-size:15px;font-weight:700;padding:11px 16px;cursor:pointer;text-decoration:none!important;min-height:44px}
.zrj-btn.line{background:#fff;color:var(--ink)!important;border:1.5px solid var(--line)}
.zrj-row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.zrj-dep{display:grid;grid-template-columns:auto 1fr auto;gap:10px;align-items:center;padding:10px 0;border-bottom:1px solid var(--soft)}
.zrj-dep:last-child{border-bottom:0}
.zrj-badge{min-width:52px;text-align:center;border-radius:10px;color:#fff;font-weight:800;font-size:14px;padding:6px 8px;background:var(--k,var(--navy))}
.zrj-dep b{display:block;font-size:15.5px}.zrj-dep span{display:block;font-size:12.5px;color:var(--mut)}
.zrj-when{text-align:right}.zrj-when b{font-size:18px;font-variant-numeric:tabular-nums}.zrj-when.soon b{color:var(--acc)}
.zrj-tab{display:grid;gap:0;margin-top:6px}
.zrj-h{display:grid;grid-template-columns:44px 1fr;gap:8px;padding:7px 4px;border-bottom:1px solid var(--soft);font-variant-numeric:tabular-nums}
.zrj-h b{font-size:17px;text-align:right}.zrj-h span{font-size:16px;word-spacing:7px}.zrj-h.now{background:#e8f4f1;border-radius:10px}.zrj-h u{text-decoration:none;color:#fff;background:var(--acc);border-radius:6px;padding:0 4px}
.zrj-list{max-height:460px;overflow:auto;margin-top:6px;border-radius:12px}
.zrj-tr{display:grid;grid-template-columns:62px 1fr auto;gap:10px;align-items:center;padding:9px 6px;border-bottom:1px solid var(--soft)}
.zrj-tr>b{font-size:17px;font-variant-numeric:tabular-nums}.zrj-tr div b{display:block;font-size:15px}.zrj-tr div span{display:block;font-size:12.5px;color:var(--mut)}.zrj-tr small{font-size:12px;color:var(--mut)}
.zrj-tr.cur{background:#e8f4f1;border-radius:10px}
.zrj-big{display:flex;gap:12px;align-items:baseline;margin:6px 0 8px}.zrj-big b{font-size:40px;line-height:1;color:var(--acc)}.zrj-big span{font-size:15px}
.zrj-bar{display:flex;height:12px;border-radius:99px;overflow:hidden;background:var(--soft);margin:6px 0}.zrj-bar i{display:block}
.zrj-leg{display:flex;flex-wrap:wrap;gap:6px 14px;font-size:13px;color:var(--mut)}.zrj-leg i{display:inline-block;width:10px;height:10px;border-radius:3px;margin-right:5px;vertical-align:-1px}
.zrj .ok{background:var(--ok)}.zrj .styk{background:var(--styk)}.zrj .dlugo{background:var(--dl)}.zrj .bdlugo{background:var(--bdl)}.zrj .zle{background:var(--zle)}
.zrj-pr{display:grid;grid-template-columns:62px 18px 62px 1fr auto;gap:6px;align-items:center;padding:9px 4px;border-bottom:1px solid var(--soft);font-variant-numeric:tabular-nums}
.zrj-pr b{font-size:16px}.zrj-pr span{font-size:12.5px;color:var(--mut)}.zrj-pr em{font-style:normal;color:#fff;border-radius:8px;padding:4px 8px;font-size:13px;font-weight:800;text-align:center;white-space:nowrap}
.zrj-pr.cur{background:#e8f4f1;border-radius:10px}
.zrj-walk{display:flex;justify-content:space-between;align-items:center;background:var(--soft);border-radius:12px;padding:8px 10px;margin:0 0 10px;font-size:14px}
.zrj-walk>span:last-child{display:flex;align-items:center;gap:4px;white-space:nowrap;flex:none}.zrj-walk button{width:40px;height:40px;border:1px solid var(--line);border-radius:10px;background:#fff;font-size:20px;font-weight:700;cursor:pointer;color:var(--ink)}.zrj-walk b{display:inline-block;min-width:62px;text-align:center}
.zrj-map{height:min(60vh,460px);min-height:300px;border-radius:16px;overflow:hidden;border:1px solid var(--line);background:#eef1f5}
.zrj-mut{color:var(--mut);font-size:13.5px}.zrj-empty{color:var(--mut);padding:14px 4px;text-align:center}
.zrj-app{display:flex;gap:12px;align-items:center;background:#fdf7e6;border-radius:16px;padding:14px;margin:12px 0}.zrj-app p{margin:0;font-size:14.5px;flex:1}
@media(max-width:520px){.zrj-tabs button{font-size:12.5px;padding:10px 2px}.zrj-hero h2{font-size:22px}.zrj-clock{font-size:24px}.zrj-pr{grid-template-columns:52px 14px 52px 1fr auto}}
</style>
<div class="zrj" id="<?php echo esc_attr($id); ?>">
 <div class="zrj-hero"><span class="zrj-clock" data-r="zeg">--:--</span><small>Rozkłady jazdy</small><h2 data-r="tyt">Komunikacja</h2><p data-r="sub">Wczytuję rozkłady…</p></div>
 <div class="zrj-tabs" role="tablist"><button class="on" data-tab="teraz">Odjazdy</button><button data-tab="bus">Autobusy</button><button data-tab="poc">Pociągi</button><button data-tab="prz">Przesiadki</button></div>
 <section data-p="teraz">
  <div class="zrj-card"><label for="<?php echo esc_attr($id); ?>-gdzie">Skąd odjeżdżasz?</label><select id="<?php echo esc_attr($id); ?>-gdzie" data-r="gdzie"></select>
   <div class="zrj-row"><button class="zrj-btn line" data-a="lok">📍 Najbliższy przystanek</button><span class="zrj-mut" data-r="lokinfo"></span></div></div>
  <div class="zrj-card" data-r="odj"></div>
  <div class="zrj-card zrj-doj" data-r="doj"></div>
  <h3>Mapa linii</h3><div class="zrj-chips" data-r="mchips"></div><div class="zrj-map" data-r="mapa"></div>
  <p class="zrj-mut">Kliknij przystanek na mapie, żeby zobaczyć odjazdy.</p>
 </section>
 <section data-p="bus" hidden>
  <div class="zrj-card"><div class="zrj-chips" data-r="linie"></div><div class="zrj-chips" data-r="kier"></div>
   <label for="<?php echo esc_attr($id); ?>-pr">Przystanek</label><select id="<?php echo esc_attr($id); ?>-pr" data-r="przyst"></select><p style="margin:-4px 0 10px"><button class="zrj-lnk" data-a="dojdz">🚶 Jak dojść do tego przystanku</button></p>
   <div class="zrj-seg" data-r="dz1"></div><div class="zrj-tab" data-r="tab"></div><p class="zrj-mut" data-r="tabinfo"></p></div>
 </section>
 <section data-p="poc" hidden>
  <div class="zrj-card"><div class="zrj-seg" data-r="st1"></div><div class="zrj-seg" data-r="pk"></div><div class="zrj-seg" data-r="dz2"></div>
   <div class="zrj-list" data-r="poc"></div><p class="zrj-mut" data-r="pocinfo"></p></div>
 </section>
 <section data-p="prz" hidden>
  <div class="zrj-card"><p style="margin:0 0 10px">Ile minut według rozkładu czekasz między autobusem a pociągiem? Wybierz stację i kierunek.</p>
   <div class="zrj-seg" data-r="st2"></div>
   <div class="zrj-seg" data-r="tryb"><button data-t="na" class="on">Autobusem na pociąg</button><button data-t="z">Z pociągu na autobus</button></div>
   <div class="zrj-seg" data-r="dz3"></div>
   <div class="zrj-walk"><span>Dojście przystanek ↔ peron</span><span><button data-w="-1" aria-label="Mniej">−</button><b data-r="walk">3 min</b><button data-w="1" aria-label="Więcej">+</button></span></div>
   <div data-r="sum"></div><div class="zrj-list" data-r="prz"></div>
   <p class="zrj-mut">Liczone z rozkładów, bez opóźnień. Przesiadka „na styk” w praktyce często się nie udaje.</p></div>
 </section>
 <div class="zrj-app" data-r="app" hidden><p><b>Te rozkłady masz też w telefonie.</b> Aplikacja „Moja Kobyłka” działa bez instalowania ze sklepu.</p><a class="zrj-btn" data-r="appl" href="#">Otwórz</a></div>
 <div class="zrj-card" data-r="info"></div>
</div>
<script>
(function(){
const C=<?php echo $cfg; ?>,R=document.getElementById(<?php echo wp_json_encode($id); ?>);if(!R)return;
const q=n=>R.querySelector('[data-r="'+n+'"]'),E=s=>String(s==null?'':s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const KOL=['#0e7c6b','#c2410c','#6d28d9','#0369a1','#b45309','#be185d','#4d7c0f','#0f766e','#9333ea','#b91c1c','#1d4ed8','#a16207','#15803d','#7c2d12','#475569','#db2777'],DN={robocze:'Dni robocze',sobota:'Sobota',niedziela:'Niedziela'};
const t2m=t=>{const[a,b]=t.split(':');return +a*60+ +b;},m2t=m=>{m=((m%1440)+1440)%1440;return Math.floor(m/60)+':'+String(m%60).padStart(2,'0');};
const teraz=()=>{const d=new Date();return d.getHours()*60+d.getMinutes();},typD=d=>{const w=d.getDay();return w===0?'niedziela':w===6?'sobota':'robocze';};
const iso=d=>d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
function dataTypu(t){const d=new Date();for(let i=0;i<8;i++){const x=new Date(d.getFullYear(),d.getMonth(),d.getDate()+i);if(typD(x)===t)return iso(x);}return iso(d);}
let K=null;const S={me:null,sl:null,tab:'teraz',gdzie:0,linia:null,kier:0,przyst:-1,dzien:typD(new Date()),st:null,pk:'do_warszawy',tryb:'na',walk:3,mL:''};
const pocL=(st,kier,t)=>{const P=K.pociagi&&K.pociagi.stacje&&K.pociagi.stacje[st],dz=dataTypu(t);return((P&&P[kier]||{})[t]||[]).filter(x=>!x.daty||x.daty.includes(dz));};
function przyj(l,ki,i,d){const k=l.kierunki[ki];if(!k)return[];if(k.czasy&&k.czasy[i]&&k.czasy[i][d])return k.czasy[i][d].map(t2m);if(!k.minuty||k.minuty[i]==null)return[];return((k.odjazdy||{})[d]||[]).map(t=>t2m(t)+k.minuty[i]);}
function seg(n,on){q(n).innerHTML=Object.keys(DN).map(d=>`<button data-d="${d}" class="${d===S.dzien?'on':''}">${DN[d]}</button>`).join('');}
let MIEJSCA=[];
function budujMiejsca(){const m={};
 const wgF=K.linie.some(l=>l.kierunki.some(k=>k.przystanki.some(p=>'wg' in p)));
 K.linie.forEach((l,li)=>l.kierunki.forEach((k,ki)=>k.przystanki.forEach((p,i)=>{if(wgF&&!p.wg)return;const key='p:'+p.nazwa;(m[key]=m[key]||{n:p.nazwa,t:'p',pts:[],sl:{},lat:p.lat,lon:p.lon}).pts.push({li,ki,i,sl:p.slupek||''});if(p.lat&&i<k.przystanki.length-1){const so=(m[key].sl[p.slupek||'']=m[key].sl[p.slupek||'']||{lat:p.lat,lon:p.lon,k:[]});const t=l.id+' → '+k.do;if(!so.k.includes(t))so.k.push(t);}if(!m[key].lat&&p.lat){m[key].lat=p.lat;m[key].lon=p.lon;}})));
 const out=Object.values(m).sort((a,b)=>a.n.localeCompare(b.n,'pl'));
 const st=Object.keys((K.pociagi&&K.pociagi.stacje)||{}).sort((a,b)=>a.length-b.length).map(s=>{let ll=null;K.linie.forEach(l=>l.kierunki.forEach(k=>(k.stacje||[]).forEach(x=>{if(x.stacja===s&&!ll){const p=k.przystanki[x.i];if(p.lat)ll=[p.lat,p.lon];}})));const w=K.pociagi.wsp&&K.pociagi.wsp[s];if(w)ll=w;return{n:'Stacja '+s,s,t:'s',pts:[],sl:{},lat:ll&&ll[0],lon:ll&&ll[1]};});
 st.forEach(s=>{K.linie.forEach((l,li)=>l.kierunki.forEach((k,ki)=>(k.stacje||[]).forEach(x=>{if(x.stacja===s.s)s.pts.push({li,ki,i:x.i,sl:k.przystanki[x.i].slupek||'',nz:k.przystanki[x.i].nazwa});})));});
 MIEJSCA=st.concat(out);
 q('gdzie').innerHTML=(st.length?`<optgroup label="Stacje kolejowe">${st.map((x,i)=>`<option value="${i}">${E(x.n)} (pociągi i autobusy)</option>`).join('')}</optgroup>`:'')+`<optgroup label="Przystanki autobusowe">${out.map((x,i)=>`<option value="${i+st.length}">${E(x.n)}</option>`).join('')}</optgroup>`;
}
function odjazdy(){const M=MIEJSCA[S.gdzie];if(!M)return;const d=typD(new Date()),now=teraz(),o=[];
 if(M.s){Object.keys(K.pociagi.stacje[M.s]||{}).forEach(kk=>pocL(M.s,kk,d).forEach(x=>o.push({m:t2m(x.g),b:x.linia||'KM',k:'#14315a',do:x.do,op:'pociąg Kolei Mazowieckich'})));}
 M.pts.forEach(p=>{const l=K.linie[p.li],k=l.kierunki[p.ki];if(p.i===k.przystanki.length-1)return;przyj(l,p.ki,p.i,d).forEach(m=>o.push({m,b:l.id,k:KOL[p.li%KOL.length],do:k.do,op:'autobus'+(p.nz?' z przyst. '+p.nz:'')+(p.sl?' · słupek '+p.sl:'')}));});
 const n=o.filter(x=>x.m>=now).sort((a,b)=>a.m-b.m).slice(0,12);
 q('odj').innerHTML=`<b style="font-size:17px">${E(M.n)}</b><div class="zrj-mut" style="margin-bottom:4px">Najbliższe odjazdy dziś</div>`+(n.length?n.map(x=>{const r=x.m-now;return`<div class="zrj-dep"><span class="zrj-badge" style="--k:${x.k}">${E(x.b)}</span><div><b>${E(x.do)}</b><span>${x.op}</span></div><div class="zrj-when${r<15?' soon':''}"><b>${r<1?'teraz':r<60?r+' min':m2t(x.m)}</b><span>${r<60?m2t(x.m):''}</span></div></div>`;}).join(''):'<p class="zrj-empty">Dziś stąd nic już nie odjeżdża. Sprawdź rozkład na jutro w zakładce Autobusy lub Pociągi.</p>');
 dojscie();
}
/* ---- jak dojść ---- */
const MOD={left:'skręć w lewo',right:'skręć w prawo','slight left':'odbij lekko w lewo','slight right':'odbij lekko w prawo','sharp left':'skręć ostro w lewo','sharp right':'skręć ostro w prawo',straight:'idź prosto',uturn:'zawróć'};
const KIER=['północ','północny wschód','wschód','południowy wschód','południe','południowy zachód','zachód','północny zachód'];
function krok(st){const m=st.maneuver,nm=st.name?` w ${/^(ul|al|pl|os)\.?\s/i.test(st.name)?'':'ul. '}${E(st.name)}`:'';
 if(m.type==='depart')return`Ruszaj na ${KIER[Math.round(((m.bearing_after||0)%360)/45)%8]}${st.name?' ulicą '+E(st.name):''}`;
 if(m.type==='arrive')return'Jesteś na miejscu'+(m.modifier==='left'?' – przystanek po lewej':m.modifier==='right'?' – przystanek po prawej':'');
 if(m.type==='roundabout'||m.type==='rotary')return`Na rondzie idź w ${m.exit?m.exit+'. wylot':'wylot'}${nm}`;
 if(m.type==='new name'||m.type==='continue')return(MOD[m.modifier]&&m.modifier!=='straight'?MOD[m.modifier][0].toUpperCase()+MOD[m.modifier].slice(1):'Idź dalej')+nm;
 const a=MOD[m.modifier]||'idź dalej';return a[0].toUpperCase()+a.slice(1)+nm;}
function uprosc(st,cel){const o=[];st.forEach(x=>{x=Object.assign({},x,{maneuver:Object.assign({},x.maneuver)});if(x.name&&cel&&cel.toLowerCase().includes(x.name.toLowerCase()))x.name='';const t=x.maneuver.type;const pr=o[o.length-1];
 if(pr&&t!=='arrive'&&t!=='depart'&&(x.distance<25||(x.name&&x.name===pr.name&&(t==='continue'||t==='new name'))))pr.distance+=x.distance;else o.push(x);});return o;}
const trasy={};let trasaL=null;
function cel(){const M=MIEJSCA[S.gdzie];if(!M)return null;const sl=M.sl&&S.sl!=null&&M.sl[S.sl];return sl?{lat:sl.lat,lon:sl.lon}:(M.lat?{lat:M.lat,lon:M.lon}:null);}
function mapsUrl(c){const ios=/iPhone|iPad|iPod/.test(navigator.userAgent);return ios?`https://maps.apple.com/?daddr=${c.lat},${c.lon}&dirflg=w`:`https://www.google.com/maps/dir/?api=1&destination=${c.lat},${c.lon}&travelmode=walking`;}
async function dojscie(){const box=q('doj'),M=MIEJSCA[S.gdzie];if(!box||!M)return;
 const sls=Object.entries(M.sl||{}).filter(([k,v])=>v.lat);if(S.sl==null||!(M.sl||{})[S.sl])S.sl=sls.length===1?sls[0][0]:null;const c=cel();
 let h=`<h4>🚶 Jak dojść ${M.s?'na stację':'na przystanek'}</h4>`;
 if(!c){box.innerHTML=h+`<p class="zrj-mut" style="margin:0 0 10px">Nie mamy jeszcze dokładnego położenia tego przystanku.</p><a class="zrj-btn line" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent('przystanek '+M.n+' '+(C.nazwa||''))}">Szukaj w Mapach Google</a>`;return;}
 if(sls.length>1)h+=`<p class="zrj-mut" style="margin:0">Przystanek ma ${sls.length} słupki (po obu stronach ulicy). Wybierz ten, z którego odjeżdża Twój autobus:</p><div class="zrj-sl">${sls.map(([k,v])=>`<button data-sl="${E(k)}" class="${S.sl===k?'on':''}"><b>${k?'słupek '+E(k):'przystanek'}</b><span>${v.k.slice(0,4).map(E).join(', ')}${v.k.length>4?'…':''}</span></button>`).join('')}</div>`;
 if(!S.me){box.innerHTML=h+`<div class="zrj-row"><button class="zrj-btn" data-a="trasa">📍 Pokaż drogę z mojego miejsca</button><a class="zrj-btn line" target="_blank" rel="noopener" href="${mapsUrl(c)}">Otwórz w mapach</a></div><p class="zrj-mut" style="margin:8px 0 0">Pokażemy trasę pieszą krok po kroku i czas dojścia.</p>`;return;}
 const key=S.me.join()+'|'+c.lat+','+c.lon;box.innerHTML=h+'<p class="zrj-mut">Wyznaczam trasę pieszą…</p>';
 let T=trasy[key];if(T===undefined){try{const j=await fetch(`https://routing.openstreetmap.de/routed-foot/route/v1/foot/${S.me[1]},${S.me[0]};${c.lon},${c.lat}?overview=full&geometries=geojson&steps=true`).then(r=>r.json());T=trasy[key]=(j.routes&&j.routes[0])||null;}catch(e){T=null;}}
 const prosto=Math.hypot((c.lon-S.me[1])*68000,(c.lat-S.me[0])*111000);
 const dist=T?T.distance:prosto*1.3,min=Math.max(1,Math.round(dist/75));
 const st=T?uprosc(T.legs[0].steps,M.n):[];
 box.innerHTML=h+`<div class="zrj-czas"><b>${min} min</b><span>pieszo · ${dist<1000?Math.round(dist/10)*10+' m':(dist/1000).toFixed(1).replace('.',',')+' km'}${S.sl?' · do słupka '+E(S.sl):''}</span></div>`+
  (st.length?`<ol class="zrj-kroki">${st.map(x=>`<li><span>${krok(x)}</span><small>${x.maneuver.type==='arrive'?'':Math.round(x.distance/5)*5+' m'}</small></li>`).join('')}</ol>`:`<p class="zrj-mut">Nie udało się wyznaczyć trasy ulicami – kieruj się mapą.</p>`)+
  `<div class="zrj-row"><a class="zrj-btn" target="_blank" rel="noopener" href="${mapsUrl(c)}">Prowadź w mapach</a><button class="zrj-btn line" data-a="namapie">Pokaż na mapie</button></div>`+(dist>3000?'<p class="zrj-mut" style="margin:8px 0 0">To daleko – sprawdź, czy bliżej nie masz innego przystanku (przycisk „Najbliższy przystanek”).</p>':'');
 rysujTrase(T,c);}
function rysujTrase(T,c){if(!mapa||!window.L)return;if(trasaL)trasaL.remove();trasaL=L.layerGroup().addTo(mapa);
 const pts=T?T.geometry.coordinates.map(x=>[x[1],x[0]]):[S.me,[c.lat,c.lon]];
 L.polyline(pts,{color:'#14315a',weight:5,opacity:.9,dashArray:T?null:'6 8'}).addTo(trasaL);
 L.circleMarker(S.me,{radius:8,color:'#fff',weight:3,fillColor:'#2563eb',fillOpacity:1}).addTo(trasaL).bindTooltip('Jesteś tutaj');
 L.circleMarker([c.lat,c.lon],{radius:9,color:'#fff',weight:3,fillColor:'#0e7c6b',fillOpacity:1}).addTo(trasaL).bindTooltip('Cel');}
let mapa=null,warstwa=null;
function rysujMape(){if(!q('mapa'))return;if(!window.L){window.addEventListener('load',rysujMape,{once:true});return;}if(!mapa){mapa=L.map(q('mapa'),{scrollWheelZoom:false});L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(mapa);}
 if(warstwa)warstwa.remove();warstwa=L.layerGroup().addTo(mapa);const all=[];
 K.linie.forEach((l,li)=>{if(S.mL&&S.mL!==l.id)return;const k=l.kierunki[0];const pts=k.przystanki.filter(p=>p.lat).map(p=>[p.lat,p.lon]);if(pts.length>1)L.polyline(pts,{color:KOL[li%KOL.length],weight:S.mL?6:4,opacity:.85}).addTo(warstwa);
  k.przystanki.forEach(p=>{if(!p.lat)return;if(p.wg!==0)all.push([p.lat,p.lon]);const idx=MIEJSCA.findIndex(x=>x.t==='p'&&x.n===p.nazwa);L.circleMarker([p.lat,p.lon],{radius:6,color:KOL[li%KOL.length],weight:2.5,fillColor:'#fff',fillOpacity:1}).addTo(warstwa).bindTooltip(p.nazwa).on('click',()=>{S.gdzie=idx;S.sl=null;q('gdzie').value=idx;odjazdy();q('odj').scrollIntoView({behavior:'smooth',block:'center'});});});});
 MIEJSCA.filter(x=>x.s&&x.lat).forEach(x=>{const idx=MIEJSCA.indexOf(x);L.marker([x.lat,x.lon],{icon:L.divIcon({className:'',html:'<div style="background:#14315a;color:#fff;font:800 12px system-ui;border-radius:8px;padding:4px 7px;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.3);white-space:nowrap">🚆 '+E(x.s)+'</div>',iconSize:null})}).addTo(warstwa).on('click',()=>{S.gdzie=idx;S.sl=null;q('gdzie').value=idx;odjazdy();q('odj').scrollIntoView({behavior:'smooth',block:'center'});});});
 MIEJSCA.forEach(x=>{if(x.s&&x.lat)all.push([x.lat,x.lon]);});if(all.length)mapa.fitBounds(all,{padding:[20,20],maxZoom:15});if(S.me&&cel())dojscie();setTimeout(()=>mapa.invalidateSize(),50);
}
function grupy(attr,akt){let g=null,h='';K.linie.forEach((l,i)=>{if(l.grupa&&l.grupa!==g){g=l.grupa;h+=`<span class="zrj-gr">${E(g)}</span>`;}h+=`<button data-${attr}="${E(l.id)}" style="--k:${KOL[i%KOL.length]}" class="${akt===l.id?'on':''}" title="${E(l.opis||'')}"><i></i>${E(l.id)}</button>`;});return h;}
function mchips(){q('mchips').innerHTML=`<button data-ml="" class="${!S.mL?'on':''}">Wszystkie</button>`+grupy('ml',S.mL);}
function bus(){const li=Math.max(0,K.linie.findIndex(l=>l.id===S.linia)),l=K.linie[li];S.linia=l.id;if(S.kier>=l.kierunki.length)S.kier=0;const k=l.kierunki[S.kier];
 q('linie').innerHTML=grupy('l',l.id);
 q('kier').innerHTML=l.kierunki.map((x,i)=>`<button data-k="${i}" class="${i===S.kier?'on':''}">→ ${E(x.do)}</button>`).join('');
 if(S.przyst<0||S.przyst>=k.przystanki.length){const w=k.przystanki.findIndex(p=>p.wg);S.przyst=w>=0?w:0;}
 q('przyst').innerHTML=k.przystanki.map((p,i)=>`<option value="${i}"${i===S.przyst?' selected':''}>${E(p.nazwa)}${k.minuty&&k.minuty[i]!=null&&i?' (+'+k.minuty[i]+' min)':''}</option>`).join('');
 const T=przyj(l,S.kier,S.przyst,S.dzien),g={};T.forEach(m=>{const h=Math.floor(m/60)%24;(g[h]=g[h]||[]).push(String(m%60).padStart(2,'0'));});
 const now=teraz(),dz=S.dzien===typD(new Date()),hN=Math.floor(now/60);
 q('tab').innerHTML=T.length?Object.keys(g).map(Number).sort((a,b)=>a-b).map(h=>`<div class="zrj-h${dz&&h===hN?' now':''}"><b>${h}</b><span>${g[h].map(x=>dz&&h*60+ +x>=now&&h*60+ +x<now+60?'<u>'+x+'</u>':x).join(' ')}</span></div>`).join(''):'<p class="zrj-empty">W tym dniu linia nie kursuje.'+(S.dzien!=='robocze'?'<br><button class="zrj-btn line" data-d="robocze" style="margin-top:10px">Pokaż dni robocze</button>':'')+'</p>';
 const dok=k.czasy&&k.czasy[S.przyst];q('tabinfo').innerHTML=E(l.opis||'')+'. '+(dok||!S.przyst?'Godziny z rozkładu dla tego przystanku. ':'Godziny wyliczone z czasu przejazdu, mogą różnić się o 1–2 minuty. ')+(k.wazny_od||l.wazny_od?'Ważny od '+E(k.wazny_od||l.wazny_od)+'. ':'')+(l.url?`<a href="${E(l.url)}" target="_blank" rel="noopener">Oficjalny rozkład${l.grupa?' – '+E(l.grupa):''}</a>`:'');
}
function stSeg(n,tylkoPrz){let st=Object.keys(K.pociagi.stacje).sort((a,b)=>a.length-b.length);if(tylkoPrz)st=st.filter(s=>punkty(s).length&&K.pociagi.stacje[s].do_warszawy);if(S.st&&!st.includes(S.st))S.st=st[0];if(!S.st)S.st=st[0];q(n).innerHTML=st.map(s=>`<button data-s="${E(s)}" class="${s===S.st?'on':''}">${E(s)}</button>`).join('');}
const PKN={do_warszawy:'Do Warszawy',z_warszawy:'Z Warszawy'};
function pkSeg(){const P=K.pociagi.stacje[S.st]||{},ks=Object.keys(P).sort((a,b)=>(PKN[a]?0:1)-(PKN[b]?0:1)||a.localeCompare(b));if(!ks.includes(S.pk))S.pk=ks[0];q('pk').innerHTML=ks.map(k=>`<button data-pk="${E(k)}" class="${k===S.pk?'on':''}">${E(PKN[k]||'W stronę: '+k.replace(/^kier_/,''))}</button>`).join('');}
function poc(){pkSeg();const L_=pocL(S.st,S.pk,S.dzien).slice().sort((a,b)=>t2m(a.g)-t2m(b.g)),now=teraz(),dz=S.dzien===typD(new Date());let nast=false;
 q('poc').innerHTML=L_.length?L_.map(x=>{const m=t2m(x.g),cur=dz&&!nast&&m>=now?(nast=true,' cur'):'';return`<div class="zrj-tr${cur}"><b>${E(x.g)}</b><div><b>${E(x.do)}</b><span>${x.linia?E(x.linia)+' · ':''}z: ${E(x.z||'')}${x.daty?' · tylko wybrane dni':''}</span></div><small>${E(x.nr||'')}</small></div>`;}).join(''):'<p class="zrj-empty">Brak pociągów w tym dniu.</p>';
 const c=q('poc').querySelector('.cur');if(c)q('poc').scrollTop=c.offsetTop-q('poc').offsetTop-40;
 q('pocinfo').innerHTML=`${L_.length} pociągów. Koleje Mazowieckie${K.pociagi.wazny?', rozkład ważny '+E(K.pociagi.wazny):''}. ${K.pociagi.zrodlo?`<a href="${E(K.pociagi.zrodlo)}" target="_blank" rel="noopener">Źródło</a>. `:''}Utrudnienia na żywo: <a href="https://portalpasazera.pl" target="_blank" rel="noopener">portalpasazera.pl</a>.`;
}
const ocena=(c,p)=>c<p?['zle','nie zdążysz']:c<=p+1?['styk','na styk']:c<=12?['ok','wygodnie']:c<=20?['dlugo','długo']:['bdlugo','bardzo długo'];
function punkty(st){const o=[];K.linie.forEach((l,li)=>l.kierunki.forEach((k,ki)=>(k.stacje||[]).forEach(s=>{if(s.stacja===st)o.push({l,li,ki,i:s.i});})));return o;}
function prz(){const st=S.st,d=S.dzien,out=[],na=S.tryb==='na';
 if(na){const P=pocL(st,'do_warszawy',d).map(x=>({m:t2m(x.g),do:x.do})).sort((a,b)=>a.m-b.m);
  punkty(st).forEach(p=>{if(p.i===0)return;przyj(p.l,p.ki,p.i,d).forEach(a=>{const zd=P.find(x=>x.m-a>=S.walk);out.push({a,b:zd?zd.m:null,c:zd?zd.m-a:null,lin:p.l.id,li:p.li,op:zd?zd.do:''});});});}
 else{const P=pocL(st,'z_warszawy',d).map(x=>t2m(x.g)).sort((a,b)=>a-b),B=[];
  punkty(st).forEach(p=>{const k=p.l.kierunki[p.ki];if(p.i===k.przystanki.length-1)return;przyj(p.l,p.ki,p.i,d).forEach(m=>B.push({m,lin:p.l.id,li:p.li,do:k.do}));});B.sort((a,b)=>a.m-b.m);
  P.forEach(a=>{const zd=B.find(x=>x.m-a>=S.walk);out.push({a,b:zd?zd.m:null,c:zd?zd.m-a:null,lin:zd?zd.lin:'',li:zd?zd.li:0,op:zd?'kier. '+zd.do:''});});}
 out.sort((x,y)=>x.a-y.a);
 if(!out.length){q('sum').innerHTML='';q('prz').innerHTML='<p class="zrj-empty">W tym dniu nie ma kursów do porównania – część linii kursuje tylko w dni robocze.'+(S.dzien!=='robocze'?'<br><button class="zrj-btn line" data-d="robocze" style="margin-top:10px">Pokaż dni robocze</button>':'')+'</p>';return;}
 const n={ok:0,styk:0,dlugo:0,bdlugo:0,zle:0};out.forEach(r=>{if(r.b==null)n.zle++;else n[ocena(r.c,S.walk)[0]]++;});const N=out.length,pr=x=>Math.round(x/N*100);
 q('sum').innerHTML=`<div class="zrj-big"><b>${n.ok}</b><span>z ${N} ${na?'kursów autobusu daje':'pociągów daje'} wygodną przesiadkę (do 12 minut czekania)</span></div><div class="zrj-bar">${['ok','styk','dlugo','bdlugo','zle'].map(k=>`<i class="${k}" style="flex:${n[k]}"></i>`).join('')}</div><div class="zrj-leg"><span><i class="ok"></i>wygodnie ${pr(n.ok)}%</span><span><i class="styk"></i>na styk ${pr(n.styk)}%</span><span><i class="dlugo"></i>13–20 min ${pr(n.dlugo)}%</span><span><i class="bdlugo"></i>ponad 20 min ${pr(n.bdlugo)}%</span>${n.zle?`<span><i class="zle"></i>brak ${pr(n.zle)}%</span>`:''}</div>`;
 const now=teraz(),dz=S.dzien===typD(new Date());let nast=false;
 q('prz').innerHTML=out.map(r=>{const o=r.b==null?['zle','brak']:ocena(r.c,S.walk),cur=dz&&!nast&&r.a>=now?(nast=true,' cur'):'';
  return`<div class="zrj-pr${cur}"><b>${m2t(r.a)}</b><span>→</span><b>${r.b==null?'–':m2t(r.b)}</b><span>${na?'autobus '+E(r.lin)+' → pociąg, '+E(r.op):'pociąg → '+(r.lin?'autobus '+E(r.lin)+', '+E(r.op):'brak autobusu')}</span><em class="${o[0]}">${r.b==null?'–':r.c+' min'}</em></div>`;}).join('');
 const c=q('prz').querySelector('.cur');if(c)q('prz').scrollTop=c.offsetTop-q('prz').offsetTop-40;
}
function pokaz(t){S.tab=t;R.querySelectorAll('[data-tab]').forEach(b=>b.classList.toggle('on',b.dataset.tab===t));R.querySelectorAll('section[data-p]').forEach(s=>s.hidden=s.dataset.p!==t);
 if(t==='teraz'){odjazdy();rysujMape();}if(t==='bus'){seg('dz1');bus();}if(t==='poc'){stSeg('st1');seg('dz2');poc();}if(t==='prz'){stSeg('st2',1);seg('dz3');prz();}
 try{history.replaceState(null,'','#'+t);}catch(e){}}
R.addEventListener('click',e=>{const b=e.target.closest('button');if(!b)return;const D=b.dataset;
 if(D.tab)pokaz(D.tab);else if(D.d){S.dzien=D.d;pokaz(S.tab);}else if(D.l){S.linia=D.l;S.kier=0;S.przyst=-1;bus();}else if(D.k!=null&&b.parentNode===q('kier')){S.kier=+D.k;S.przyst=-1;bus();}
 else if(D.s){S.st=D.s;pokaz(S.tab);}else if(D.pk){S.pk=D.pk;q('pk').querySelectorAll('button').forEach(x=>x.classList.toggle('on',x===b));poc();}
 else if(D.t){S.tryb=D.t;q('tryb').querySelectorAll('button').forEach(x=>x.classList.toggle('on',x===b));prz();}
 else if(D.w){S.walk=Math.min(8,Math.max(1,S.walk+ +D.w));q('walk').textContent=S.walk+' min';prz();}
 else if(D.ml!=null){S.mL=D.ml;mchips();rysujMape();}
 else if(D.sl!=null){S.sl=D.sl;dojscie();}
 else if(D.a==='namapie'){const c=cel();if(c&&mapa){q('mapa').scrollIntoView({behavior:'smooth',block:'center'});const b=trasaL?L.featureGroup(trasaL.getLayers()).getBounds():null;b?mapa.fitBounds(b,{padding:[30,30]}):mapa.setView([c.lat,c.lon],17);}}
 else if(D.a==='trasa'){gps(()=>dojscie());}
 else if(D.a==='dojdz'){const l=K.linie.find(x=>x.id===S.linia),p=l&&l.kierunki[S.kier].przystanki[S.przyst];if(!p)return;let idx=MIEJSCA.findIndex(x=>x.t==='p'&&x.n===p.nazwa);if(idx<0){MIEJSCA.push({n:p.nazwa,t:'p',pts:[],sl:p.lat?{[p.slupek||'']:{lat:p.lat,lon:p.lon,k:[l.id+' → '+l.kierunki[S.kier].do]}}:{},lat:p.lat,lon:p.lon});idx=MIEJSCA.length-1;}S.gdzie=idx;S.sl=p.lat&&MIEJSCA[idx].sl[p.slupek||'']?(p.slupek||''):null;if(q('gdzie').querySelector('option[value="'+idx+'"]'))q('gdzie').value=idx;pokaz('teraz');setTimeout(()=>q('doj').scrollIntoView({behavior:'smooth',block:'start'}),100);}
 else if(D.a==='lok'){q('lokinfo').textContent='Szukam…';
  gps(()=>{const la=S.me[0],lo=S.me[1];let best=-1,bd=1e9;MIEJSCA.forEach((m,i)=>{if(!m.lat)return;const dx=(m.lon-lo)*68000,dy=(m.lat-la)*111000,dd=Math.hypot(dx,dy);if(dd<bd){bd=dd;best=i;}});
   if(best<0){q('lokinfo').textContent='Nie znalazłem przystanku.';return;}S.gdzie=best;S.sl=null;q('gdzie').value=best;q('lokinfo').textContent=bd<1500?'ok. '+Math.round(bd/10)*10+' m od Ciebie':'Najbliższy jest '+(bd/1000).toFixed(1).replace('.',',')+' km od Ciebie';odjazdy();});}
});
function gps(ok){if(!navigator.geolocation){alertTxt('Twoja przeglądarka nie udostępnia lokalizacji.');return;}navigator.geolocation.getCurrentPosition(p=>{S.me=[+p.coords.latitude.toFixed(5),+p.coords.longitude.toFixed(5)];ok();},()=>alertTxt('Nie mam dostępu do lokalizacji. Zezwól na nią w przeglądarce albo wybierz przystanek z listy.'),{enableHighAccuracy:true,timeout:10000,maximumAge:60000});}
function alertTxt(t){const x=q('lokinfo');if(x)x.textContent=t;const d=q('doj');if(d&&S.tab==='teraz')d.insertAdjacentHTML('beforeend','<p class="zrj-mut" style="color:#b42318">'+t+'</p>');}
q('gdzie').addEventListener('change',e=>{S.gdzie=+e.target.value;S.sl=null;odjazdy();});q('przyst').addEventListener('change',e=>{S.przyst=+e.target.value;bus();});
const zeg=()=>{q('zeg').textContent=new Date().toLocaleTimeString('pl-PL',{hour:'2-digit',minute:'2-digit'});};zeg();
setInterval(()=>{zeg();if(K&&S.tab==='teraz')odjazdy();},30000);
fetch(C.dane,{cache:'no-cache'}).then(r=>r.json()).then(j=>{K=j;
 q('tyt').textContent='Rozkłady jazdy'+(C.nazwa?' – '+C.nazwa:'');
 const ma=K.pociagi&&K.pociagi.stacje&&Object.keys(K.pociagi.stacje).length;
 const mp=ma&&Object.keys(K.pociagi.stacje).some(s=>punkty(s).length&&K.pociagi.stacje[s].do_warszawy);
 const nl=K.linie.length;q('sub').textContent=(nl?nl+' '+(nl===1?'linia autobusowa':nl<5?'linie autobusowe':'linii autobusowych'):'Brak linii autobusowych w danych')+(ma?', pociągi Kolei Mazowieckich'+(mp?' i czasy przesiadek':''):'')+'. Stan na '+(K.stan||'').split('-').reverse().join('.')+'.';
 if(!ma)R.querySelectorAll('[data-tab="poc"]').forEach(b=>b.hidden=true);if(!mp)R.querySelectorAll('[data-tab="prz"]').forEach(b=>b.hidden=true);
 const vis=[...R.querySelectorAll('.zrj-tabs button')].filter(b=>!b.hidden).length;R.querySelector('.zrj-tabs').style.gridTemplateColumns='repeat('+vis+',1fr)';
 if(C.app){q('app').hidden=false;q('appl').href=C.app;}
 const zr=K.zrodla||{};q('info').innerHTML=`${(K.bilety||[]).length?`<b>Bilety</b><ul style="margin:6px 0 10px;padding-left:20px">${K.bilety.map(x=>'<li>'+E(x)+'</li>').join('')}</ul>`:''}<p class="zrj-mut" style="margin:0">Źródła rozkładów: ${[...new Set(K.linie.map(l=>l.grupa).filter(Boolean))].map(g=>{const l=K.linie.find(x=>x.grupa===g);return`<a href="${E(l.url)}" target="_blank" rel="noopener">${E(g)}</a>`;}).join(', ')||`<a href="${E(K.zrodlo_rozkladow||'https://www.wtp.waw.pl/rozklady-jazdy/')}" target="_blank" rel="noopener">ZTM Warszawa</a>`}${ma?`, <a href="${E(K.pociagi.zrodlo||'https://www.mazowieckie.com.pl/')}" target="_blank" rel="noopener">Koleje Mazowieckie</a>`:''}${K.zrodlo?`, <a href="${E(K.zrodlo)}" target="_blank" rel="noopener">strona urzędu</a>`:''}. Linie nocne: <a href="https://www.wtp.waw.pl/rozklady-jazdy/" target="_blank" rel="noopener">rozkłady ZTM</a>. ${(K.linie_inne||[]).map(l=>`Linia ${E(l.id)} (${E(l.opis)}): <a href="${E(l.url)}" target="_blank" rel="noopener">rozkład</a>. `).join('')}Redakcja Życia Powiatu aktualizuje rozkłady przy każdej zmianie. Zauważyłeś błąd? Napisz do nas.</p>`;
 if(!nl)R.querySelector('[data-tab="bus"]').hidden=true;
 budujMiejsca();mchips();const h=(location.hash||'').slice(1),bt=R.querySelector('[data-tab="'+h+'"]');pokaz(bt&&!bt.hidden?h:'teraz');
}).catch(()=>{q('sub').textContent='Nie udało się wczytać rozkładów. Odśwież stronę.';});
})();
</script>
<?php return ob_get_clean();
});
