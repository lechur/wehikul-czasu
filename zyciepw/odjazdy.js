/* Odjazdy – samodzielny moduł komunikacji (Życie Powiatu). Źródło logiki: aplikacja Stacja Kobyłka (kobylka/moduly.js).
   Użycie: <div id="x"></div><script src="odjazdy.js"></script><script>ZPKom.mount(document.getElementById('x'),{dane:'komunikacja.json'})</script> */
(function(){
'use strict';
const Q=s=>document.querySelector(s),QA=s=>[...document.querySelectorAll(s)];
const E=s=>String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const LS=(k,v)=>{try{if(v===undefined){const x=localStorage.getItem('zpk_'+k);return x?JSON.parse(x):null;}localStorage.setItem('zpk_'+k,JSON.stringify(v));}catch(e){}return null;};
const t2m=t=>{const p=String(t).split(':');return(+p[0])*60+(+p[1]);};
const m2t=m=>{m=((m%1440)+1440)%1440;return Math.floor(m/60)+':'+String(m%60).padStart(2,'0');};
const dzienTyp=()=>{const d=new Date().getDay();return d===0?'niedziela':d===6?'sobota':'robocze';};
const DN={robocze:'Dni robocze',sobota:'Soboty',niedziela:'Niedziele i święta'};
const teraz=()=>{const d=new Date();return d.getHours()*60+d.getMinutes();};
let ROOT=null,OPT={},K=null;const KS={pieszo:3,stacja:null,me:null};
const say=t=>{if(OPT.toast)OPT.toast(t);else{let e=ROOT&&ROOT.querySelector('.zpk-say');if(!e&&ROOT){e=document.createElement('div');e.className='zpk-say';ROOT.prepend(e);}if(e){e.textContent=t;clearTimeout(e._t);e._t=setTimeout(()=>e.remove(),4000);}}};
const kDoj=()=>{},kRenderPrzes=()=>{},kRenderPoc=()=>{};
const ZCSS=`.zpk{font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:var(--ink,#16181b);line-height:1.45;font-size:16px;max-width:720px;margin:0 auto;text-align:left}.zpk *{box-sizing:border-box}
.zpk h3{font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:var(--mut,#667085);font-weight:700}
.zpk .mut{color:var(--mut,#667085)}.zpk p{margin:0}
.zpk .seg.k2{display:grid;grid-template-columns:1fr 1fr;background:var(--soft,#f1f4f8);border-radius:12px;padding:3px;gap:0}
.zpk .seg.k2 button{border:0;background:none;border-radius:9px;padding:10px 6px;font:inherit;font-weight:700;font-size:13.5px;color:var(--mut,#667085);cursor:pointer;box-shadow:none}
.zpk .seg.k2 button.on{background:var(--card,#fff);color:var(--ink,#16181b);box-shadow:0 1px 3px rgba(0,0,0,.14)}
.zpk input[type=search]{font:inherit;font-size:16px;border:1.5px solid var(--line,#e3e7ee);border-radius:12px;padding:11px 12px;background:var(--card,#fff);color:var(--ink,#16181b)}
.zpk .btn.sm{display:inline-flex;align-items:center;gap:6px;background:#14315a;color:#fff;border-radius:10px;padding:9px 13px;font-weight:700;font-size:14px;text-decoration:none;border:0}
.zpk .ktab{border-top:1px solid var(--line,#e3e7ee)}.zpk .kh{display:flex;gap:12px;padding:7px 2px;border-bottom:1px solid var(--line,#e3e7ee);font-variant-numeric:tabular-nums}.zpk .kh b{width:26px;text-align:right;font-size:16px}.zpk .kh span{flex:1;word-spacing:6px;font-size:15px}.zpk .kh u{text-decoration:none;font-weight:800;color:#0E7C6B}.zpk .kh.now{background:var(--card,#fff)}
.zpk .k2r:last-child{border-bottom:0}.zpk-say{background:#14315a;color:#fff;border-radius:10px;padding:9px 12px;font-size:14px;margin-bottom:8px}
.zpk .zpk-info{font-size:12.5px;color:var(--mut,#667085);margin:10px 4px 0}.zpk .zpk-info a{color:inherit}`;
const KOL=['#0E7C6B','#C2410C','#2563EB','#9333EA','#B45309','#BE185D','#0891B2','#4D7C0F'];
function kPrzyjazdy(l,ki,i,dzien){const k=l.kierunki[ki];if(!k)return[];if(k.czasy&&k.czasy[i]&&k.czasy[i][dzien])return k.czasy[i][dzien].map(t2m);if(!k.minuty||k.minuty[i]==null)return[];return((k.odjazdy||{})[dzien]||[]).map(t=>t2m(t)+k.minuty[i]);}
const iso=d=>d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
function dataTypu(t){const d=new Date();for(let i=0;i<8;i++){const x=new Date(d.getFullYear(),d.getMonth(),d.getDate()+i),w=x.getDay();if((w===0?'niedziela':w===6?'sobota':'robocze')===t)return iso(x);}return iso(d);}
function pocL(P,kier,t){const dz=dataTypu(t);return((P&&P[kier]||{})[t]||[]).filter(x=>!x.daty||x.daty.includes(dz));}
const maPociagi=()=>!!(K.pociagi&&K.pociagi.stacje&&Object.values(K.pociagi.stacje).some(s=>Object.values(s.do_warszawy||{}).some(a=>a.length)));
function kPunkty(stacja){const o=[];K.linie.forEach((l,li)=>l.kierunki.forEach((k,ki)=>(k.stacje||[]).forEach(s=>{if(s.stacja===stacja)o.push({l,li,ki,i:s.i});})));return o;}
function kOcena(czek,p){return czek<p?['zle','nie zdążysz']:czek<=p+1?['styk','na styk']:czek<=12?['ok','wygodnie']:czek<=20?['dlugo','długo']:['bdlugo','bardzo długo'];}
const KST={'Kobyłka':[52.33695,21.21557],'Kobyłka Ossów':[52.3273,21.19331]};
function kMapsUrl(c){return /iPhone|iPad|iPod/.test(navigator.userAgent)?`https://maps.apple.com/?daddr=${c.lat},${c.lon}&dirflg=w`:`https://www.google.com/maps/dir/?api=1&destination=${c.lat},${c.lon}&travelmode=walking`;}
/* ---------- Komunikacja 2: tablica odjazdów „skąd jadę” + szybkie połączenia z Warszawą ---------- */
const K2={skad:null,cel:'do',otw:null,wyb:false,szuk:'',blisko:null};
const k2css=`.k2top{display:flex;align-items:center;gap:10px;background:var(--card,#fff);border:1px solid var(--line,#e3e7ee);border-radius:16px;padding:10px 12px;margin:6px 0 12px}
.k2top .pin{font-size:22px}.k2top .tx{flex:1;min-width:0}.k2top .tx small{display:block;color:var(--mut,#667085);font-size:12px}.k2top .tx b{display:block;font-size:17px;line-height:1.2}
.k2top button{flex:none;border:1.5px solid var(--line,#e3e7ee);background:var(--card,#fff);color:var(--ink,#16181b);border-radius:999px;padding:8px 11px;font:inherit;font-weight:700;font-size:13.5px;cursor:pointer}
.k2pick{background:var(--card,#fff);border:1px solid var(--line,#e3e7ee);border-radius:16px;padding:12px;margin:-4px 0 12px}
.k2pick input{width:100%;margin:8px 0}.k2pl{max-height:300px;overflow-y:auto;border-top:1px solid var(--line,#e3e7ee)}
.k2pl button{display:flex;align-items:center;gap:10px;width:100%;text-align:left;background:none;border:0;border-bottom:1px solid var(--line,#e3e7ee);padding:11px 2px;font:inherit;color:var(--ink,#16181b);cursor:pointer}
.k2pl button b{flex:1;font-size:15.5px}.k2pl button small{color:var(--mut,#667085);font-size:12.5px;white-space:nowrap}.k2pl .ln{display:flex;gap:4px}.k2pl .ln i{font-style:normal;font-size:11px;font-weight:800;color:#fff;border-radius:6px;padding:2px 5px}
.k2h{display:flex;justify-content:space-between;align-items:baseline;margin:16px 2px 8px}.k2h h3{margin:0}
.k2j{background:var(--card,#fff);border:1px solid var(--line,#e3e7ee);border-radius:16px;overflow:hidden;margin-bottom:6px}
.k2o{display:flex;gap:12px;align-items:center;padding:12px;border-bottom:1px solid var(--line,#e3e7ee)}.k2o:last-child{border-bottom:0}
.k2o.first{background:var(--soft,#f1f4f8)}
.k2w{flex:none;width:74px;text-align:center}.k2w b{display:block;font-size:24px;line-height:1;font-weight:800;letter-spacing:-.02em;font-variant-numeric:tabular-nums}.k2w span{display:block;font-size:11.5px;color:var(--mut,#667085);margin-top:3px}
.k2w.soon b{color:#C2410C}
.k2s{flex:1;min-width:0;font-size:14.5px;line-height:1.45}.k2s .leg{display:flex;flex-wrap:wrap;align-items:center;gap:5px}.k2s .t{font-weight:800;font-variant-numeric:tabular-nums}
.k2s .sub{color:var(--mut,#667085);font-size:12.5px}
.k2b{display:inline-block;font-style:normal;font-weight:800;font-size:12.5px;color:#fff;border-radius:7px;padding:2px 7px;min-width:34px;text-align:center}
.k2chip{display:inline-block;font-size:12px;font-weight:700;border-radius:999px;padding:2px 8px;background:var(--soft,#f1f4f8)}
.k2chip.ok{background:#E5F4E4;color:#1f7a45}.k2chip.styk{background:#FFF1D6;color:#9a5b00}.k2chip.dlugo,.k2chip.bdlugo{background:var(--soft,#f1f4f8);color:var(--mut,#667085)}
.k2r{display:block;width:100%;background:none;border:0;border-bottom:1px solid var(--line,#e3e7ee);padding:12px;text-align:left;font:inherit;color:var(--ink,#16181b);cursor:pointer}
.k2r .row{display:flex;align-items:center;gap:11px}.k2r .k2b{font-size:15px;padding:6px 8px;min-width:50px;border-radius:9px}
.k2r .mid{flex:1;min-width:0}.k2r .mid b{display:block;font-size:16px;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.k2r .mid span{display:block;color:var(--mut,#667085);font-size:12.5px;margin-top:2px}
.k2r .cd{flex:none;text-align:right}.k2r .cd b{display:block;font-size:22px;font-weight:800;line-height:1;font-variant-numeric:tabular-nums}.k2r .cd span{font-size:11.5px;color:var(--mut,#667085)}
.k2r .cd.soon b{color:#C2410C}.k2r.off .cd b{font-size:15px;color:var(--mut,#667085)}
.k2x{padding:2px 12px 14px;border-bottom:1px solid var(--line,#e3e7ee);background:var(--soft,#f1f4f8)}
.k2x h4{margin:12px 0 6px;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:var(--mut,#667085)}
.k2tr{list-style:none;margin:0;padding:0}.k2tr li{display:flex;gap:10px;padding:4px 0;font-size:14.5px}.k2tr li b{width:44px;font-variant-numeric:tabular-nums}.k2tr li.st span{font-weight:700}
details.kdet{background:var(--card,#fff);border:1px solid var(--line,#e3e7ee);border-radius:16px;margin:10px 0;overflow:hidden}details.kdet>summary{list-style:none;cursor:pointer;padding:14px;font-weight:700;display:flex;justify-content:space-between;align-items:center}details.kdet>summary::-webkit-details-marker{display:none}details.kdet>summary::after{content:'›';font-size:22px;color:var(--mut,#667085);transform:rotate(90deg);transition:.15s}details.kdet[open]>summary::after{transform:rotate(-90deg)}details.kdet>.in{padding:0 12px 12px}details.kdet .card{box-shadow:none;border:0;padding:0;margin:0}
@media (prefers-color-scheme:dark){:root:not([data-theme=light]) .k2chip.ok{background:#14301f;color:#8fd6a8}:root:not([data-theme=light]) .k2chip.styk{background:#3a2f12;color:#f0c674}}
:root[data-theme=dark] .k2chip.ok{background:#14301f;color:#8fd6a8}:root[data-theme=dark] .k2chip.styk{background:#3a2f12;color:#f0c674}`;
const k2odl=(a,b,c,d)=>Math.hypot((b-d)*68000,(a-c)*111000);
const k2dzien=o=>{const d=new Date();d.setDate(d.getDate()+(o||0));const w=d.getDay();return w===0?'niedziela':w===6?'sobota':'robocze';};
const k2col=id=>{const i=K.linie.findIndex(l=>l.id===id);return i<0?'#14315a':KOL[i%KOL.length];};
let K2M=null;
function k2Miejsca(){if(K2M)return K2M;const M={};
 Object.keys(KST).forEach(n=>{M['s:'+n]={id:'s:'+n,n:'Stacja '+n,stacja:n,typ:'s',lat:KST[n][0],lon:KST[n][1],p:kPunkty(n).map(x=>({l:x.l,li:x.li,ki:x.ki,i:x.i}))};});
 K.linie.forEach((l,li)=>l.kierunki.forEach((k,ki)=>k.przystanki.forEach((p,i)=>{const id='p:'+p.nazwa;const m=M[id]||(M[id]={id,n:p.nazwa,typ:'p',la:[],lo:[],p:[]});m.p.push({l,li,ki,i});if(p.lat){m.la.push(p.lat);m.lo.push(p.lon);}})));
 Object.values(M).forEach(m=>{if(m.typ==='p'){m.lat=m.la.length?m.la.reduce((a,b)=>a+b,0)/m.la.length:null;m.lon=m.lo.length?m.lo.reduce((a,b)=>a+b,0)/m.lo.length:null;}m.linie=[...new Set(m.p.map(x=>x.l.id))];});
 return K2M=M;}
function k2Skad(){const M=k2Miejsca();return M[K2.skad]||M['s:'+KS.stacja]||Object.values(M)[0];}
/* czas przejazdu autobusu między przystankami i→j tego samego kierunku dla kursu nr n */
function k2Czas(l,ki,i,j,n,d){const A=kPrzyjazdy(l,ki,i,d),B=kPrzyjazdy(l,ki,j,d);if(A.length===B.length&&B[n]!=null&&B[n]>=A[n])return B[n];const k=l.kierunki[ki];if(k.minuty&&k.minuty[i]!=null&&k.minuty[j]!=null)return A[n]+k.minuty[j]-k.minuty[i];return null;}
/* odjazdy z miejsca pogrupowane: linia + kierunek */
function k2Grupy(m){const d=k2dzien(0),d1=k2dzien(1),now=teraz(),G={};
 const add=(key,o)=>{(G[key]=G[key]||Object.assign({t:[],j:[]},o));return G[key];};
 m.p.forEach(x=>{const k=x.l.kierunki[x.ki];if(x.i===k.przystanki.length-1)return;const g=add(x.l.id+'|'+x.ki,{typ:'b',linia:x.l.id,col:KOL[x.li%KOL.length],do:k.do,x,slupek:k.przystanki[x.i].slupek,nazwa:k.przystanki[x.i].nazwa});
  kPrzyjazdy(x.l,x.ki,x.i,d).forEach((t,n)=>{if(t>=now)g.t.push({m:t,n});});if(!g.t.length)kPrzyjazdy(x.l,x.ki,x.i,d1).slice(0,1).forEach(t=>g.j.push(t));g.all=kPrzyjazdy(x.l,x.ki,x.i,d);});
 if(m.typ==='s'&&maPociagi()){const P=K.pociagi.stacje[m.stacja];[['do_warszawy','Warszawa'],['z_warszawy','Wołomin, Tłuszcz']].forEach(a=>{const L=pocL(P,a[0],d).map(x=>({m:t2m(x.g),do:x.do})).sort((p,q)=>p.m-q.m);if(!L.length&&!pocL(P,a[0],d1).length)return;
   const g=add('poc|'+a[0],{typ:'t',linia:'Pociąg',col:'#14315a',do:a[1],kier:a[0]});g.t=L.filter(x=>x.m>=now);g.all=L.map(x=>x.m);g.allL=L;if(!g.t.length)pocL(P,a[0],d1).slice(0,1).forEach(x=>g.j.push(t2m(x.g)));});}
 return Object.keys(G).map(k=>Object.assign(G[k],{key:k})).filter(g=>g.all&&g.all.length||g.j.length).sort((a,b)=>(a.t[0]?a.t[0].m:9999+(a.j[0]||0))-(b.t[0]?b.t[0].m:9999+(b.j[0]||0)));}
const k2za=(m,now)=>{const z=m-now;return z<1?['teraz','']:z<60?[z+' min',m2t(m)]:[m2t(m),'za '+Math.floor(z/60)+' h '+(z%60)+' min'];};
/* połączenia do Warszawy z miejsca */
function k2DoWawy(m){const d=k2dzien(0),now=teraz(),O=[];if(!maPociagi())return O;const ST=K.pociagi.stacje;
 const poc=s=>pocL(ST[s],'do_warszawy',d).map(x=>({m:t2m(x.g),do:x.do})).sort((a,b)=>a.m-b.m);
 if(m.typ==='s'){poc(m.stacja).filter(x=>x.m>=now).slice(0,4).forEach(x=>O.push({wyj:x.m,poc:x.m,do:x.do,st:m.stacja,rodz:'s'}));return O;}
 m.p.forEach(x=>{const k=x.l.kierunki[x.ki];(k.stacje||[]).forEach(s=>{if(s.i<=x.i||!ST[s.stacja])return;const P=poc(s.stacja);
  kPrzyjazdy(x.l,x.ki,x.i,d).forEach((t,n)=>{if(t<now||t>now+150)return;const arr=k2Czas(x.l,x.ki,x.i,s.i,n,d);if(arr==null)return;const p=P.find(q=>q.m>=arr+KS.pieszo);if(!p)return;O.push({wyj:t,linia:x.l.id,col:KOL[x.li%KOL.length],arr,poc:p.m,do:p.do,st:s.stacja,czek:p.m-arr,rodz:'b',slupek:k.przystanki[x.i].slupek});});});});
 if(m.lat){let bs=null,bd=1e9;Object.keys(KST).forEach(n=>{const o=k2odl(m.lat,m.lon,KST[n][0],KST[n][1]);if(o<bd){bd=o;bs=n;}});const w=Math.ceil(bd*1.3/75);if(bs&&w<=25&&ST[bs]){poc(bs).filter(x=>x.m>=now+w).slice(0,2).forEach(p=>O.push({wyj:p.m-w,pieszo:w,poc:p.m,do:p.do,st:bs,rodz:'w'}));}}
 /* dla każdego pociągu zostaw najlepszą opcję: najpóźniejsze wyjście */
 const by={};O.forEach(o=>{const k=o.st+'|'+o.poc;if(!by[k]||o.wyj>by[k].wyj)by[k]=o;});return Object.values(by).filter(o=>o.wyj>=now).sort((a,b)=>a.poc-b.poc||b.wyj-a.wyj).slice(0,4);}
/* powrót z Warszawy: pociąg na stacji → autobus do mojego przystanku */
function k2ZWawy(m){const d=k2dzien(0),now=teraz(),O=[];if(!maPociagi())return O;const ST=K.pociagi.stacje;
 const poc=s=>pocL(ST[s],'z_warszawy',d).map(x=>({m:t2m(x.g),do:x.do})).sort((a,b)=>a.m-b.m);
 if(m.typ==='s'){poc(m.stacja).filter(x=>x.m>=now).slice(0,4).forEach(x=>O.push({poc:x.m,st:m.stacja,kon:x.m,rodz:'s',do:x.do}));return O;}
 const B=[];m.p.forEach(x=>{const k=x.l.kierunki[x.ki];(k.stacje||[]).forEach(s=>{if(s.i>=x.i||!ST[s.stacja])return;kPrzyjazdy(x.l,x.ki,s.i,d).forEach((t,n)=>{const arr=k2Czas(x.l,x.ki,s.i,x.i,n,d);if(arr!=null)B.push({st:s.stacja,bus:t,arr,linia:x.l.id,col:KOL[x.li%KOL.length]});});});});
 let bs=null,bd=1e9;if(m.lat)Object.keys(KST).forEach(n=>{const o=k2odl(m.lat,m.lon,KST[n][0],KST[n][1]);if(o<bd){bd=o;bs=n;}});const w=bs?Math.ceil(bd*1.3/75):99;
 Object.keys(ST).forEach(s=>{poc(s).filter(x=>x.m>=now).slice(0,5).forEach(p=>{const b=B.filter(q=>q.st===s&&q.bus>=p.m+KS.pieszo).sort((a,c)=>a.arr-c.arr)[0];const pw=s===bs&&w<=25?p.m+w:null;
  if(b&&(pw==null||b.arr<pw))O.push({poc:p.m,st:s,bus:b.bus,linia:b.linia,col:b.col,kon:b.arr,czek:b.bus-p.m,rodz:'b'});else if(pw!=null)O.push({poc:p.m,st:s,pieszo:w,kon:pw,rodz:'w'});});});
 const by={};O.forEach(o=>{if(!by[o.poc]||o.kon<by[o.poc].kon)by[o.poc]=o;});return Object.values(by).sort((a,b)=>a.poc-b.poc).slice(0,4);}
function k2Render(){const box=ROOT;if(!box)return;const m=k2Skad(),now=teraz(),M=k2Miejsca();
 let h=`<div class="k2top"><div class="tx"><small>Jadę z</small><b>${E(m.n)}</b></div><button data-k2="gps" aria-label="Znajdź przystanek najbliżej mnie">📍 Blisko</button><button data-k2="zmien">${K2.wyb?'Zamknij':'Zmień'}</button></div>`;
 if(K2.wyb){const q=K2.szuk.toLowerCase();let L=K2.blisko&&!q?K2.blisko:Object.values(M).filter(x=>!q||x.n.toLowerCase().includes(q)).sort((a,b)=>(a.typ==='s'?0:1)-(b.typ==='s'?0:1)||a.n.localeCompare(b.n,'pl'));
  h+=`<div class="k2pick"><b>${K2.blisko&&!q?'Najbliżej Ciebie':'Wybierz przystanek lub stację'}</b><input id="k2-q" type="search" placeholder="Szukaj przystanku, np. Kościół" value="${E(K2.szuk)}" autocomplete="off"><div class="k2pl">${L.slice(0,80).map(x=>`<button data-k2m="${E(x.id)}"><span>${x.typ==='s'?'🚆':'🚌'}</span><b>${E(x.n)}</b>${x.odl!=null?`<small>${x.odl<1000?Math.round(x.odl/10)*10+' m':(x.odl/1000).toFixed(1).replace('.',',')+' km'}</small>`:''}<span class="ln">${x.linie.slice(0,4).map(l=>`<i style="background:${k2col(l)}">${E(l)}</i>`).join('')}</span></button>`).join('')||'<p class="mut" style="padding:10px 0">Nie ma takiego przystanku.</p>'}</div></div>`;}
 if(maPociagi()){const O=K2.cel==='do'?k2DoWawy(m):k2ZWawy(m);
  h+=`<div class="seg k2" id="k2-cel"><button data-k2c="do" class="${K2.cel==='do'?'on':''}">Do Warszawy</button><button data-k2c="z" class="${K2.cel==='z'?'on':''}">Z Warszawy do domu</button></div><div class="k2j" style="margin-top:8px">`;
  if(!O.length)h+=`<div class="k2o"><div class="k2s"><b>Dziś nie ma już połączeń.</b><div class="sub">Sprawdź pełny rozkład pociągów niżej.</div></div></div>`;
  O.forEach((o,i)=>{const oc=o.czek!=null?kOcena(o.czek,KS.pieszo):null;
   if(K2.cel==='do'){const z=k2za(o.wyj,now);
    h+=`<div class="k2o${i===0?' first':''}"><div class="k2w${o.wyj-now<=5?' soon':''}"><b>${z[0]}</b><span>${o.rodz==='w'?'wyjdź':o.rodz==='s'?'odjazd':'autobus'}${z[1]?' '+z[1]:''}</span></div><div class="k2s">`+
     (o.rodz==='b'?`<div class="leg"><span class="t">${m2t(o.wyj)}</span><i class="k2b" style="background:${o.col}">${E(o.linia)}</i><span>→ ${E(o.st)} <span class="t">${m2t(o.arr)}</span></span></div>`:o.rodz==='w'?`<div class="leg"><span>🚶 pieszo ok. ${o.pieszo} min na stację ${E(o.st)}</span></div>`:'')+
     `<div class="leg"><i class="k2b" style="background:#14315a">Pociąg</i><span class="t">${m2t(o.poc)}</span><span>→ ${E(o.do)}</span>${oc?`<span class="k2chip ${oc[0]}">${o.czek} min na przesiadkę</span>`:''}</div></div></div>`;}
   else{const z=k2za(o.poc,now);
    h+=`<div class="k2o${i===0?' first':''}"><div class="k2w"><b>${m2t(o.poc)}</b><span>pociąg na stacji</span></div><div class="k2s"><div class="leg"><i class="k2b" style="background:#14315a">Pociąg</i><span>z Warszawy · stacja ${E(o.st)}</span></div>`+
     (o.rodz==='b'?`<div class="leg"><i class="k2b" style="background:${o.col}">${E(o.linia)}</i><span class="t">${m2t(o.bus)}</span><span>→ ${E(m.n)} <span class="t">${m2t(o.kon)}</span></span>${oc?`<span class="k2chip ${oc[0]}">${o.czek} min na przesiadkę</span>`:''}</div>`:o.rodz==='w'?`<div class="leg"><span>🚶 pieszo ok. ${o.pieszo} min · w domu ok. <span class="t">${m2t(o.kon)}</span></span></div>`:`<div class="sub">dalej: ${E(o.do)}</div>`)+`</div></div>`;}});
  h+=`</div><p class="mut" style="margin:6px 4px 0;font-size:12.5px">${K2.cel==='do'?'Godziny odjazdu pociągu ze stacji w Kobyłce.':'Godziny przyjazdu pociągu z Warszawy do Kobyłki.'} Na przesiadkę liczymy ${KS.pieszo} min dojścia.</p>`;}
 const G=k2Grupy(m);
 h+=`<div class="k2h"><h3>Odjazdy: ${E(m.n)}</h3><span class="mut">${new Date().toLocaleTimeString('pl-PL',{hour:'2-digit',minute:'2-digit'})}</span></div><div class="k2j">`;
 if(!G.length)h+=`<div class="k2o"><div class="k2s"><b>Z tego miejsca nic dziś nie odjeżdża.</b></div></div>`;
 G.forEach(g=>{const t=g.t[0],z=t?k2za(t.m,now):null,open=K2.otw===g.key;
  h+=`<button class="k2r${t?'':' off'}" data-k2g="${E(g.key)}" aria-expanded="${open}"><div class="row"><i class="k2b" style="background:${g.col}">${E(g.linia)}</i><div class="mid"><b>→ ${E(g.do)}</b><span>${t?(g.t.slice(1,3).length?'potem '+g.t.slice(1,3).map(x=>m2t(x.m)).join(', '):'ostatni kurs dziś'):'dziś już nie jedzie'}${g.typ==='b'&&m.typ==='s'?' · przyst. '+E(g.nazwa):''}${g.slupek&&m.typ==='p'?' · słupek '+E(g.slupek):''}</span></div><div class="cd${t&&t.m-now<=5?' soon':''}"><b>${t?z[0]:(g.j.length?m2t(g.j[0]):'–')}</b><span>${t?z[1]:(g.j.length?'jutro':'')}</span></div></div></button>`;
  if(open){const hrs={};(g.all||[]).forEach(x=>{const hh=Math.floor(x/60)%24;(hrs[hh]=hrs[hh]||[]).push(String(x%60).padStart(2,'0'));});const hN=Math.floor(now/60);
   h+=`<div class="k2x">`;
   if(g.typ==='b'&&t){const k=g.x.l.kierunki[g.x.ki],d=k2dzien(0);const S=[],mi=k.minuty||[],base=j=>mi[j]!=null&&mi[g.x.i]!=null?t.m+mi[j]-mi[g.x.i]:null,AN=[{j:g.x.i,dl:0}];
    if(k.czasy)Object.keys(k.czasy).map(Number).filter(j=>j>g.x.i).sort((p,q)=>p-q).forEach(j=>{const T=(k.czasy[j][d]||[]).map(t2m),A0=kPrzyjazdy(g.x.l,g.x.ki,g.x.i,d);if(T.length===A0.length&&T[t.n]!=null&&base(j)!=null)AN.push({j,dl:T[t.n]-base(j)});});
    for(let j=g.x.i;j<k.przystanki.length;j++){let bm=base(j);if(bm==null)continue;let p=AN[0],q=null;AN.forEach(x=>{if(x.j<=j)p=x;else if(!q)q=x;});const dl=q&&q.j>p.j?p.dl+(q.dl-p.dl)*((mi[j]-mi[p.j])/Math.max(1,mi[q.j]-mi[p.j])):p.dl;const a=Math.round(bm+dl);S.push({n:k.przystanki[j].nazwa,a:S.length?Math.max(a,S[S.length-1].a):a,st:(k.stacje||[]).some(s=>s.i===j)});}
    if(S.length>1)h+=`<h4>Kurs ${m2t(t.m)} – kolejne przystanki (±2 min)</h4><ul class="k2tr">${S.map(s=>`<li class="${s.st?'st':''}"><b>${m2t(s.a)}</b><span>${s.st?'🚆 ':''}${E(s.n)}</span></li>`).join('')}</ul>`;}
   if(g.typ==='t'&&g.t.length)h+=`<h4>Najbliższe pociągi</h4><ul class="k2tr">${g.allL.filter(x=>x.m>=now).slice(0,8).map(x=>`<li><b>${m2t(x.m)}</b><span>→ ${E(x.do)}</span></li>`).join('')}</ul>`;
   h+=`<h4>Wszystkie odjazdy dziś (${DN[k2dzien(0)].toLowerCase()})</h4>${Object.keys(hrs).length?`<div class="ktab" style="margin-top:0">${Object.keys(hrs).map(Number).sort((a,b)=>a-b).map(hh=>`<div class="kh${hh===hN?' now':''}"><b>${hh}</b><span>${hrs[hh].map(x=>hh*60+ +x>=now&&hh*60+ +x<now+60?'<u>'+x+'</u>':x).join(' ')}</span></div>`).join('')}</div>`:'<p class="mut">Dziś ta linia nie kursuje.</p>'}`;
   if(g.typ==='b'){const p=g.x.l.kierunki[g.x.ki].przystanki[g.x.i];if(p.lat)h+=`<a class="btn sm" style="margin-top:10px" target="_blank" rel="noopener" href="${kMapsUrl({lat:p.lat,lon:p.lon})}">🧭 Nawiguj na ten przystanek</a>`;}
   else h+=`<a class="btn sm" style="margin-top:10px" target="_blank" rel="noopener" href="${kMapsUrl({lat:m.lat,lon:m.lon})}">🧭 Nawiguj na stację</a>`;
   h+=`</div>`;}});
 h+=`</div><p class="mut" style="margin:6px 4px 0;font-size:12.5px">Dotknij linii, żeby zobaczyć przystanki i wszystkie godziny. Czasy z rozkładów${K.stan?' (stan na '+E(K.stan)+')':''}, bez opóźnień.</p>`;
 const foc=document.activeElement&&document.activeElement.id==='k2-q',pos=foc?document.activeElement.selectionStart:0;box.innerHTML=h;if(foc){const i=Q('#k2-q');i.focus();try{i.setSelectionRange(pos,pos);}catch(e){}}
}
function k2Ustaw(id){const M=k2Miejsca();if(!M[id])return;K2.skad=id;LS('kom_skad',id);K2.wyb=false;K2.szuk='';K2.otw=null;const m=M[id];
 let st=m.typ==='s'?m.stacja:null;if(!st&&m.lat){let bd=1e9;Object.keys(KST).forEach(n=>{const o=k2odl(m.lat,m.lon,KST[n][0],KST[n][1]);if(o<bd){bd=o;st=n;}});}
 if(st&&st!==KS.stacja){KS.stacja=st;LS('kom_stacja',st);QA('#k-st button').forEach(x=>x.classList.toggle('on',x.dataset.s===st));KS.cel=null;KS.trasa=null;kDoj();if(maPociagi()){kRenderPrzes();kRenderPoc();}}
 k2Render();}
function k2Init(){if(!Q('#zpkcss')){const s=document.createElement('style');s.id='zpkcss';s.textContent=k2css+ZCSS;document.head.appendChild(s);}
 const M=k2Miejsca();K2.skad=LS('kom_skad');if(!M[K2.skad])K2.skad='s:'+KS.stacja;
 const box=ROOT;
 box.addEventListener('click',e=>{const b=e.target.closest('[data-k2],[data-k2m],[data-k2c],[data-k2g]');if(!b)return;e.stopPropagation();
  if(b.dataset.k2==='zmien'){K2.wyb=!K2.wyb;K2.blisko=null;k2Render();if(K2.wyb){const i=Q('#k2-q');if(i&&innerWidth>700)i.focus();}}
  else if(b.dataset.k2==='gps'){if(!navigator.geolocation){say('Telefon nie udostępnia lokalizacji.');return;}b.textContent='Szukam…';navigator.geolocation.getCurrentPosition(p=>{KS.me=[+p.coords.latitude.toFixed(5),+p.coords.longitude.toFixed(5)];const L=Object.values(M).filter(x=>x.lat).map(x=>Object.assign(x,{odl:k2odl(KS.me[0],KS.me[1],x.lat,x.lon)})).sort((a,c)=>a.odl-c.odl).slice(0,6);if(!L.length){say('Nie znalazłem przystanków.');k2Render();return;}if(L[0].odl>6000){say('Jesteś daleko od Kobyłki – wybierz przystanek z listy.');K2.wyb=true;K2.blisko=null;k2Render();return;}K2.blisko=L;K2.wyb=true;K2.szuk='';k2Ustaw(L[0].id);K2.wyb=true;K2.blisko=L;k2Render();say('Najbliżej: '+L[0].n);},()=>{say('Brak zgody na lokalizację. Wybierz przystanek z listy.');K2.wyb=true;k2Render();},{enableHighAccuracy:true,timeout:10000,maximumAge:60000});}
  else if(b.dataset.k2m)k2Ustaw(b.dataset.k2m);
  else if(b.dataset.k2c){K2.cel=b.dataset.k2c;k2Render();}
  else if(b.dataset.k2g){K2.otw=K2.otw===b.dataset.k2g?null:b.dataset.k2g;k2Render();}});
 box.addEventListener('input',e=>{if(e.target.id==='k2-q'){K2.szuk=e.target.value;k2Render();}});
 k2Render();setInterval(()=>{if(ROOT&&ROOT.offsetParent!==null&&!K2.wyb)k2Render();},30000);}


async function mount(el,opt){OPT=opt||{};ROOT=el;el.classList.add('zpk');el.innerHTML='<p class="mut">Wczytuję rozkłady…</p>';
 try{K=await fetch(OPT.dane||'komunikacja.json').then(r=>{if(!r.ok)throw 0;return r.json();});}catch(e){K=null;}
 if(!K||!K.linie){el.innerHTML='<p class="mut">Nie udało się wczytać rozkładów. Sprawdź połączenie.</p>';return;}
 KS.stacja=Object.keys(KST)[0];const inner=document.createElement('div');el.innerHTML='';el.appendChild(inner);const stopka=document.createElement('p');stopka.className='zpk-info';
 stopka.innerHTML='Na razie linie '+K.linie.map(l=>E(l.id)).join(', ')+(maPociagi()?' oraz pociągi Kolei Mazowieckich ze stacji '+Object.keys(K.pociagi.stacje).map(E).join(' i '):'')+'. Kolejne linie powiatu dodajemy. '+(K.pociagi&&K.pociagi.wazny?'Rozkład kolejowy: '+E(K.pociagi.wazny)+'. ':'')+'Źródła: <a href="'+E(K.zrodlo_rozkladow||'https://www.wtp.waw.pl/rozklady-jazdy/')+'" target="_blank" rel="noopener">ZTM Warszawa</a>'+(K.pociagi&&K.pociagi.zrodlo?', <a href="'+E(K.pociagi.zrodlo)+'" target="_blank" rel="noopener">Koleje Mazowieckie</a>':'')+'.';
 el.appendChild(stopka);ROOT=inner;inner.classList.add('zpk');k2Init();if(OPT.onReady)OPT.onReady();}
window.ZPKom={mount,odswiez:()=>{if(ROOT&&K)k2Render();}};
})();
