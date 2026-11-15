/* Moduły: komunikacja, budżet miasta, wniosek do budżetu, budżet obywatelski.
   Dane: komunikacja.json, budzet.json, bo.json (odświeżane w cotygodniowym zadaniu). */
(function(){
'use strict';
const Q=s=>document.querySelector(s),QA=s=>[...document.querySelectorAll(s)];
const E=s=>String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const LS=(k,v)=>{try{if(v===undefined){const x=localStorage.getItem('mk_'+k);return x?JSON.parse(x):null;}localStorage.setItem('mk_'+k,JSON.stringify(v));}catch(e){}return null;};
const zl=n=>Math.round(n).toLocaleString('pl-PL');
const mln=n=>n>=1e6?(n/1e6).toLocaleString('pl-PL',{maximumFractionDigits:1})+' mln zł':zl(n)+' zł';
const t2m=t=>{const p=String(t).split(':');return(+p[0])*60+(+p[1]);};
const m2t=m=>{m=((m%1440)+1440)%1440;return Math.floor(m/60)+':'+String(m%60).padStart(2,'0');};
const MK=window.MK||{};
const say=t=>{if(MK.toast)MK.toast(t);};
const DATA={};
async function load(k){if(DATA[k]!==undefined)return DATA[k];try{DATA[k]=await fetch(k+'.json').then(r=>{if(!r.ok)throw 0;return r.json();});}catch(e){DATA[k]=null;}return DATA[k];}
const dark=()=>getComputedStyle(document.documentElement).getPropertyValue('color-scheme').includes('dark');
const dzienTyp=()=>{const d=new Date().getDay();return d===0?'niedziela':d===6?'sobota':'robocze';};
const DN={robocze:'Dni robocze',sobota:'Soboty',niedziela:'Niedziele i święta'};
const teraz=()=>{const d=new Date();return d.getHours()*60+d.getMinutes();};

/* ---------- mapa na canvasie z przesuwaniem i powiększaniem ---------- */
function Mapa(cv,rysuj,klik){
 const v={cx:0,cy:0,w:7000};let rdy=false;
 function draw(){const MAPA=MK.MAPA;if(!MAPA||!MAPA._r)return;const dpr=window.devicePixelRatio||1,w=cv.clientWidth,h=cv.clientHeight;if(!w)return;
  if(!rdy){v.cx=MAPA.center[0];v.cy=MAPA.center[1];rdy=true;}
  cv.width=w*dpr;cv.height=h*dpr;const c=cv.getContext('2d');c.scale(dpr,dpr);const s=w/v.w,X=x=>w/2+(x-v.cx)*s,Y=y=>h/2-(y-v.cy)*s,dk=dark();
  c.fillStyle=dk?'#1b2638':'#f1f3f6';c.fillRect(0,0,w,h);
  const poly=(L,col)=>{c.fillStyle=col;for(const p of L){c.beginPath();c.moveTo(X(p[0]),Y(p[1]));for(let i=2;i<p.length;i+=2)c.lineTo(X(p[i]),Y(p[i+1]));c.fill();}};
  poly(MAPA._f,dk?'#1f3a2e':'#d9e8d6');poly(MAPA._w,dk?'#27405c':'#b9d3e6');
  const W=[2.4,1.9,1.4,1,.6,.3];c.strokeStyle=dk?'#5d6a80':'#aab3c2';c.lineCap='round';c.lineJoin='round';
  MAPA._r.forEach((cl,i)=>{if(i>=4&&v.w>9000)return;c.lineWidth=W[i]*Math.min(2.2,Math.max(1,5000/v.w));c.globalAlpha=i>=4?.5:.9;c.beginPath();cl.forEach(p=>{c.moveTo(X(p[0]),Y(p[1]));for(let k=2;k<p.length;k+=2)c.lineTo(X(p[k]),Y(p[k+1]));});c.stroke();});c.globalAlpha=1;
  rysuj(c,X,Y,v,dk,w,h);}
 const pts=new Map();let drag=null,pinch=null,moved=false;
 cv.style.touchAction='none';
 cv.addEventListener('pointerdown',e=>{cv.setPointerCapture(e.pointerId);pts.set(e.pointerId,[e.clientX,e.clientY]);drag=[e.clientX,e.clientY,v.cx,v.cy];moved=false;if(pts.size===2){const[a,b]=[...pts.values()];pinch=[Math.hypot(a[0]-b[0],a[1]-b[1]),v.w];}});
 cv.addEventListener('pointermove',e=>{if(!pts.has(e.pointerId))return;pts.set(e.pointerId,[e.clientX,e.clientY]);
  if(pts.size===2&&pinch){const[a,b]=[...pts.values()],d=Math.hypot(a[0]-b[0],a[1]-b[1]);v.w=Math.min(16000,Math.max(700,pinch[1]*pinch[0]/d));moved=true;draw();return;}
  if(!drag)return;const dx=e.clientX-drag[0],dy=e.clientY-drag[1];if(Math.abs(dx)+Math.abs(dy)>6)moved=true;const s=cv.clientWidth/v.w;v.cx=drag[2]-dx/s;v.cy=drag[3]+dy/s;draw();});
 const up=e=>{pts.delete(e.pointerId);if(pts.size<2)pinch=null;if(!moved&&drag&&klik){const r=cv.getBoundingClientRect(),s=cv.clientWidth/v.w;klik(v.cx+(e.clientX-r.left-cv.clientWidth/2)/s,v.cy-(e.clientY-r.top-cv.clientHeight/2)/s,12/s);}drag=null;};
 cv.addEventListener('pointerup',up);cv.addEventListener('pointercancel',e=>{pts.delete(e.pointerId);drag=null;pinch=null;});
 cv.addEventListener('wheel',e=>{e.preventDefault();v.w=Math.min(16000,Math.max(700,v.w*(e.deltaY>0?1.15:.87)));draw();},{passive:false});
 return{draw,v,zoom:k=>{v.w=Math.min(16000,Math.max(700,v.w*k));draw();},cel:(x,y,w)=>{v.cx=x;v.cy=y;if(w)v.w=w;rdy=true;draw();}};
}
const EN=(lat,lon)=>MK.fwd(lon,lat);
const zoomBtns=id=>`<div class="mz"><button data-z="${id}" data-k=".7" aria-label="Powiększ">+</button><button data-z="${id}" data-k="1.4" aria-label="Pomniejsz">−</button></div>`;

/* =====================================================================
   KOMUNIKACJA
   ===================================================================== */
const KOL=['#0E7C6B','#C2410C','#2563EB','#9333EA','#B45309','#BE185D','#0891B2','#4D7C0F'];
let K=null,KS={linia:null,kier:0,przyst:null,dzien:dzienTyp(),stacja:null,tryb:'na',pieszo:3,mapaL:null},kMapa=null;
function kPrzyjazdy(l,ki,i,dzien){const k=l.kierunki[ki];if(!k)return[];if(k.czasy&&k.czasy[i]&&k.czasy[i][dzien])return k.czasy[i][dzien].map(t2m);if(!k.minuty||k.minuty[i]==null)return[];return((k.odjazdy||{})[dzien]||[]).map(t=>t2m(t)+k.minuty[i]);}
const iso=d=>d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
function dataTypu(t){const d=new Date();for(let i=0;i<8;i++){const x=new Date(d.getFullYear(),d.getMonth(),d.getDate()+i),w=x.getDay();if((w===0?'niedziela':w===6?'sobota':'robocze')===t)return iso(x);}return iso(d);}
function pocL(P,kier,t){const dz=dataTypu(t);return((P&&P[kier]||{})[t]||[]).filter(x=>!x.daty||x.daty.includes(dz));}
const maPociagi=()=>!!(K.pociagi&&K.pociagi.stacje&&Object.values(K.pociagi.stacje).some(s=>Object.values(s.do_warszawy||{}).some(a=>a.length)));
function kStacje(){const s=new Set();if(maPociagi())Object.keys(K.pociagi.stacje).forEach(x=>s.add(x));K.linie.forEach(l=>l.kierunki.forEach(k=>(k.stacje||[]).forEach(x=>s.add(x.stacja))));return[...s];}
function kPunkty(stacja){const o=[];K.linie.forEach((l,li)=>l.kierunki.forEach((k,ki)=>(k.stacje||[]).forEach(s=>{if(s.stacja===stacja)o.push({l,li,ki,i:s.i});})));return o;}
function kOcena(czek,p){return czek<p?['zle','nie zdążysz']:czek<=p+1?['styk','na styk']:czek<=12?['ok','wygodnie']:czek<=20?['dlugo','długo']:['bdlugo','bardzo długo'];}
function kPrzesiadki(){
 const st=KS.stacja,P=K.pociagi&&K.pociagi.stacje&&K.pociagi.stacje[st];if(!P)return[];const d=KS.dzien,out=[];
 if(KS.tryb==='na'){const poc=pocL(P,'do_warszawy',d).map(x=>({m:t2m(x.g),do:x.do})).sort((a,b)=>a.m-b.m);
  kPunkty(st).forEach(p=>{if(p.i===0)return;kPrzyjazdy(p.l,p.ki,p.i,d).forEach(a=>{const mozl=poc.filter(x=>x.m>=a);const pierw=mozl[0],zd=mozl.find(x=>x.m-a>=KS.pieszo);
   out.push({linia:p.l.id,li:p.li,a,b:zd?zd.m:null,czek:zd?zd.m-a:null,ucieka:pierw&&zd&&pierw!==zd?pierw.m-a:null,opis:zd?zd.do:''});});});}
 else{const poc=pocL(P,'z_warszawy',d).map(x=>t2m(x.g)).sort((a,b)=>a-b);
  const bus=[];kPunkty(st).forEach(p=>{const k=p.l.kierunki[p.ki];if(p.i===k.przystanki.length-1)return;kPrzyjazdy(p.l,p.ki,p.i,d).forEach(m=>bus.push({m,linia:p.l.id,li:p.li,do:k.do}));});bus.sort((a,b)=>a.m-b.m);
  poc.forEach(a=>{const mozl=bus.filter(x=>x.m>=a),pierw=mozl[0],zd=mozl.find(x=>x.m-a>=KS.pieszo);
   out.push({linia:zd?zd.linia:'',li:zd?zd.li:0,a,b:zd?zd.m:null,czek:zd?zd.m-a:null,ucieka:pierw&&zd&&pierw!==zd?pierw.m-a:null,opis:zd?'kier. '+zd.do:''});});}
 return out.sort((x,y)=>x.a-y.a);
}
function kRenderPrzes(){
 const box=Q('#k-prz');if(!box)return;const R=kPrzesiadki(),na=KS.tryb==='na';
 if(!R.length){box.innerHTML='<p class="empty">Brak kursów do porównania w tym dniu.</p>';Q('#k-prz-sum').innerHTML='';return;}
 const licz={ok:0,styk:0,dlugo:0,bdlugo:0,brak:0};R.forEach(r=>{if(r.b==null)licz.brak++;else licz[kOcena(r.czek,KS.pieszo)[0]]++;});
 const n=R.length,pr=x=>Math.round(x/n*100);
 Q('#k-prz-sum').innerHTML=`<div class="kbig"><b>${licz.ok}</b><span>z ${n} ${na?'kursów autobusu':'pociągów'} ma wygodną przesiadkę (do 12 minut czekania)</span></div>
  <div class="kbar"><i class="ok" style="flex:${licz.ok}"></i><i class="styk" style="flex:${licz.styk}"></i><i class="dlugo" style="flex:${licz.dlugo}"></i><i class="bdlugo" style="flex:${licz.bdlugo}"></i><i class="zle" style="flex:${licz.brak}"></i></div>
  <div class="kleg"><span><i class="ok"></i>wygodnie ${pr(licz.ok)}%</span><span><i class="styk"></i>na styk ${pr(licz.styk)}%</span><span><i class="dlugo"></i>13–20 min ${pr(licz.dlugo)}%</span><span><i class="bdlugo"></i>ponad 20 min ${pr(licz.bdlugo)}%</span>${licz.brak?`<span><i class="zle"></i>brak połączenia ${pr(licz.brak)}%</span>`:''}</div>`;
 const now=teraz(),dzis=KS.dzien===dzienTyp();let nast=false;
 box.innerHTML=R.map(r=>{const o=r.b==null?['zle','brak']:kOcena(r.czek,KS.pieszo);const cur=dzis&&!nast&&r.a>=now?(nast=true,' cur'):'';
  return `<div class="krow${cur}"><div class="kt"><b>${m2t(r.a)}</b><span>${na?'autobus '+E(r.linia):'pociąg z Warszawy'}</span></div><div class="ka">→</div><div class="kt"><b>${r.b==null?'–':m2t(r.b)}</b><span>${r.b==null?'tego dnia już nic':(na?'pociąg, '+E(r.opis):'autobus '+E(r.linia)+', '+E(r.opis))}</span></div><div class="kw ${o[0]}"><b>${r.b==null?'–':r.czek+' min'}</b><span>${o[1]}</span>${r.ucieka!=null?`<em>wcześniejszy odjeżdża po ${r.ucieka} min</em>`:''}</div></div>`;}).join('');
 const c=box.querySelector('.cur');if(c&&box.scrollTo)box.scrollTo({top:c.offsetTop-box.offsetTop-40});
}
function kRenderRozklad(){
 const l=K.linie.find(x=>x.id===KS.linia)||K.linie[0];if(!l)return;KS.linia=l.id;if(KS.kier>=l.kierunki.length)KS.kier=0;const k=l.kierunki[KS.kier];
 Q('#k-kier').innerHTML=l.kierunki.map((x,i)=>`<button data-kk="${i}" class="${i===KS.kier?'on':''}">→ ${E(x.do)}</button>`).join('');
 const idx=Math.min(KS.przyst==null?0:KS.przyst,k.przystanki.length-1);KS.przyst=idx;
 Q('#k-przyst').innerHTML=k.przystanki.map((p,i)=>`<option value="${i}"${i===idx?' selected':''}>${E(p.nazwa)}${p.slupek?' '+E(p.slupek):''}${k.minuty&&k.minuty[i]!=null?' (+'+k.minuty[i]+' min)':''}</option>`).join('');
 const T=kPrzyjazdy(l,KS.kier,idx,KS.dzien),g={};T.forEach(m=>{const h=Math.floor(m/60)%24;(g[h]=g[h]||[]).push(String(m%60).padStart(2,'0'));});
 const now=teraz(),dzis=KS.dzien===dzienTyp(),hN=Math.floor(now/60);
 Q('#k-tab').innerHTML=T.length?Object.keys(g).map(Number).sort((a,b)=>a-b).map(h=>`<div class="kh${dzis&&h===hN?' now':''}"><b>${h}</b><span>${g[h].map(x=>dzis&&h*60+ +x>=now&&h*60+ +x<now+60?'<u>'+x+'</u>':x).join(' ')}</span></div>`).join(''):'<p class="empty">W tym dniu linia nie kursuje.</p>';
 const dok=k.czasy&&k.czasy[idx];Q('#k-tab-info').innerHTML=(dok||idx===0?'Godziny z rozkładu dla tego przystanku. ':'Godziny wyliczone z czasu przejazdu od pierwszego przystanku, mogą różnić się o 1–2 minuty. ')+(l.uwagi?E(l.uwagi)+' ':'')+(k.wazny_od?'Rozkład ważny od '+E(k.wazny_od)+'. ':'')+(l.url?`<a href="${E(l.url)}" target="_blank" rel="noopener">Oficjalny rozkład</a>`:'');
}
function kRenderPoc(){
 const box=Q('#k-poc');if(!box)return;const kier=KS.pk||'do_warszawy',P=K.pociagi.stacje[KS.stacja];
 const L=pocL(P,kier,KS.dzien).slice().sort((a,b)=>t2m(a.g)-t2m(b.g));
 if(!L.length){box.innerHTML='<p class="empty">Brak pociągów w tym dniu.</p>';return;}
 const now=teraz(),dzis=KS.dzien===dzienTyp();let nast=false;
 box.innerHTML=L.map(x=>{const m=t2m(x.g),cur=dzis&&!nast&&m>=now?(nast=true,' cur'):'';const kto=kier==='do_warszawy'?x.do:x.do;
  return `<div class="krow${cur}"><div class="kt"><b>${E(x.g)}</b><span>${E(x.nr||'')}</span></div><div class="ka">→</div><div class="kt" style="flex:3"><b style="font-size:15px">${E(kto)}</b><span>${x.z&&kier==='do_warszawy'?'jedzie z: '+E(x.z):x.z?'z: '+E(x.z):''}${x.daty?' · tylko wybrane dni':''}</span></div></div>`;}).join('');
 const c=box.querySelector('.cur');if(c&&box.scrollTo)box.scrollTo({top:c.offsetTop-box.offsetTop-40});
}
function kNajblizsze(){
 const box=Q('#k-nast');if(!box)return;const st=KS.stacja,d=dzienTyp(),now=teraz(),o=[];
 const P=K.pociagi&&K.pociagi.stacje&&K.pociagi.stacje[st];
 if(P){pocL(P,'do_warszawy',d).forEach(x=>o.push({m:t2m(x.g),t:'Pociąg',do:x.do,c:'#14315a'}));pocL(P,'z_warszawy',d).forEach(x=>o.push({m:t2m(x.g),t:'Pociąg',do:x.do,c:'#14315a'}));}
 kPunkty(st).forEach(p=>{const k=p.l.kierunki[p.ki];if(p.i===k.przystanki.length-1)return;kPrzyjazdy(p.l,p.ki,p.i,d).forEach(m=>o.push({m,t:p.l.id,do:k.do,c:KOL[p.li%KOL.length]}));});
 const n=o.filter(x=>x.m>=now).sort((a,b)=>a.m-b.m).slice(0,6);
 box.innerHTML=n.length?n.map(x=>`<div class="kn"><i style="background:${x.c}">${E(x.t)}</i><span>${E(x.do)}</span><b>${x.m-now<1?'teraz':x.m-now<60?'za '+(x.m-now)+' min':m2t(x.m)}</b><small>${x.m-now<60?m2t(x.m):''}</small></div>`).join(''):'<p class="mut" style="margin:0">Dziś nie ma już odjazdów z tej stacji.</p>';
}
function kPozycje(k){ /* współrzędne przystanków w metrach; brakujące uzupełniamy między sąsiadami */
 const P=k.przystanki.map(p=>p.lat&&p.lon?EN(p.lat,p.lon):null);
 for(let i=0;i<P.length;i++){if(P[i])continue;let a=i-1,b=i+1;while(a>=0&&!P[a])a--;while(b<P.length&&!P[b])b++;if(a>=0&&b<P.length){const f=(i-a)/(b-a);P[i]=[P[a][0]+(P[b][0]-P[a][0])*f,P[a][1]+(P[b][1]-P[a][1])*f];P[i].przybl=1;}}
 return P;
}
function kRysuj(c,X,Y,v,dk,w,h){const byl={};
 K.linie.forEach((l,li)=>{if(KS.mapaL&&KS.mapaL!==l.id)return;const k=l.kierunki[0];if(!k)return;const P=l._p||(l._p=kPozycje(k));const col=KOL[li%KOL.length];
  c.strokeStyle=col;c.lineWidth=KS.mapaL?5:3.5;c.globalAlpha=.9;c.beginPath();let st=false;P.forEach(p=>{if(!p)return;if(!st){c.moveTo(X(p[0]),Y(p[1]));st=true;}else c.lineTo(X(p[0]),Y(p[1]));});c.stroke();c.globalAlpha=1;
  P.forEach((p,i)=>{if(!p)return;const st=(k.stacje||[]).some(s=>s.i===i);c.beginPath();c.arc(X(p[0]),Y(p[1]),st?7:4,0,7);c.fillStyle=st?'#14315a':(dk?'#0d131d':'#fff');c.fill();c.lineWidth=2;c.strokeStyle=col;c.stroke();
   const nz=k.przystanki[i].nazwa;if((v.w<4200||st)&&!byl[nz]){byl[nz]=1;c.font=(st?'700 ':'')+'11px system-ui';c.fillStyle=dk?'#e5e9f0':'#14213d';c.strokeStyle=dk?'#0d131d':'#fff';c.lineWidth=3;const t=nz;c.strokeText(t,X(p[0])+8,Y(p[1])+4);c.fillText(t,X(p[0])+8,Y(p[1])+4);}});});
 if(K.linie.every(l=>!(l._p||[]).some(Boolean))){c.fillStyle=dk?'#e5e9f0':'#14213d';c.font='13px system-ui';c.fillText('Brak współrzędnych przystanków w danych.',12,22);}
}
async function komInit(){
 const box=Q('#v-kom');if(!K){box.innerHTML='<h2>Komunikacja</h2><p class="mut">Wczytuję rozkłady…</p>';K=await load('komunikacja');}
 if(!K||!K.linie){box.innerHTML='<h2>Komunikacja</h2><div class="card mut">Nie udało się wczytać rozkładów. Sprawdź połączenie.</div>';return;}
 if(!K.linie.some(l=>(l.kierunki||[]).some(k=>k.odjazdy))){box.innerHTML=`<h2>Komunikacja</h2><p class="mut">Autobusy i pociągi w Kobyłce. Stan na ${E(K.stan||'')}.</p>
  <h3>Autobusy</h3><div class="card"><div class="klinie">${K.linie.map((l,i)=>`<span style="background:${KOL[i%KOL.length]}" title="${E(l.opis||'')}">${E(l.id)}</span>`).join('')}</div><p class="mut" style="margin:10px 0">Linie L to autobusy lokalne, ${E((K.linie.find(l=>/nocna/.test(l.opis||''))||{}).id||'')} to linia nocna ZTM.</p><div class="flex-wrap">${K.zrodlo?`<a class="btn sm" href="${E(K.zrodlo)}" target="_blank" rel="noopener">Rozkłady i schemat linii</a>`:''}<a class="btn sm line" href="https://www.wtp.waw.pl/rozklady-jazdy/" target="_blank" rel="noopener">Rozkłady ZTM</a></div></div>
  ${(K.bilety||[]).length?`<h3>Bilety</h3><div class="card">${K.bilety.map(x=>`<div class="step"><span>${E(x)}</span></div>`).join('')}<p class="mut" style="margin:8px 0 0">Według strony Urzędu Miasta.</p></div>`:''}
  <h3>Pociągi</h3><div class="card"><b>Stacje Kobyłka i Kobyłka Ossów</b><p class="mut" style="margin:4px 0 10px">Koleje Mazowieckie, linia Warszawa Wileńska – Tłuszcz.${K.pociagi&&K.pociagi.wazny?' Rozkład ważny: '+E(K.pociagi.wazny)+'.':''}</p><div class="flex-wrap"><a class="btn sm" href="https://portalpasazera.pl" target="_blank" rel="noopener">Odjazdy na żywo</a>${K.pociagi&&K.pociagi.zrodlo?`<a class="btn sm line" href="${E(K.pociagi.zrodlo)}" target="_blank" rel="noopener">Rozkład KM</a>`:''}</div></div>
  <div class="card" style="background:var(--warnbg);box-shadow:none"><b>Czasy przesiadek: w przygotowaniu</b><p class="mut" style="color:var(--ink);margin:4px 0 0">Tu pokażemy, ile minut według rozkładu jest na przesiadkę z autobusu L do pociągu i z powrotem, razem z mapą linii.</p></div>`;return;}
 if(box.dataset.ok){kNajblizsze();kRenderPrzes();kRenderRozklad();return;}box.dataset.ok=1;
 const S=kStacje();KS.stacja=LS('kom_stacja')||S[0]||null;if(S.indexOf(KS.stacja)<0)KS.stacja=S[0]||null;KS.linia=(K.linie.find(l=>l.kierunki.some(k=>((k.odjazdy||{})[KS.dzien]||[]).length))||K.linie[0]||{}).id;
 const dzSeg=id=>`<div class="seg" id="${id}">${Object.keys(DN).map(d=>`<button data-d="${d}" class="${d===KS.dzien?'on':''}">${DN[d]}</button>`).join('')}</div>`;
 box.innerHTML=`<h2>Komunikacja</h2>
 <p class="mut">Autobusy L${maPociagi()?', pociągi i przesiadki':''} w Kobyłce według rozkładów ZTM${maPociagi()?' i Kolei Mazowieckich':''}${K.stan?' (stan na '+E(K.stan)+')':''}.</p>
 ${S.length>1?`<div class="seg k2" id="k-st">${S.map(s=>`<button data-s="${E(s)}" class="${s===KS.stacja?'on':''}">Stacja ${E(s)}</button>`).join('')}</div>`:''}
 <div class="sec"><h3>Najbliższe odjazdy</h3><span class="mut" id="k-zegar"></span></div>
 <div class="card" id="k-nast"></div>
 <h3>Ile czasu na przesiadkę</h3>
 <div class="card">
  <div class="seg k2" id="k-tryb"><button data-t="na" class="on">Autobusem na pociąg do Warszawy</button><button data-t="z">Z pociągu na autobus</button></div>
  ${dzSeg('k-dz1')}
  <div class="kpiesz"><span>Dojście z przystanku na peron</span><div><button data-p="-1" aria-label="Mniej">−</button><b id="k-pieszo">${KS.pieszo} min</b><button data-p="1" aria-label="Więcej">+</button></div></div>
  <div id="k-prz-sum"></div>
  <div class="klist" id="k-prz"></div>
  <p class="mut" style="margin:10px 0 0">Liczone z rozkładów. Opóźnień nie uwzględniamy, dlatego przesiadka „na styk” w praktyce często się nie udaje.</p>
  <button class="btn sm gold" id="k-radni" style="margin-top:10px">Wyślij wynik radnym</button>
 </div>
 <div class="sec"><h3>Mapa linii</h3></div>
 <div class="card" style="padding:10px"><div class="kchips" id="k-lchips"></div><div class="mwrap"><canvas id="k-map" class="mcv"></canvas>${zoomBtns('k')}</div><p class="mut" style="margin:8px 4px 0">Przesuwaj i powiększaj. Ciemne punkty to przystanki przy stacjach kolejowych.</p></div>
 <h3>Rozkład jazdy</h3>
 <div class="card"><div class="kchips" id="k-linie"></div><div class="kchips" id="k-kier" style="margin-top:8px"></div>
  <label for="k-przyst">Przystanek</label><select id="k-przyst"></select>${dzSeg('k-dz2')}
  <div class="ktab" id="k-tab"></div><p class="mut" id="k-tab-info" style="margin:10px 0 0"></p></div>
 ${maPociagi()?`<h3>Rozkład pociągów</h3>
 <div class="card"><div class="seg k2" id="k-pk"><button data-pk="do_warszawy" class="on">Do Warszawy</button><button data-pk="z_warszawy">Z Warszawy</button></div>${dzSeg('k-dz3')}
  <div class="klist" id="k-poc"></div></div>`:''}
 <div class="card"><b>Pociągi</b><p class="mut" style="margin:4px 0 0">${K.pociagi&&K.pociagi.wazny?'Rozkład kolejowy: '+E(K.pociagi.wazny)+'. ':''}${K.pociagi&&K.pociagi.uwagi?E(K.pociagi.uwagi)+' ':''}${K.pociagi&&K.pociagi.zrodlo?`<a href="${E(K.pociagi.zrodlo)}" target="_blank" rel="noopener">Źródło</a>. `:''}Przed podróżą sprawdź utrudnienia na <a href="https://portalpasazera.pl" target="_blank" rel="noopener">portalpasazera.pl</a>.</p></div>`;
 const chips=(id,all)=>{Q(id).innerHTML=(all?`<button data-l="" class="${!KS.mapaL?'on':''}">Wszystkie</button>`:'')+K.linie.map((l,i)=>`<button data-l="${E(l.id)}" style="--k:${KOL[i%KOL.length]}" class="${(all?KS.mapaL:KS.linia)===l.id?'on':''}"><i></i>${E(l.id)}</button>`).join('');};
 chips('#k-lchips',1);chips('#k-linie',0);
 kMapa=Mapa(Q('#k-map'),kRysuj);
 const pts=[];K.linie.forEach(l=>{l._p=kPozycje(l.kierunki[0]||{przystanki:[]});l._p.forEach(p=>p&&pts.push(p));});
 const sp=[];K.linie.forEach(l=>l.kierunki.forEach(k=>(k.stacje||[]).forEach(x=>{const P=kPozycje(k)[x.i];if(P)sp.push(P);})));const cc=sp.length?sp:pts;if(cc.length){const xs=cc.map(p=>p[0]),ys=cc.map(p=>p[1]);kMapa.cel((Math.min(...xs)+Math.max(...xs))/2,(Math.min(...ys)+Math.max(...ys))/2,6500);}else kMapa.draw();
 box.addEventListener('click',e=>{const b=e.target.closest('button');if(!b)return;
  if(b.dataset.s){KS.stacja=b.dataset.s;LS('kom_stacja',KS.stacja);QA('#k-st button').forEach(x=>x.classList.toggle('on',x===b));kNajblizsze();kRenderPrzes();kRenderPoc();}
  else if(b.dataset.t){KS.tryb=b.dataset.t;QA('#k-tryb button').forEach(x=>x.classList.toggle('on',x===b));kRenderPrzes();}
  else if(b.dataset.pk){KS.pk=b.dataset.pk;QA('#k-pk button').forEach(x=>x.classList.toggle('on',x===b));kRenderPoc();}
  else if(b.dataset.d){KS.dzien=b.dataset.d;kRenderPoc();QA('#k-dz1 button,#k-dz2 button,#k-dz3 button').forEach(x=>x.classList.toggle('on',x.dataset.d===KS.dzien));kRenderPrzes();kRenderRozklad();}
  else if(b.dataset.p){KS.pieszo=Math.min(8,Math.max(1,KS.pieszo+ +b.dataset.p));Q('#k-pieszo').textContent=KS.pieszo+' min';kRenderPrzes();}
  else if(b.dataset.kk){KS.kier=+b.dataset.kk;KS.przyst=0;kRenderRozklad();}
  else if(b.dataset.z==='k'){kMapa.zoom(+b.dataset.k);}
  else if(b.dataset.l!==undefined&&b.parentNode.id==='k-lchips'){KS.mapaL=b.dataset.l||null;chips('#k-lchips',1);kMapa.draw();}
  else if(b.dataset.l!==undefined&&b.parentNode.id==='k-linie'){KS.linia=b.dataset.l;KS.kier=0;KS.przyst=0;chips('#k-linie',0);kRenderRozklad();}
  else if(b.id==='k-radni')kDoRadnych();});
 Q('#k-przyst').onchange=e=>{KS.przyst=+e.target.value;kRenderRozklad();};
 if(!maPociagi()){const pc=Q('#k-prz').closest('.card');pc.innerHTML='<b>Przesiadki na pociąg: wkrótce</b><p class="mut" style="margin:4px 0 0">Rozkłady autobusów L są już w aplikacji. Gdy dodamy godziny pociągów Kolei Mazowieckich, pokażemy tu, ile minut według rozkładu jest na przesiadkę na stacjach Kobyłka i Kobyłka Ossów.</p>';}
 kNajblizsze();if(maPociagi()){kRenderPrzes();kRenderPoc();}kRenderRozklad();
 const tick=()=>{const z=Q('#k-zegar');if(z)z.textContent=new Date().toLocaleTimeString('pl-PL',{hour:'2-digit',minute:'2-digit'});};tick();setInterval(()=>{if(Q('#v-kom.on')){tick();kNajblizsze();}},30000);
}
function kDoRadnych(){
 const R=kPrzesiadki();if(!R.length){say('Brak danych do wysłania.');return;}const na=KS.tryb==='na';
 const zle=R.filter(r=>r.b==null||r.czek>12||r.czek<=KS.pieszo+1);
 const t='Szanowni Państwo Radni,\n\nproszę o zajęcie się skomunikowaniem autobusów linii L z pociągami na stacji '+KS.stacja+'.\n\nWedług obowiązujących rozkładów ('+DN[KS.dzien].toLowerCase()+', kierunek: '+(na?'autobusem na pociąg do Warszawy':'z pociągu z Warszawy na autobus')+') na '+R.length+' kursów '+zle.length+' nie daje wygodnej przesiadki (czekanie dłuższe niż 12 minut albo krótsze niż '+(KS.pieszo+2)+' minuty potrzebne na dojście).\n\nPrzykłady:\n'+zle.slice(0,8).map(r=>'- '+(na?'autobus '+r.linia+' przyjeżdża ':'pociąg przyjeżdża ')+m2t(r.a)+', '+(r.b==null?'brak dalszego połączenia':(na?'pociąg odjeżdża ':'autobus '+r.linia+' odjeżdża ')+m2t(r.b)+' (czekanie '+r.czek+' min)')).join('\n')+'\n\nProszę o wystąpienie do organizatora przewozów o korektę rozkładu linii L, tak aby czas na przesiadkę wynosił 5–10 minut.\n\nZ poważaniem\n(imię i nazwisko, ulica)\n\n(Zestawienie z Aplikacji mieszkańca Kobyłki, na podstawie oficjalnych rozkładów)';
 doRadnych('Przesiadki autobus–pociąg, stacja '+KS.stacja,t);
}
function radniOkregu(){const D=MK.DANE,R=(D&&D.radni&&D.radni.length)?D.radni:(MK.RADNI||[]);const o=MK.okregUlicy?MK.okregUlicy():null;return{o,moi:o?R.filter(r=>r.okreg===o):[],wsz:R};}
function doRadnych(temat,tresc,adresy){const r=radniOkregu(),do_=(adresy&&adresy.length?adresy:(r.moi.length?r.moi:r.wsz).map(x=>x.email)).filter(Boolean);
 if(!do_.length){say('Brak adresów radnych.');return;}
 const body=MK.mailBody?MK.mailBody(tresc):tresc;location.href='mailto:'+do_.join(',')+'?subject='+encodeURIComponent(temat)+'&body='+encodeURIComponent(body);}

/* =====================================================================
   BUDŻET MIASTA
   ===================================================================== */
const DZ={'801':['🏫','Szkoły i przedszkola'],'854':['🎒','Świetlice i opieka nad uczniami'],'600':['🛣️','Drogi i transport'],'900':['♻️','Śmieci, zieleń, oświetlenie, kanalizacja'],'750':['🏛️','Urząd i administracja'],'852':['🤝','Pomoc społeczna'],'855':['👪','Wsparcie rodzin'],'853':['🧩','Pozostała polityka społeczna'],'921':['🎭','Kultura i biblioteka'],'926':['⚽','Sport i rekreacja'],'757':['🏦','Odsetki od długu'],'754':['🚒','Bezpieczeństwo i straż'],'700':['🏘️','Mieszkania i nieruchomości miasta'],'851':['🩺','Zdrowie i profilaktyka'],'710':['📐','Planowanie przestrzenne'],'758':['🧰','Rezerwy'],'010':['🚰','Wodociągi i rolnictwo'],'400':['🚰','Woda i energia'],'630':['🧭','Turystyka'],'751':['🗳️','Wybory'],'756':['🧾','Pobór podatków']};
const BKOL=['#0E7C6B','#14315a','#C2410C','#B45309','#9333EA','#2563EB','#BE185D','#0891B2','#4D7C0F','#6B7280'];
let B=null;
function bGrupy(lista,ile){const s=lista.slice().sort((a,b)=>b.kwota-a.kwota),top=s.slice(0,ile),r=s.slice(ile).reduce((a,x)=>a+x.kwota,0);if(r>0)top.push({nazwa:'Pozostałe',kwota:r,reszta:s.slice(ile)});return top;}
async function budzetInit(){
 const box=Q('#v-budzet');if(!B){box.innerHTML='<h2>Budżet miasta</h2><p class="mut">Wczytuję…</p>';B=await load('budzet');}
 if(!B||!B.wydatki){box.innerHTML='<h2>Budżet miasta</h2><div class="card mut">Nie udało się wczytać danych budżetu.</div>';return;}
 if(box.dataset.ok)return;box.dataset.ok=1;
 const lm=B.mieszkancy&&B.mieszkancy.liczba,W=B.wydatki,D=B.dochody,wyn=D-W;
 const wd=bGrupy((B.wydatki_dzialy||[]).map(x=>({nazwa:(DZ[x.dzial]||[])[1]||x.nazwa,ik:(DZ[x.dzial]||['📁'])[0],kwota:x.kwota,dzial:x.dzial})),8);
 const dz=bGrupy((B.dochody_zrodla||[]).map(x=>({nazwa:x.nazwa,kwota:x.kwota})),9);
 const sumW=wd.reduce((a,x)=>a+x.kwota,0)||1,sumD=dz.reduce((a,x)=>a+x.kwota,0)||1;
 const stack=(L,sum)=>`<div class="bstack">${L.map((x,i)=>`<i style="flex:${x.kwota};background:${BKOL[i%BKOL.length]}" title="${E(x.nazwa)}"></i>`).join('')}</div>`;
 const row=(x,i,sum,per)=>{const na100=x.kwota/sum*100;return `<div class="brow"><span class="bik" style="background:${BKOL[i%BKOL.length]}">${x.ik||''}</span><div class="grow"><b>${E(x.nazwa)}</b><div class="bbar"><i style="width:${Math.max(2,na100)}%;background:${BKOL[i%BKOL.length]}"></i></div><span class="mut">${mln(x.kwota)}${per&&lm?' · '+zl(x.kwota/lm)+' zł na mieszkańca':''}</span>${x.reszta?`<span class="mut">w tym: ${x.reszta.slice(0,5).map(r=>E(r.nazwa)).join(', ')}</span>`:''}</div><div class="bz"><b>${na100<10?na100.toFixed(1).replace('.',','):Math.round(na100)} zł</b><span>ze 100 zł</span></div></div>`;};
 const inw=(B.inwestycje||[]).slice().sort((a,b)=>b.kwota-a.kwota),maxI=inw[0]?inw[0].kwota:1;
 const P=B.procedura||{};
 box.innerHTML=`<h2>Budżet miasta ${E(B.rok)}</h2>
 <p class="mut">Skąd Kobyłka ma pieniądze i na co je wydaje. Liczby z uchwały budżetowej${B.uchwala&&B.uchwala.url?` (<a href="${E(B.uchwala.url)}" target="_blank" rel="noopener">źródło w BIP</a>)`:''}.</p>
 <div class="bhero"><div><small>Miasto wyda w ${E(B.rok)} r.</small><b>${mln(W)}</b>${lm?`<span>to około ${zl(Math.round(W/lm/10)*10)} zł na jednego mieszkańca, czyli ${zl(Math.round(W/lm/12/10)*10)} zł miesięcznie</span>`:''}</div></div>
 <div class="btiles"><div><small>Dochody</small><b>${mln(D)}</b></div><div><small>Wydatki</small><b>${mln(W)}</b></div><div class="${wyn<0?'minus':'plus'}"><small>${wyn<0?'Deficyt':'Nadwyżka'}</small><b>${mln(Math.abs(wyn))}</b></div></div>
 ${wyn<0?`<p class="mut" style="margin:6px 2px 0">Deficyt oznacza, że miasto wydaje więcej, niż zarabia. Różnicę pokrywa z kredytów, obligacji albo oszczędności z poprzednich lat.</p>`:''}
 <h3>Z każdych 100 zł miasto wydaje</h3>
 <div class="card">${stack(wd,sumW)}${wd.map((x,i)=>row(x,i,sumW,1)).join('')}${B.wydatki_dzialy_opis?`<p class="mut" style="margin:10px 0 0">${E(B.wydatki_dzialy_opis)}</p>`:''}</div>
 ${B.wydatki_majatkowe?`<h3>Utrzymanie czy inwestycje</h3><div class="card"><div class="bstack big"><i style="flex:${B.wydatki_biezace||W-B.wydatki_majatkowe};background:#14315a"></i><i style="flex:${B.wydatki_majatkowe};background:#0E7C6B"></i></div>
  <div class="b2"><div><i style="background:#14315a"></i><b>${Math.round((B.wydatki_biezace||W-B.wydatki_majatkowe)/W*100)}% bieżące</b><span class="mut">${mln(B.wydatki_biezace||W-B.wydatki_majatkowe)}: pensje nauczycieli, utrzymanie szkół, dróg i urzędu, odbiór śmieci</span></div><div><i style="background:#0E7C6B"></i><b>${Math.round(B.wydatki_majatkowe/W*100)}% inwestycje</b><span class="mut">${mln(B.wydatki_majatkowe)}: nowe drogi, budynki, kanalizacja</span></div></div></div>`:''}
 ${inw.length?`<h3>Największe inwestycje</h3><div class="card">${inw.slice(0,12).map(x=>`<div class="binw"><div class="grow"><b>${E(x.nazwa)}</b>${x.miejsce?`<span class="mut">${E(x.miejsce)}</span>`:''}<div class="bbar"><i style="width:${Math.max(3,x.kwota/maxI*100)}%"></i></div></div><b class="bk">${mln(x.kwota)}</b></div>`).join('')}</div>`:''}
 <h3>Skąd miasto ma pieniądze</h3>
 <div class="card">${stack(dz,sumD)}${dz.map((x,i)=>row(x,i,sumD,0)).join('')}<p class="mut" style="margin:10px 0 0">Udział w PIT zależy od tego, ilu mieszkańców rozlicza podatek w Kobyłce. <a href="#lokalnie" data-go="lokalnie">Zobacz, ile daje Twój PIT</a>.</p></div>
 ${B.po_zmianach||B.wykonanie_2025?`<h3>Plan a rzeczywistość</h3><div class="card">${B.wykonanie_2025?`<div class="binw"><div class="grow"><b>Wykonanie 2025</b><span class="mut">tyle miasto faktycznie wydało rok wcześniej, w tym ${mln(B.wykonanie_2025.wydatki_majatkowe)} na inwestycje</span></div><b class="bk">${mln(B.wykonanie_2025.wydatki)}</b></div>`:''}<div class="binw"><div class="grow"><b>Plan na ${E(B.rok)} z uchwały budżetowej</b><span class="mut">${B.uchwala&&B.uchwala.data?E(B.uchwala.data):''}</span></div><b class="bk">${mln(W)}</b></div>${B.po_zmianach?`<div class="binw"><div class="grow"><b>Plan po zmianach</b><span class="mut">stan na ${E(B.po_zmianach.data)}; w ciągu roku plan wydatków wzrósł o ${mln(B.po_zmianach.wydatki-W)}${B.po_zmianach.url?` · <a href="${E(B.po_zmianach.url)}" target="_blank" rel="noopener">uchwała</a>`:''}</span></div><b class="bk">${mln(B.po_zmianach.wydatki)}</b></div>`:''}</div>`:''}
 ${B.dlug_koniec_roku?`<h3>Dług miasta</h3><div class="card"><div class="kbig col"><b>${mln(B.dlug_koniec_roku)}</b><span>planowany dług na koniec ${E(B.rok)} r., czyli ${Math.round(B.dlug_koniec_roku/D*100)}% rocznych dochodów${lm?' i około '+zl(Math.round(B.dlug_koniec_roku/lm/10)*10)+' zł na mieszkańca':''}${B.dlug_opis?' ('+E(B.dlug_opis)+')':''}</span></div></div>`:''}
 <h3>Jak powstaje budżet</h3>
 <div class="card"><div class="btl">
  <div><i>1</i><b>Potrzeby: przez cały rok</b><span class="mut">Wydziały Urzędu, jednostki miasta i radni zgłaszają burmistrzowi potrzeby do projektu.</span></div>
  <div><i>2</i><b>Projekt burmistrza: do 15 listopada</b><span class="mut">Burmistrz przekazuje projekt Radzie i Regionalnej Izbie Obrachunkowej.</span></div>
  <div><i>3</i><b>Komisje Rady: do 30 listopada</b><span class="mut">${P.termin_wnioskow?E(P.termin_wnioskow)+' ':'Radni opiniują projekt i zgłaszają wnioski o zmiany. '}To moment, w którym radny może upomnieć się o Twoją sprawę.</span></div>
  <div><i>4</i><b>Uchwalenie: do 31 stycznia</b><span class="mut">Rada głosuje nad budżetem. W ciągu roku budżet jest zmieniany na sesjach.</span></div></div>
  ${P.uchwala?`<p class="mut" style="margin:10px 0 0">Tryb prac: ${E(P.uchwala)}${P.url?` (<a href="${E(P.url)}" target="_blank" rel="noopener">treść</a>)`:''}.</p>`:''}
  <button class="btn gold wide" data-go="wniosekb" style="margin-top:12px">Zgłoś potrzebę do budżetu przez radnego</button>
  <button class="btn line wide" data-go="bo" style="margin-top:8px">Budżet obywatelski</button></div>
 <p class="mut">Stan danych: ${E(B.stan||'')}. Nazwy działów uprościliśmy; pełna klasyfikacja jest w uchwale.${B.mieszkancy&&B.mieszkancy.opis?' Przeliczenia na mieszkańca: '+E(B.mieszkancy.opis)+'.':''}</p>`;
}

/* =====================================================================
   WNIOSEK DO BUDŻETU PRZEZ RADNEGO
   ===================================================================== */
const WB=[
 {id:'chodnik',ik:'🚶',n:'Chodnik',t:'Budowa chodnika przy ul. {ulica}',o:'Na odcinku od … do … nie ma chodnika. Piesi, w tym dzieci idące do szkoły, chodzą jezdnią albo błotnistym poboczem.',k:'mieszkańcy ulicy i okolicznych osiedli, dzieci w drodze do szkoły, osoby z wózkami'},
 {id:'oswietlenie',ik:'💡',n:'Oświetlenie',t:'Budowa oświetlenia ulicznego na ul. {ulica}',o:'Ulica na odcinku od … do … nie jest oświetlona. Po zmroku jest tu niebezpiecznie dla pieszych i rowerzystów.',k:'mieszkańcy ulicy, osoby wracające wieczorem z pociągu i autobusu'},
 {id:'przejscie',ik:'🚸',n:'Bezpieczne przejście',t:'Bezpieczne przejście dla pieszych na ul. {ulica}',o:'Przy … (szkoła, przystanek, sklep) kierowcy jeżdżą szybko, a przejście jest słabo widoczne. Potrzebne jest doświetlenie, wyniesienie przejścia albo próg zwalniający.',k:'dzieci, seniorzy, wszyscy piesi w tym miejscu'},
 {id:'droga',ik:'🛣️',n:'Nawierzchnia ulicy',t:'Projekt i budowa nawierzchni ul. {ulica}',o:'Ulica jest gruntowa. Po deszczu stoi woda i błoto, latem kurz. Mieszkańcy od lat czekają na utwardzenie.',k:'mieszkańcy ulicy, służby (karetka, śmieciarka), dojeżdżający'},
 {id:'odwodnienie',ik:'🌧️',n:'Odwodnienie',t:'Odwodnienie ul. {ulica}',o:'Po każdym większym deszczu woda zalewa jezdnię i posesje na wysokości numerów …. Problem powtarza się od ….',k:'mieszkańcy zalewanych posesji, piesi i kierowcy'},
 {id:'plac',ik:'🛝',n:'Plac zabaw, sport',t:'Plac zabaw lub strefa sportu przy ul. {ulica}',o:'W tej części miasta nie ma ogólnodostępnego miejsca dla dzieci i młodzieży. Najbliższy plac zabaw jest … minut pieszo.',k:'rodziny z dziećmi, młodzież, seniorzy (siłownia plenerowa)'},
 {id:'zielen',ik:'🌳',n:'Zieleń, ławki',t:'Nasadzenia drzew i ławki przy ul. {ulica}',o:'Na tym odcinku nie ma cienia ani miejsca do odpoczynku. Drzewa i ławki poprawią komfort pieszych i obniżą temperaturę latem.',k:'piesi, seniorzy, mieszkańcy okolicy'},
 {id:'rower',ik:'🚲',n:'Droga rowerowa',t:'Droga rowerowa wzdłuż ul. {ulica}',o:'Rowerzyści jadą ruchliwą jezdnią razem z samochodami. Brakuje bezpiecznego połączenia z … (stacja, szkoła, centrum).',k:'rowerzyści, uczniowie, osoby dojeżdżające rowerem do pociągu'},
 {id:'inne',ik:'✚',n:'Inna potrzeba',t:'',o:'',k:''}];
let WS={krok:1,kat:null,ulica:'',tytul:'',opis:'',kto:'',skala:'ulica',komu:null};
function wbTermin(){const P=(B&&B.procedura)||{};if(!P.termin_dzien)return null;const n=new Date(),t=new Date(n.getFullYear()+'-'+P.termin_dzien+'T23:59:59'),dni=Math.ceil((t-n)/864e5);return dni>=0&&dni<=60?dni:null;}
function wbRok(){const d=new Date();return d.getMonth()>=1?d.getFullYear()+1:d.getFullYear();}
function wbTekst(){const r=wbRok(),d=LS('dane')||{};
 return 'Szanowni Państwo Radni,\n\nzwracam się z prośbą o zgłoszenie do budżetu Miasta Kobyłka na rok '+r+' następującego zadania:\n\n'+WS.tytul.toUpperCase()+'\n\nMiejsce: '+(WS.ulica?'ul. '+WS.ulica:'(do uzupełnienia)')+'\n\nOpis potrzeby:\n'+WS.opis+'\n\nKto skorzysta: '+(WS.kto||'mieszkańcy okolicy')+'\n\nProszę o:\n1. zgłoszenie zadania jako wniosku do projektu budżetu albo, jeżeli projekt jest już w Radzie, jako wniosku na posiedzeniu komisji,\n2. wystąpienie do Burmistrza o oszacowanie kosztu zadania,\n3. informację zwrotną, co stało się z wnioskiem.\n\nZ poważaniem\n'+(d.imie||'(imię i nazwisko)')+'\n'+(d.adres||(WS.ulica?'ul. '+WS.ulica+', Kobyłka':'(adres)'))+(d.kontakt?'\n'+d.kontakt:'')+'\n\n(Wniosek przygotowany w Aplikacji mieszkańca Kobyłki)';}
function wbRender(){
 const box=Q('#v-wniosekb'),r=radniOkregu(),ul=WS.ulica||(MK.store&&typeof MK.store('ulica')==='string'?MK.store('ulica'):'')||'';if(!WS.ulica)WS.ulica=ul;
 const P=(B&&B.procedura)||{};
 const kroki=`<div class="wkr"><span class="${WS.krok>=1?'on':''}">1. Co</span><span class="${WS.krok>=2?'on':''}">2. Opis</span><span class="${WS.krok>=3?'on':''}">3. Do kogo</span></div>`;
 let h=`<h2>Wniosek do budżetu</h2><p class="mut">Chodnik, latarnie albo plac zabaw wymagają pieniędzy w budżecie miasta. Mieszkaniec nie może sam wpisać zadania do budżetu, ale może to zrobić radny. Przygotuj prośbę w 3 krokach.</p>${kroki}`;
 if(WS.krok===1)h+=`<div class="card"><b>Czego potrzeba?</b><div class="wkat">${WB.map(k=>`<button data-kat="${k.id}" class="${WS.kat===k.id?'on':''}"><i>${k.ik}</i>${k.n}</button>`).join('')}</div>
   <label for="wb-ul">Na jakiej ulicy?</label><input id="wb-ul" list="wb-ull" value="${E(WS.ulica)}" placeholder="np. Załuskiego" autocomplete="off"><datalist id="wb-ull">${(MK.ulice?MK.ulice():[]).filter(x=>!x.sas).map(x=>`<option value="${E(x.u)}">`).join('')}</datalist>
   <button class="btn gold wide" id="wb-n1" style="margin-top:14px">Dalej</button></div>
   ${wbTermin()!=null?`<div class="card" style="background:var(--warnbg);box-shadow:none"><b>${wbTermin()===0?'Dziś mija termin':'Zostało '+wbTermin()+' '+(wbTermin()===1?'dzień':'dni')}</b><p class="mut" style="color:var(--ink);margin:4px 0 0">Do 30 listopada komisje Rady składają wnioski do projektu budżetu na ${wbRok()} r. Im wcześniej radny dostanie Twoją prośbę, tym większa szansa, że zdąży.</p></div>`:''}<div class="card"><b>Kiedy to ma sens?</b><p class="mut" style="margin:4px 0 0">Burmistrz składa projekt budżetu do 15 listopada, komisje Rady zgłaszają do niego wnioski do 30 listopada, a Rada uchwala budżet zwykle w grudniu lub styczniu. Wniosek złożony później radny może zgłosić przy zmianach budżetu w ciągu roku albo do budżetu na kolejny rok.</p></div>`;
 else if(WS.krok===2)h+=`<div class="card"><label for="wb-t">Nazwa zadania</label><input id="wb-t" value="${E(WS.tytul)}" maxlength="140">
   <label for="wb-o">Opisz potrzebę</label><textarea id="wb-o" style="min-height:130px" placeholder="Co jest nie tak, w którym miejscu, od kiedy.">${E(WS.opis)}</textarea>
   <p class="mut" style="margin:6px 0 0">Uzupełnij wykropkowane miejsca. Konkret działa lepiej niż ogólniki: odcinek „od–do”, liczba domów, szkoła w pobliżu, zdarzenia.</p>
   <label for="wb-k">Kto skorzysta?</label><input id="wb-k" value="${E(WS.kto)}">
   <div class="wwsk"><b>Co wzmacnia wniosek</b><span>✓ podpisy sąsiadów (nawet 10–20 osób)</span><span>✓ zdjęcia miejsca</span><span>✓ informacja, że teren należy do miasta</span><span>✓ wcześniejsze pisma w tej sprawie</span><span>✓ pomysł, jak zadanie podzielić na etapy: radny musi wskazać źródło pieniędzy</span></div>
   <div class="flex-wrap" style="margin-top:14px"><button class="btn line" id="wb-b2">Wstecz</button><button class="btn gold" id="wb-n2" style="flex:1">Dalej</button></div></div>`;
 else{const moi=r.moi,lista=moi.length?moi:r.wsz;if(!WS.komu)WS.komu=lista.map(x=>x.id);
  h+=`<div class="card"><b>${moi.length?'Radni z Twojego okręgu nr '+r.o:'Wybierz radnych'}</b>${!moi.length?`<p class="mut" style="margin:4px 0 0">Ustaw swoją ulicę w zakładce Odpady, a pokażę radnych z Twojego okręgu.</p>`:''}
   <div class="wrad">${lista.map(x=>`<label><input type="checkbox" data-rad="${E(x.id)}" ${WS.komu.indexOf(x.id)>=0?'checked':''}><span><b>${E(x.imie)}</b>${x.funkcja&&x.funkcja!=='BRAK'?`<small>${E(x.funkcja)}</small>`:''}</span></label>`).join('')}</div>
   ${moi.length?`<button class="btn sm line" id="wb-wsz" style="margin-top:8px">Pokaż wszystkich radnych</button>`:''}</div>
   <div class="card"><b>Podgląd wiadomości</b><pre class="wpre" id="wb-pre">${E(wbTekst())}</pre>
   <button class="btn gold wide" id="wb-mail">Wyślij e-mailem do radnych</button><button class="btn line wide" id="wb-kop" style="margin-top:8px">Skopiuj treść</button>
   <button class="btn line wide" id="wb-b3" style="margin-top:8px">Wstecz</button>
   <p class="mut" style="margin:10px 0 0">Wiadomość wysyłasz ze swojej skrzynki, na służbowe adresy radnych z BIP. Zapisz wniosek, a za 3 tygodnie zapytaj radnego, co z nim zrobił.</p></div>`;}
 const zap=LS('wnioskib')||[];
 if(zap.length)h+=`<h3>Moje wnioski do budżetu</h3><div class="card">${zap.map(z=>`<div class="step"><b>${E(z.t)}</b><span class="mut">wysłany ${E(z.d)} · ${z.n} ${z.n===1?'radny':'radnych'}</span></div>`).join('')}</div>`;
 box.innerHTML=h;
 const g=id=>Q('#'+id);
 if(WS.krok===1){QA('#v-wniosekb [data-kat]').forEach(b=>b.onclick=()=>{WS.kat=b.dataset.kat;QA('#v-wniosekb [data-kat]').forEach(x=>x.classList.toggle('on',x===b));});
  g('wb-n1').onclick=()=>{WS.ulica=g('wb-ul').value.trim();if(!WS.kat){say('Wybierz, czego potrzeba.');return;}if(!WS.ulica&&WS.kat!=='inne'){say('Wpisz ulicę.');g('wb-ul').focus();return;}
   const k=WB.find(x=>x.id===WS.kat);if(WS._kat!==WS.kat+WS.ulica){WS.tytul=k.t.replace('{ulica}',WS.ulica);WS.opis=k.o;WS.kto=k.k;WS._kat=WS.kat+WS.ulica;}WS.krok=2;wbRender();window.scrollTo(0,0);};}
 else if(WS.krok===2){const zb=()=>{WS.tytul=g('wb-t').value.trim();WS.opis=g('wb-o').value.trim();WS.kto=g('wb-k').value.trim();};
  g('wb-b2').onclick=()=>{zb();WS.krok=1;wbRender();};g('wb-n2').onclick=()=>{zb();if(WS.tytul.length<8){say('Nazwij zadanie.');return;}if(WS.opis.length<30){say('Opisz potrzebę w 2–3 zdaniach.');return;}if(/…/.test(WS.opis)){say('Uzupełnij wykropkowane miejsca w opisie.');g('wb-o').focus();return;}WS.krok=3;wbRender();window.scrollTo(0,0);};}
 else{QA('#v-wniosekb [data-rad]').forEach(c=>c.onchange=()=>{WS.komu=QA('#v-wniosekb [data-rad]:checked').map(x=>x.dataset.rad);});
  if(g('wb-wsz'))g('wb-wsz').onclick=()=>{const all=radniOkregu().wsz;Q('#v-wniosekb .wrad').innerHTML=all.map(x=>`<label><input type="checkbox" data-rad="${E(x.id)}" ${WS.komu.indexOf(x.id)>=0?'checked':''}><span><b>${E(x.imie)}</b><small>okręg ${E(x.okreg)}</small></span></label>`).join('');g('wb-wsz').remove();QA('#v-wniosekb [data-rad]').forEach(c=>c.onchange=()=>{WS.komu=QA('#v-wniosekb [data-rad]:checked').map(x=>x.dataset.rad);});};
  g('wb-b3').onclick=()=>{WS.krok=2;wbRender();};
  g('wb-kop').onclick=()=>{try{navigator.clipboard.writeText(wbTekst());say('Skopiowano treść wniosku.');}catch(e){say('Nie udało się skopiować.');}};
  g('wb-mail').onclick=()=>{const all=radniOkregu().wsz,adr=all.filter(x=>WS.komu.indexOf(x.id)>=0).map(x=>x.email).filter(Boolean);if(!adr.length){say('Zaznacz co najmniej jednego radnego.');return;}
   const z=LS('wnioskib')||[];z.unshift({t:WS.tytul,d:new Date().toLocaleDateString('pl-PL'),n:adr.length});LS('wnioskib',z.slice(0,30));doRadnych('Wniosek do budżetu '+wbRok()+': '+WS.tytul,wbTekst(),adr);};}
}
async function wniosekbInit(){if(!B)B=await load('budzet');wbRender();}

/* =====================================================================
   BUDŻET OBYWATELSKI
   ===================================================================== */
const BOP=[
 {typ:'twardy',ik:'🛝',t:'Strefa zabaw i odpoczynku przy ul. …',o:'Niewielki plac zabaw z ławkami i zielenią na miejskiej działce przy ul. …. W promieniu 10 minut pieszo nie ma dziś takiego miejsca.',k:'rodziny z małymi dziećmi i seniorzy z okolicznych ulic',e:'urządzenia zabawowe z certyfikatem, bezpieczna nawierzchnia, 3 ławki, kosz, nasadzenia'},
 {typ:'twardy',ik:'🌳',t:'Zielona ulica: drzewa i ławki na ul. …',o:'Posadzenie drzew i ustawienie ławek wzdłuż ul. … na odcinku od … do …. Latem nie ma tu cienia, a piesi nie mają gdzie odpocząć w drodze na stację.',k:'piesi, seniorzy, mieszkańcy ulicy',e:'drzewa o obwodzie min. 14 cm z pielęgnacją przez 3 lata, ławki z oparciem, kosze'},
 {typ:'twardy',ik:'🚲',t:'Wiaty i stojaki rowerowe przy stacji i szkołach',o:'Zadaszone stojaki rowerowe przy … (stacja, szkoła, urząd). Obecnie rowery przypinane są do płotów i znaków.',k:'uczniowie i osoby dojeżdżające rowerem do pociągu',e:'stojaki typu U, zadaszenie, oświetlenie, stacja naprawy rowerów'},
 {typ:'twardy',ik:'💪',t:'Siłownia plenerowa i street workout przy ul. …',o:'Ogólnodostępne urządzenia do ćwiczeń dla młodzieży i dorosłych na terenie miejskim przy ul. ….',k:'młodzież, dorośli, seniorzy',e:'zestaw drążków, 5–6 urządzeń, nawierzchnia, tablica z instrukcją'},
 {typ:'twardy',ik:'🚸',t:'Bezpieczna droga do szkoły nr …',o:'Doświetlenie przejść dla pieszych i aktywne oznakowanie przy szkole …. Dzieci przechodzą tu przez ruchliwą ulicę.',k:'uczniowie i rodzice, wszyscy piesi',e:'doświetlenie 2 przejść, znaki aktywne, wymalowanie'},
 {typ:'miękki',ik:'📚',t:'Kino letnie, warsztaty, zajęcia dla seniorów',o:'Cykl bezpłatnych wydarzeń dla mieszkańców: … spotkań w okresie …, w miejscu ….',k:'mieszkańcy w każdym wieku',e:'wynajem sprzętu, prowadzący, promocja, ubezpieczenie'},
 {typ:'twardy',ik:'🐝',t:'Łąka kwietna i domki dla owadów przy ul. …',o:'Zamiana trawnika przy ul. … w łąkę kwietną z tablicą edukacyjną. Tańsza w utrzymaniu niż koszony trawnik i lepiej zatrzymuje wodę.',k:'mieszkańcy okolicy, przedszkola i szkoły (edukacja)',e:'przygotowanie gruntu, wysiew, tablica, pielęgnacja przez 2 lata'}];
let BO=null,boMapa=null,BS={f:'wszystkie',wyb:null,krok:0,p:{t:'',o:'',k:'',e:'',m:'',typ:'twardy'}};
const boKol=s=>/^zrealiz/i.test(s||'')?'#0E7C6B':/wybrany/i.test(s||'')?'#2563EB':/w realiz|trakcie/i.test(s||'')?'#B45309':/niezreal|odrzuc|anul/i.test(s||'')?'#B3261E':'#6B7280';
function boTekst(){const p=BS.p;return 'PROJEKT DO BUDŻETU OBYWATELSKIEGO\n\nTytuł: '+p.t+'\n\nRodzaj: projekt '+p.typ+(p.typ==='twardy'?' (do 150 000 zł)':' (do 20 000 zł)')+'\n\nLokalizacja: '+(p.m||'(do uzupełnienia)')+'\n\nOpis projektu:\n'+p.o+'\n\nKto skorzysta:\n'+p.k+'\n\nZakres (co trzeba zrobić lub kupić):\n'+p.e+'\n\nProjekt jest ogólnodostępny i bezpłatny dla mieszkańców.';}
function boLista(){const L=(BO.projekty||[]).filter(p=>BS.f==='wszystkie'||String(p.edycja)===BS.f);
 Q('#bo-lista').innerHTML=L.length?L.map((p,i)=>`<div class="bop${BS.wyb===p?' on':''}" data-bi="${(BO.projekty||[]).indexOf(p)}"><i style="background:${boKol(p.status)}"></i><div class="grow"><b>${E(p.tytul)}</b><span class="mut">${E(p.edycja)}${p.typ?' · '+E(p.typ):''}${p.miejsce?' · '+E(p.miejsce):''}${p.kwota?' · '+mln(p.kwota):''}</span>${p.opis?`<span class="mut">${E(p.opis)}</span>`:''}<span class="bost" style="color:${boKol(p.status)}">${E(p.status||'brak informacji')}${p.url?` · <a href="${E(p.url)}" target="_blank" rel="noopener">źródło</a>`:''}</span></div></div>`).join(''):'<p class="empty">Brak projektów.</p>';}
function boRysuj(c,X,Y,v,dk){(BO.projekty||[]).forEach(p=>{if(!p._p)return;if(BS.f!=='wszystkie'&&String(p.edycja)!==BS.f)return;const x=X(p._p[0]),y=Y(p._p[1]),on=BS.wyb===p;
  c.beginPath();c.arc(x,y,on?11:8,0,7);c.fillStyle=boKol(p.status);c.fill();c.lineWidth=on?4:2.5;c.strokeStyle=dk?'#0d131d':'#fff';c.stroke();
  if(on){c.font='700 12px system-ui';c.lineWidth=3;c.strokeStyle=dk?'#0d131d':'#fff';c.fillStyle=dk?'#e5e9f0':'#14213d';const t=p.tytul.length>34?p.tytul.slice(0,33)+'…':p.tytul;c.strokeText(t,x+14,y+4);c.fillText(t,x+14,y+4);}});}
function boKreator(){
 const box=Q('#bo-kreator'),p=BS.p,Z=(BO&&BO.zasady)||{};
 if(BS.krok===0){box.innerHTML=`<p class="mut" style="margin:0 0 10px">Wybierz przykład i dopasuj go do swojej ulicy albo zacznij od pustego projektu.</p><div class="boex">${BOP.map((x,i)=>`<button data-ex="${i}"><i>${x.ik}</i><b>${E(x.t)}</b><span>${E(x.o.slice(0,90))}…</span><em>${x.typ}</em></button>`).join('')}<button data-ex="-1"><i>✚</i><b>Własny pomysł</b><span>Zacznij od pustego formularza.</span></button></div>`;
  QA('#bo-kreator [data-ex]').forEach(b=>b.onclick=()=>{const x=BOP[+b.dataset.ex];BS.p=x?{t:x.t,o:x.o,k:x.k,e:x.e,m:'',typ:x.typ}:{t:'',o:'',k:'',e:'',m:'',typ:'twardy'};BS.krok=1;boKreator();Q('#bo-kreator').scrollIntoView({behavior:'smooth',block:'start'});});return;}
 if(BS.krok===1){box.innerHTML=`<label>Rodzaj projektu</label><div class="seg k2" id="bo-typ"><button data-ty="twardy" class="${p.typ==='twardy'?'on':''}">Twardy: inwestycja, do 150 000 zł</button><button data-ty="miękki" class="${p.typ==='miękki'?'on':''}">Miękki: wydarzenia, do 20 000 zł</button></div><label for="bo-t">Tytuł projektu</label><input id="bo-t" value="${E(p.t)}" maxlength="120">
  <label for="bo-m">Lokalizacja (ulica, działka, obiekt)</label><input id="bo-m" value="${E(p.m)}" placeholder="np. skwer przy ul. Załuskiego">
  <label for="bo-o">Opis: co i dlaczego</label><textarea id="bo-o" style="min-height:110px">${E(p.o)}</textarea>
  <label for="bo-k">Kto skorzysta</label><input id="bo-k" value="${E(p.k)}">
  <label for="bo-e">Zakres: co trzeba zrobić lub kupić</label><textarea id="bo-e" style="min-height:70px">${E(p.e)}</textarea>
  <div class="flex-wrap" style="margin-top:14px"><button class="btn line" id="bo-b">Przykłady</button><button class="btn gold" id="bo-n" style="flex:1">Sprawdź projekt</button></div>`;
  const zb=()=>{p.t=Q('#bo-t').value.trim();p.m=Q('#bo-m').value.trim();p.o=Q('#bo-o').value.trim();p.k=Q('#bo-k').value.trim();p.e=Q('#bo-e').value.trim();};
  QA('#bo-typ button').forEach(b=>b.onclick=()=>{p.typ=b.dataset.ty;QA('#bo-typ button').forEach(x=>x.classList.toggle('on',x===b));});Q('#bo-b').onclick=()=>{BS.krok=0;boKreator();};Q('#bo-n').onclick=()=>{zb();if(p.t.length<8||p.o.length<40){say('Uzupełnij tytuł i opis.');return;}BS.krok=2;boKreator();};return;}
 const test=[[!/…/.test(p.t+p.o+p.m),/…/.test(p.t+p.o+p.m)?'Uzupełnij wykropkowane miejsca w tytule i opisie':'Wszystkie pola uzupełnione'],[p.m.length>3,'Podana lokalizacja'],[p.o.length>=120,'Opis ma co najmniej 2–3 zdania konkretów'],[p.k.length>5,'Wiadomo, kto skorzysta'],[p.e.length>10,'Wypisany zakres, który urząd może wycenić']];
 box.innerHTML=`<b>Sprawdzenie</b><div class="botest">${test.map(t=>`<span class="${t[0]?'ok':'no'}">${t[0]?'✓':'!'} ${t[1]}</span>`).join('')}</div>
  <div class="wwsk"><b>Zanim złożysz</b><span>✓ sprawdź, czy teren należy do miasta (geoportal albo pytanie do urzędu)</span><span>✓ projekt musi być ogólnodostępny</span>${Z.podpisy?`<span>✓ poparcie: ${E(Z.podpisy)}${Z.lista_poparcia_url?` (<a href="${E(Z.lista_poparcia_url)}" target="_blank" rel="noopener">wzór listy</a>)`:''}</span>`:'<span>✓ zbierz podpisy poparcia sąsiadów</span>'}<span>✓ limit dla projektu ${E(p.typ)}: ${p.typ==='twardy'?'150 000':'20 000'} zł brutto</span><span>✓ jedna osoba może zgłosić najwyżej 2 projekty</span><span>✓ kosztu nie musisz znać co do złotówki, urząd go zweryfikuje</span></div>
  <pre class="wpre">${E(boTekst())}</pre>
  <button class="btn gold wide" id="bo-kop">Skopiuj treść projektu</button>
  ${Z.formularz_url?`<a class="btn line wide" style="margin-top:8px;text-align:center" href="${E(Z.formularz_url)}" target="_blank" rel="noopener">Otwórz formularz w portalu miasta</a><p class="mut" style="margin:6px 0 0">Formularz działa w czasie naboru. Poza naborem zapisz treść i wróć z nią wiosną.</p>`:''}
  <button class="btn line wide" id="bo-rad" style="margin-top:8px">${BO&&BO.dziala?'Poproś radnego o wsparcie projektu':'Wyślij pomysł radnym'}</button>
  <button class="btn line wide" id="bo-b" style="margin-top:8px">Popraw</button>`;
 Q('#bo-b').onclick=()=>{BS.krok=1;boKreator();};
 Q('#bo-kop').onclick=()=>{try{navigator.clipboard.writeText(boTekst());say('Skopiowano. Wklej do formularza Urzędu.');}catch(e){say('Nie udało się skopiować.');}};
 Q('#bo-rad').onclick=()=>doRadnych((BO&&BO.dziala?'Projekt do budżetu obywatelskiego: ':'Pomysł mieszkańca: ')+p.t,'Szanowni Państwo Radni,\n\n'+(BO&&BO.dziala?'przygotowuję projekt do budżetu obywatelskiego i proszę o wsparcie: sprawdzenie, czy teren należy do miasta, oraz pomoc w zebraniu poparcia.':'przesyłam pomysł na niewielkie zadanie dla mieszkańców. Proszę o zgłoszenie go do budżetu miasta albo wskazanie właściwej drogi.')+'\n\n'+boTekst()+'\n\nZ poważaniem\n(imię i nazwisko, ulica)\n\n(Przygotowane w Aplikacji mieszkańca Kobyłki)');
}
async function boInit(){
 const box=Q('#v-bo');if(!BO){box.innerHTML='<h2>Budżet obywatelski</h2><p class="mut">Wczytuję…</p>';BO=await load('bo');}
 if(!BO){box.innerHTML='<h2>Budżet obywatelski</h2><div class="card mut">Nie udało się wczytać danych.</div>';return;}
 if(box.dataset.ok){if(boMapa)boMapa.draw();return;}box.dataset.ok=1;
 const Z=BO.zasady||{},PR=BO.projekty||[],ed=[...new Set(PR.map(p=>String(p.edycja)))].sort().reverse();
 PR.forEach(p=>{if(p.lat&&p.lon)p._p=EN(p.lat,p.lon);});const zm=PR.filter(p=>p._p).length;
 const E0=(BO.edycje||[]).slice().sort((a,b)=>b.rok-a.rok);
 box.innerHTML=`<h2>Budżet obywatelski</h2>
 <div class="card how"><b class="how-t">${BO.dziala?'Mieszkańcy zgłaszają. Mieszkańcy wybierają. Miasto realizuje.':'W Kobyłce nie ma dziś budżetu obywatelskiego'}</b><p class="mut" style="margin:6px 0 0">${E(BO.podsumowanie||'')}</p><button class="btn sm gold" id="bo-doKr" style="margin-top:10px">Mam pomysł na projekt</button></div>
 ${PR.length?`<div class="bhero"><div><small>Od ${E(Math.min(...PR.map(p=>p.edycja)))} roku mieszkańcy wybrali</small><b>${PR.length} projektów</b><span>o łącznej wartości ${mln(PR.reduce((a,p)=>a+(p.kwota||0),0))}, w ${E0.length} edycjach po ${E0[0]&&E0[0].pula?mln(E0[0].pula):''}</span></div></div>`:''}
 ${Z.harmonogram&&Z.harmonogram.length?`<h3>Harmonogram</h3><div class="card"><div class="btl">${Z.harmonogram.map((x,i)=>`<div><i>${i+1}</i><b>${E(x.etap)}</b><span class="mut">${E(x.termin)}</span></div>`).join('')}</div></div>`:''}
 ${BO.dziala||Z.kto?`<h3>Zasady w skrócie</h3><div class="card">${Z.kto?`<div class="step"><b>Kto może zgłosić</b><span class="mut">${E(Z.kto)}</span></div>`:''}${Z.podpisy?`<div class="step"><b>Poparcie</b><span class="mut">${E(Z.podpisy)}</span></div>`:''}${Z.limity?`<div class="step"><b>Limity</b><span class="mut">${E(Z.limity)}</span></div>`:''}${Z.glosowanie?`<div class="step"><b>Głosowanie</b><span class="mut">${E(Z.glosowanie)}</span></div>`:''}${(Z.wymogi||[]).length?`<div class="step"><b>Wymogi</b><span class="mut">${Z.wymogi.map(E).join(' · ')}</span></div>`:''}${Z.uchwala?`<p class="mut" style="margin:8px 0 0">Podstawa: ${E(Z.uchwala)}${Z.url?` (<a href="${E(Z.url)}" target="_blank" rel="noopener">treść</a>)`:''}.</p>`:''}</div>`:''}
 ${PR.length?`<div class="sec"><h3>Mapa realizacji</h3><span class="mut">${PR.length} ${PR.length===1?'projekt':'projektów'}</span></div>
 <div class="card" style="padding:10px">${ed.length>1?`<div class="kchips" id="bo-f"><button data-f="wszystkie" class="on">Wszystkie</button>${ed.map(e=>`<button data-f="${E(e)}">${E(e)}</button>`).join('')}</div>`:''}
  ${zm?`<div class="mwrap"><canvas id="bo-map" class="mcv"></canvas>${zoomBtns('bo')}</div><div class="kleg" style="margin:8px 4px"><span><i style="background:#0E7C6B"></i>zrealizowany</span><span><i style="background:#2563EB"></i>wybrany, czeka na realizację</span><span><i style="background:#6B7280"></i>brak informacji o realizacji</span></div>`:''}
  <div id="bo-lista"></div>${zm<PR.length?`<p class="mut" style="margin:8px 4px 0">${PR.length-zm} ${zm?'projektów nie ma na mapie, bo źródła nie podają dokładnego miejsca.':'projektów: źródła nie podają dokładnych miejsc, dlatego pokazujemy listę.'}</p>`:''}</div>`:''}
 <h3>${BO.dziala?'Kreator projektu':'Masz pomysł? Przygotuj go'}</h3>
 <div class="card" id="bo-kreator"></div>
 ${(BO.inne_formy||[]).length?`<h3>${BO.dziala?'Inne drogi':'Co działa zamiast tego'}</h3><div class="card">${BO.inne_formy.map(f=>`<div class="step"><b>${E(f.nazwa)}</b><span class="mut">${E(f.opis||'')}${f.uchwala?' '+E(f.uchwala)+'.':''}${f.url?` <a href="${E(f.url)}" target="_blank" rel="noopener">Źródło</a>`:''}</span></div>`).join('')}<button class="btn sm gold" data-go="wniosekb" style="margin-top:10px">Wniosek do budżetu przez radnego</button></div>`:''}
 ${(BO.sasiedzi||[]).filter(s=>s.pula).length?`<h3>U sąsiadów</h3><p class="mut">Pule budżetów obywatelskich i podobnych programów w sąsiednich miastach.</p><div class="card">${BO.sasiedzi.filter(s=>s.pula).map(s=>`<div class="binw"><div class="grow"><b>${E(s.gmina)}</b><span class="mut">na ${E(s.rok)} r.${s.url?` · <a href="${E(s.url)}" target="_blank" rel="noopener">źródło</a>`:''}</span></div><b class="bk">${mln(s.pula)}</b></div>`).join('')}</div>`:''}
 <p class="mut">Stan danych: ${E(BO.stan||'')}. Źródło: ${BO.portal?`<a href="${E(BO.portal)}" target="_blank" rel="noopener">portal budżetu obywatelskiego Kobyłki</a>`:'Urząd Miasta'} i raporty o stanie miasta. Portal nie podaje, czy projekt został wykonany, dlatego przy części projektów nie ma informacji o realizacji.</p>`;
 if(PR.length){boLista();
  if(zm){boMapa=Mapa(Q('#bo-map'),boRysuj,(x,y,r)=>{let best=null,bd=Math.max(r,60)**2*4;PR.forEach(p=>{if(!p._p)return;const d=(p._p[0]-x)**2+(p._p[1]-y)**2;if(d<bd){bd=d;best=p;}});BS.wyb=best;boMapa.draw();boLista();const el=Q('#bo-lista .on');if(el)el.scrollIntoView({behavior:'smooth',block:'nearest'});});{const pp=PR.filter(p=>p._p).map(p=>p._p),xs=pp.map(p=>p[0]),ys=pp.map(p=>p[1]);boMapa.cel((Math.min(...xs)+Math.max(...xs))/2,(Math.min(...ys)+Math.max(...ys))/2,Math.max(2500,(Math.max(...xs)-Math.min(...xs))*1.25,(Math.max(...ys)-Math.min(...ys))*1.5));}}
  box.addEventListener('click',e=>{const f=e.target.closest('[data-f]');if(f){BS.f=f.dataset.f;QA('#bo-f button').forEach(x=>x.classList.toggle('on',x===f));boLista();if(boMapa)boMapa.draw();return;}
   const z=e.target.closest('[data-z="bo"]');if(z&&boMapa){boMapa.zoom(+z.dataset.k);return;}
   const b=e.target.closest('[data-bi]');if(b&&!e.target.closest('a')){const p=PR[+b.dataset.bi];BS.wyb=BS.wyb===p?null:p;boLista();if(boMapa){if(BS.wyb&&p._p)boMapa.cel(p._p[0],p._p[1],Math.min(boMapa.v.w,3000));else boMapa.draw();}}});}
 boKreator();
 const dk=Q('#bo-doKr');if(dk)dk.onclick=()=>Q('#bo-kreator').scrollIntoView({behavior:'smooth',block:'start'});
}


/* =====================================================================
   MAPA ZGŁOSZEŃ (dziury, awarie…) – wspólny mini-serwer /mieszkaniec-api/
   ===================================================================== */
const ZG_API='https://www.zyciepw.pl/mieszkaniec-api/?zasob=zgloszenia';
const ZK={droga:['🕳','Dziura w drodze lub chodniku','#C2410C'],oswietlenie:['💡','Nie świeci latarnia','#B45309'],woda:['🚰','Woda, kanalizacja, zalana ulica','#2563EB'],odpady:['🗑','Śmieci, dzikie wysypisko','#6B7280'],zielen:['🌳','Zieleń, drzewa','#15803D'],bezpieczenstwo:['🚸','Znak, przejście, bezpieczeństwo','#BE185D'],potrzeba:['✚','Potrzebna inwestycja','#7C3AED'],inne:['✉','Inna sprawa','#0891B2']};
const ZKR={droga:'Dziury',oswietlenie:'Latarnie',woda:'Woda',odpady:'Śmieci',zielen:'Zieleń',bezpieczenstwo:'Bezpieczeństwo',potrzeba:'Potrzeby',inne:'Inne'};
const ZMAP={oswietlenie:'oswietlenie',droga:'droga',woda:'woda',odpady:'odpady',zielen:'zielen',bezpieczenstwo:'bezpieczenstwo',potrzeba:'potrzeba',inne:'inne'};
function zgUid(){let u=LS('zg_u');if(!u){u=Array.from(crypto.getRandomValues(new Uint8Array(12)),b=>b.toString(16).padStart(2,'0')).join('');LS('zg_u',u);}return u;}
function zgIle(od){const m=Math.max(0,Math.floor((Date.now()-new Date(od))/60000)),d=Math.floor(m/1440),h=Math.floor(m%1440/60);
 if(d>=1)return d+' '+(d===1?'dzień':'dni')+(d<7&&h?' '+h+' godz.':'');if(h>=1)return h+' godz. '+(m%60)+' min';return (m%60)+' min';}
const pl=(n,a,b,c)=>n===1?a:(n%10>=2&&n%10<=4&&(n%100<10||n%100>=20))?b:c;
let ZG=null,ZS={kat:'',st:'otwarte',sort:'czeka',wyb:null},zgMapa=null,ZUL={};
async function zgWczytaj(){try{const r=await fetch(ZG_API+'&gmina=kobylka',{cache:'no-store'});if(!r.ok)throw 0;const j=await r.json();ZG=j.sprawy||[];ZG.forEach(z=>{z._p=EN(z.lat,z.lon);});return true;}catch(e){ZG=null;return false;}}
async function zgUlica(z){if(ZUL[z.id]!==undefined)return ZUL[z.id];ZUL[z.id]='';try{const r=MK.najblizszaUlica?await MK.najblizszaUlica(z.lat,z.lon):null;if(r&&r.u&&r.m<400)ZUL[z.id]=(r.m<40?'ul. ':'okolice ul. ')+r.u;}catch(e){}return ZUL[z.id];}
window.zgPublikuj=async function(o){
 const kat=ZMAP[o.cat];if(!kat||o.lat==null)return null;const fd=new FormData();
 fd.append('kat',kat);fd.append('lat',o.lat);fd.append('lon',o.lon);fd.append('opis',o.opis||'');fd.append('zrodlo','aplikacja-kobylka');fd.append('u',zgUid());
 if(o.foto){try{const b=await (await fetch(o.foto)).blob();fd.append('plik',b,'zgloszenie.jpg');}catch(e){}}
 try{const r=await fetch(ZG_API,{method:'POST',body:fd}),j=await r.json().catch(()=>({}));if(!r.ok){say(j.message||'Nie udało się dodać na mapę.');return null;}
  const z=j.sprawa;LS('zg_moje',[...(LS('zg_moje')||[]),z.id].slice(-100));ZG=null;
  say(j.dolaczono?'To miejsce ktoś już zgłosił. Twój głos dodano: '+z.n+' '+pl(z.n,'zgłoszenie','zgłoszenia','zgłoszeń')+', czeka '+zgIle(z.pierwsze)+'.':'Dodano na wspólną mapę zgłoszeń.');return j;}
 catch(e){say('Brak połączenia. Zgłoszenie nie trafiło na wspólną mapę.');return null;}};
function zgFiltr(){return(ZG||[]).filter(z=>z.status===ZS.st&&(!ZS.kat||z.kat===ZS.kat));}
function zgRysuj(c,X,Y,v,dk){zgFiltr().forEach(z=>{const k=ZK[z.kat]||ZK.inne,x=X(z._p[0]),y=Y(z._p[1]),r=Math.min(16,7+Math.sqrt(z.n)*2.2),on=ZS.wyb===z.id;
  c.beginPath();c.arc(x,y,r+(on?3:0),0,7);c.fillStyle=z.status==='naprawione'?(dk?'#0d131d':'#fff'):k[2];c.fill();c.lineWidth=on?4:2.5;c.strokeStyle=z.status==='naprawione'?'#0E7C6B':(dk?'#0d131d':'#fff');c.stroke();
  if(z.n>1&&z.status!=='naprawione'){c.fillStyle='#fff';c.font='700 11px system-ui';c.textAlign='center';c.fillText(z.n,x,y+4);c.textAlign='left';}});}
function zgKarta(z){const k=ZK[z.kat]||ZK.inne,tez=(LS('zg_tez')||[]).includes(z.id)||(LS('zg_moje')||[]).includes(z.id),nap=(LS('zg_nap')||[]).includes(z.id),otw=z.status==='otwarte';
 return `<div class="zgk${ZS.wyb===z.id?' on':''}" data-zid="${E(z.id)}"><div class="zgh"><i style="background:${k[2]}">${k[0]}</i><div class="grow"><b>${E(k[1])}</b><span class="mut" data-zul="${E(z.id)}">${E(ZUL[z.id]||'')}</span></div></div>
  ${otw?`<div class="zgl2"><div><b class="zglicz" data-od="${E(z.pierwsze)}">${zgIle(z.pierwsze)}</b><span>od pierwszego zgłoszenia</span></div><div><b>${z.n}</b><span>${pl(z.n,'zgłoszenie','zgłoszenia','zgłoszeń')}</span></div></div>`
   :`<div class="zgfix">✓ Naprawione ${new Date(z.naprawione_od).toLocaleDateString('pl-PL',{day:'numeric',month:'long'})}, po ${zgIle2(z.pierwsze,z.naprawione_od)} od pierwszego zgłoszenia</div>`}
  ${z.opis?`<p class="zgop">${E(z.opis)}</p>`:''}${z.plik?`<img class="zgimg" loading="lazy" alt="Zdjęcie zgłoszenia" src="${ZG_API}&plik=${E(z.plik)}">`:''}
  <span class="mut" style="font-size:12.5px">Pierwsze: ${new Date(z.pierwsze).toLocaleDateString('pl-PL',{day:'numeric',month:'long',year:'numeric'})}${z.n>1?' · ostatnie: '+zgIle(z.ostatnie)+' temu':''}${otw&&z.naprawione_glosy?' · '+z.naprawione_glosy+' '+pl(z.naprawione_glosy,'osoba mówi','osoby mówią','osób mówi')+', że naprawione':''}</span>
  ${otw?`<div class="zgb"><button data-ztez="${E(z.id)}" ${tez?'disabled':''}>${tez?'✓ Zgłoszone':'＋ Też to widzę'}</button><button data-znap="${E(z.id)}" ${nap?'disabled':''}>${nap?'✓ Dziękujemy':'Już naprawione'}</button><button data-zrad="${E(z.id)}">Przekaż radnym</button></div>`:''}</div>`;}
function zgIle2(a,b){const d=Math.max(0,Math.round((new Date(b)-new Date(a))/864e5));return d<1?'mniej niż dniu':d+' '+pl(d,'dniu','dniach','dniach');}
function zgLista(){const L=zgFiltr().slice();if(ZS.sort==='ile')L.sort((a,b)=>b.n-a.n||a.pierwsze.localeCompare(b.pierwsze));else L.sort((a,b)=>a.pierwsze.localeCompare(b.pierwsze));
 const box=Q('#zg-lista');box.innerHTML=L.length?L.map(zgKarta).join(''):`<p class="empty">${ZS.st==='otwarte'?'Brak otwartych zgłoszeń'+(ZS.kat?' w tej kategorii':'')+'.':'Nic jeszcze nie oznaczono jako naprawione.'}</p>`;
 L.forEach(z=>{if(ZUL[z.id]===undefined)zgUlica(z).then(u=>{const el=box.querySelector(`[data-zul="${z.id}"]`);if(el)el.textContent=u;});});}
function zgNaglowek(){const O=(ZG||[]).filter(z=>z.status==='otwarte'),N=(ZG||[]).filter(z=>z.status==='naprawione'),st=O.slice().sort((a,b)=>a.pierwsze.localeCompare(b.pierwsze))[0],suma=O.reduce((a,z)=>a+z.n,0);
 Q('#zg-hero').innerHTML=`<div><small>Otwarte sprawy w Kobyłce</small><b>${O.length}</b><span>${suma} ${pl(suma,'zgłoszenie','zgłoszenia','zgłoszeń')} mieszkańców${N.length?' · '+N.length+' naprawionych w ostatnich 90 dniach':''}</span></div>${st?`<div class="zgst"><small>Najdłużej czeka</small><b class="zglicz" data-od="${E(st.pierwsze)}">${zgIle(st.pierwsze)}</b><span>${E((ZK[st.kat]||ZK.inne)[1])}</span></div>`:''}`;
 const cnt={};O.forEach(z=>cnt[z.kat]=(cnt[z.kat]||0)+1);
 Q('#zg-kat').innerHTML=`<button data-zk="" class="${!ZS.kat?'on':''}">Wszystkie</button>`+Object.keys(ZK).filter(k=>cnt[k]||ZS.kat===k).map(k=>`<button data-zk="${k}" class="${ZS.kat===k?'on':''}" style="--k:${ZK[k][2]}"><i></i>${ZKR[k]} ${cnt[k]||0}</button>`).join('');}
async function zgInit(odswiez){
 const box=Q('#v-mapazgl');
 if(!box.dataset.ok){box.dataset.ok=1;box.innerHTML=`<h2>Mapa zgłoszeń</h2><p class="mut">Dziury, awarie i inne sprawy zgłoszone przez mieszkańców Kobyłki. Licznik pokazuje, ile czasu minęło od pierwszego zgłoszenia na tej mapie.</p>
  <div class="bhero zgh2" id="zg-hero"><div><small>Wczytuję…</small></div></div>
  <button class="btn gold wide" data-go="zglos">＋ Zgłoś nową sprawę</button>
  <div class="seg k2" id="zg-st" style="margin-top:14px"><button data-zs="otwarte" class="on">Czekają na naprawę</button><button data-zs="naprawione">Naprawione</button></div>
  <div class="kchips" id="zg-kat" style="margin-top:10px"></div>
  <div class="card" style="padding:10px"><div class="mwrap"><canvas id="zg-map" class="mcv"></canvas>${zoomBtns('zg')}</div><p class="mut" style="margin:8px 4px 0">Liczba w kółku to liczba zgłoszeń w tym miejscu. Dotknij punktu, aby zobaczyć szczegóły.</p></div>
  <div class="sec"><h3>Lista</h3><div class="seg" id="zg-sort" style="grid-template-columns:1fr 1fr;width:62%;margin:0"><button data-zo="czeka" class="on">najdłużej</button><button data-zo="ile">najczęściej</button></div></div>
  <div id="zg-lista"></div>
  <p class="mut">Zgłoszenia są anonimowe. Ta sama sprawa zgłoszona w promieniu ok. 35 m łączy się w jeden punkt z licznikiem. Sprawa trafia do „Naprawione”, gdy potwierdzą to 2 osoby albo moderator. Opisy i zdjęcia pokazujemy po sprawdzeniu przez moderatora. Licznik dotyczy zgłoszeń w tej aplikacji i na zyciepw.pl, a nie rejestru Urzędu: żeby sprawa trafiła do Urzędu, wyślij pismo albo przekaż ją radnym.</p>`;
  zgMapa=Mapa(Q('#zg-map'),zgRysuj,(x,y,r)=>{let best=null,bd=(Math.max(r,25)*2.2)**2;zgFiltr().forEach(z=>{const d=(z._p[0]-x)**2+(z._p[1]-y)**2;if(d<bd){bd=d;best=z;}});ZS.wyb=best?best.id:null;zgMapa.draw();zgLista();const el=Q('#zg-lista .on');if(el)el.scrollIntoView({behavior:'smooth',block:'nearest'});});
  box.addEventListener('click',zgKlik);
  setInterval(()=>{if(!Q('#v-mapazgl.on'))return;QA('#v-mapazgl .zglicz').forEach(b=>b.textContent=zgIle(b.dataset.od));},30000);}
 if(!ZG||odswiez){Q('#zg-lista').innerHTML='<p class="mut">Wczytuję zgłoszenia…</p>';const ok=await zgWczytaj();
  if(!ok){Q('#zg-hero').innerHTML='<div><small>Mapa zgłoszeń</small><b style="font-size:22px">Brak połączenia</b><span>Nie udało się wczytać zgłoszeń. Spróbuj za chwilę.</span></div>';Q('#zg-lista').innerHTML='';zgMapa.draw();return;}}
 zgNaglowek();zgLista();
 const P=(ZG||[]).map(z=>z._p);if(P.length&&!zgMapa._fit){zgMapa._fit=1;const xs=P.map(p=>p[0]),ys=P.map(p=>p[1]);zgMapa.cel((Math.min(...xs)+Math.max(...xs))/2,(Math.min(...ys)+Math.max(...ys))/2,Math.max(2500,(Math.max(...xs)-Math.min(...xs))*1.3,(Math.max(...ys)-Math.min(...ys))*1.6));}else zgMapa.draw();
}
async function zgGlos(id,akcja){const r=await fetch(ZG_API+'&akcja='+akcja,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,u:zgUid()})}).catch(()=>null);
 if(!r){say('Brak połączenia.');return;}const j=await r.json().catch(()=>({}));if(!r.ok){say(j.message||'Nie udało się.');return;}
 const kl=akcja==='tez'?'zg_tez':'zg_nap';LS(kl,[...(LS(kl)||[]),id].slice(-300));const i=ZG.findIndex(z=>z.id===id);if(i>=0&&j.sprawa){j.sprawa._p=ZG[i]._p;ZG[i]=j.sprawa;}
 say(j.juz?'Twój głos był już policzony.':akcja==='tez'?'Dziękujemy. Teraz '+j.sprawa.n+' '+pl(j.sprawa.n,'zgłoszenie','zgłoszenia','zgłoszeń')+'.':j.sprawa.status==='naprawione'?'Dziękujemy. Sprawa trafiła do naprawionych.':'Dziękujemy. Jeszcze jedna osoba musi potwierdzić naprawę.');
 zgNaglowek();zgLista();zgMapa.draw();}
function zgRadni(z){const k=ZK[z.kat]||ZK.inne,ul=ZUL[z.id]||'';
 const t='Szanowni Państwo Radni,\n\nproszę o interwencję w sprawie zgłoszonej przez mieszkańców na wspólnej mapie zgłoszeń:\n\n'+k[1]+(ul?', '+ul:'')+'\nMiejsce na mapie: https://www.google.com/maps?q='+z.lat+','+z.lon+'\nPierwsze zgłoszenie: '+new Date(z.pierwsze).toLocaleDateString('pl-PL')+' (czeka '+zgIle(z.pierwsze)+')\nLiczba zgłoszeń: '+z.n+(z.opis?'\nOpis: '+z.opis:'')+'\n\nProszę o przekazanie sprawy Burmistrzowi i informację, kiedy zostanie załatwiona.\n\nZ poważaniem\n(imię i nazwisko, ulica)\n\n(Z mapy zgłoszeń w Aplikacji mieszkańca Kobyłki)';
 doRadnych(k[1]+(ul?', '+ul:'')+' – '+z.n+' '+pl(z.n,'zgłoszenie','zgłoszenia','zgłoszeń'),t);}
function zgKlik(e){const b=e.target.closest('button');
 if(b){if(b.dataset.zs){ZS.st=b.dataset.zs;ZS.wyb=null;QA('#zg-st button').forEach(x=>x.classList.toggle('on',x===b));zgNaglowek();zgLista();zgMapa.draw();return;}
  if(b.dataset.zk!==undefined){ZS.kat=b.dataset.zk;ZS.wyb=null;zgNaglowek();zgLista();zgMapa.draw();return;}
  if(b.dataset.zo){ZS.sort=b.dataset.zo;QA('#zg-sort button').forEach(x=>x.classList.toggle('on',x===b));zgLista();return;}
  if(b.dataset.z==='zg'){zgMapa.zoom(+b.dataset.k);return;}
  if(b.dataset.ztez){b.disabled=true;zgGlos(b.dataset.ztez,'tez');return;}
  if(b.dataset.znap){b.disabled=true;zgGlos(b.dataset.znap,'naprawione');return;}
  if(b.dataset.zrad){zgRadni(ZG.find(z=>z.id===b.dataset.zrad));return;}return;}
 const k=e.target.closest('[data-zid]');if(k&&!e.target.closest('a')){const z=ZG.find(x=>x.id===k.dataset.zid);ZS.wyb=ZS.wyb===z.id?null:z.id;QA('#zg-lista .zgk').forEach(x=>x.classList.toggle('on',x.dataset.zid===ZS.wyb));if(ZS.wyb)zgMapa.cel(z._p[0],z._p[1],Math.min(zgMapa.v.w,2500));else zgMapa.draw();Q('#zg-map').scrollIntoView({behavior:'smooth',block:'center'});}}

window.modGo=function(v){if(v==='mapazgl')zgInit(true);else if(v==='kom')komInit();else if(v==='budzet')budzetInit();else if(v==='wniosekb')wniosekbInit();else if(v==='bo')boInit();};
const h0=(location.hash||'').slice(1);if(['kom','budzet','wniosekb','bo','mapazgl'].indexOf(h0)>=0)setTimeout(()=>{if(Q('#v-'+h0+'.on'))window.modGo(h0);},600);
})();
