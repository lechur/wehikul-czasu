import re
s=open('/home/claude/wc/kobylka/index.html',encoding='utf-8').read()
def rep(a,b,cnt=1):
    global s
    assert a in s, a[:90]
    s=s.replace(a,b,cnt)
I={'home':'<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20h5v-6h4v6h5V9.5"/>','trash':'<path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M6 7l1 13h10l1-13"/><path d="M10 11v6M14 11v6"/>','plus':'<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
'building':'<path d="M4 20h16M5 20V9l7-5 7 5v11"/><path d="M9 20v-6h6v6M9 10.5h.01M15 10.5h.01"/>','user':'<circle cx="12" cy="8" r="3.6"/><path d="M4.5 20c0-4 3.4-7 7.5-7s7.5 3 7.5 7"/>',
'users':'<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.5"/><path d="M15.5 14.2c3 .2 5.5 2.6 5.5 5.8"/>','cal':'<rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
'phone':'<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>','store':'<path d="M4 9l1.5-5h13L20 9"/><path d="M4 9a2.7 2.7 0 0 0 5.3 0 2.7 2.7 0 0 0 5.4 0 2.7 2.7 0 0 0 5.3 0"/><path d="M5.5 11.5V20h13v-8.5M10 20v-5h4v5"/>',
'coins':'<ellipse cx="9" cy="7" rx="6" ry="2.6"/><path d="M3 7v4c0 1.4 2.7 2.6 6 2.6s6-1.2 6-2.6V7"/><path d="M3 11v4c0 1.4 2.7 2.6 6 2.6 1 0 1.9-.1 2.7-.3"/><circle cx="17" cy="16" r="4"/>','pin':'<path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.3"/>',
'alert':'<path d="M12 3 2.5 20h19z"/><path d="M12 10v4.5M12 17.5v.01"/>','chev':'<path d="M9 6l6 6-6 6"/>'}
def ic(n,sz=24): return f'<svg class="i" width="{sz}" height="{sz}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{I[n]}</svg>'
rep('<head>','<head>\n<base href="../">\n<meta name="robots" content="noindex">')
rep('<title>Aplikacja mieszkańca Kobyłki</title>','<title>Aplikacja mieszkańca Kobyłki – propozycja</title>')
# ---- start view
a=s.index('<section class="view on" id="v-start">'); b=s.index('</section>',a)+10
about=re.search(r'<div class="card about">.*?</div>\n',s[a:b],re.S).group(0)
HOWCARD=re.search(r'<div class="card how">.*?</div></div>\n',s[a:b],re.S).group(0).strip()
start=f'''<section class="view on" id="v-start">
  <div id="hero"></div>
  <div class="card" id="s-waste"></div>
  <div class="qa4">
   <button data-go="zglos"><span class="ico c5">{ic('alert')}</span>Zgłoś</button>
   <button data-go="urzad"><span class="ico c2">{ic('building')}</span>Urząd</button>
   <button data-go="rada"><span class="ico c4">{ic('users')}</span>Radni</button>
   <button data-go="wiecej"><span class="ico c3">{ic('phone')}</span>Telefony</button>
  </div>
  <div id="s-sprawy-wrap"><div class="sec"><h3>Moje sprawy</h3><button data-go="sprawy">Otwórz</button></div><div class="card" id="s-sprawy"></div></div>
  <div class="sec"><h3>Najbliższe dni</h3><button data-go="kal">Kalendarz</button></div>
  <div id="agenda"></div>
  {HOWCARD}
  <button class="card lok-teaser" data-go="lokalnie"><span class="ico c1">{ic('coins')}</span><span class="grow"><b>Ze 100 zł u sąsiada 48 zł zostaje w Kobyłce</b><small>W sieci handlowej tylko 14 zł. Zobacz, jak to działa</small></span><span class="chev">{ic('chev',20)}</span></button>
  {about}  <div hidden><div id="s-terminy"></div><div id="s-zap"></div><div id="s-firmy"></div><b id="s-dzis"></b><div id="s-sun"></div><span id="s-moon"></span><canvas id="startsky"></canvas><div id="s-ev"></div></div>
</section>'''
s=s[:a]+start+s[b:]
# ---- nav
n=re.search(r'<nav class="bar">.*?</nav>',s,re.S).group(0)
rep(n,f'''<nav class="bar">
  <button data-go="start" class="on">{ic('home')}Dziś</button>
  <button data-go="odpady">{ic('trash')}Odpady</button>
  <button data-go="zglos" class="fab">{ic('plus')}Zgłoś</button>
  <button data-go="miasto">{ic('building')}Miasto</button>
  <button data-go="menu">{ic('user')}Moje</button>
</nav>''')
# ---- menu split
a=s.index('<section class="view" id="v-menu">'); b=s.index('</section>',a)+10
m=s[a:b]
k1=m.index('<h3>Miasto</h3>'); k2=m.index('<h3>Ty</h3>')
m2=m[:k1]+m[k2:]
m2=m2.replace('<h2>Więcej</h2>','<h2>Moje</h2>').replace('<h3>Ty</h3>','')
def tile(go,c,i,t,sub): return f'<button data-go="{go}"><span class="ico {c}">{ic(i)}</span><span><b>{t}</b><small>{sub}</small></span></button>'
miasto=f'''<section class="view" id="v-miasto">
  <h2>Miasto</h2>
  <div class="qa">
   {tile('urzad','c2','building','Urząd','godziny, płatności, sprawy')}
   {tile('rada','c4','users','Rada i radni','Twój okręg, sesje, dyżury')}
   {tile('kal','c3','cal','Wydarzenia','co się dzieje w mieście')}
   {tile('wiecej','c5','phone','Ważne telefony','awarie i alarmowe')}
   {tile('firmy','c1','store','Firmy sąsiadów','usługi i sklepy z Kobyłki')}
   {tile('lokalnie','c1','coins','Twoje pieniądze','zakupy lokalne i PIT')}
   {tile('odkryj','c6','pin','Odkrywaj','mapy, historia, niebo')}
  </div>
</section>
'''
s=s[:a]+miasto+m2+s[b:]
# ---- routing
rep("const TAB={start:'start',odpady:'odpady',zglos:'zglos',kal:'kal',menu:'menu',sprawy:'menu',firmy:'menu',wiecej:'menu',odkryj:'menu',urzad:'menu',rada:'menu',lokalnie:'menu'};",
    "const TAB={start:'start',odpady:'odpady',zglos:'zglos',miasto:'miasto',menu:'menu',sprawy:'menu',kal:'miasto',firmy:'miasto',wiecej:'miasto',odkryj:'miasto',urzad:'miasto',rada:'miasto',lokalnie:'miasto'};")
rep("hd.classList.toggle('sub',TAB[v]==='menu'&&v!=='menu');","hd.classList.toggle('sub',!['start','odpady','zglos','miasto','menu'].includes(v));")
rep("'sprawy','wiecej','menu','urzad','rada','lokalnie'].includes(h))go(h);","'sprawy','wiecej','menu','urzad','rada','lokalnie','miasto'].includes(h))go(h);")
rep("history.pushState({v},'','#'+v);","history.pushState({v},'',location.pathname+location.search+'#'+v);")
rep("history.length>1?history.back():go(\\'menu\\')","history.length>1?history.back():go(\\'miasto\\')") if "go(\\'menu\\')" in s else rep("history.length>1?history.back():go('menu')","history.length>1?history.back():go('miasto')")
rep("window.scrollTo(0,0);if(DANE){","window.scrollTo(0,0);if(DANE){if(v==='start')agenda();")
rep("renderTerminy();renderUrzad();renderRada();adresMenu();","renderTerminy();renderUrzad();renderRada();adresMenu();agenda();")
rep("store('ulica_info',info||null);odpady();}","store('ulica_info',info||null);odpady();agenda();if(window._onb){window._onb=0;go('start');toast('Gotowe. Pokazuję terminy dla ulicy '+u+'.');}}")
js=r'''
/* ===== Dziś: powitanie i wspólna oś najbliższych dni ===== */
function agenda(){const u=store('ulica'),x=u&&rejonUlicy(u),H=$('#hero'),A=$('#agenda');if(!H||!A)return;
 const g=new Date().getHours(),hi=g<5?'Dobry wieczór':g<18?'Dzień dobry':'Dobry wieczór';
 if(!x){$('#s-waste').hidden=true;H.innerHTML=`<div class="welcome"><div class="w-t">${hi} w Kobyłce</div><p>Podaj swoją ulicę, a pokażę wywóz odpadów pod Twoim adresem, Twoich radnych i najbliższe terminy. Adres zostaje tylko w tym telefonie.</p><button class="btn wide w-b1" id="onb-gps">📍 Użyj mojej lokalizacji</button><button class="btn wide line" id="onb-typ" style="margin-top:8px">Wpiszę ulicę</button></div>`;
  $('#onb-typ').onclick=()=>{window._onb=1;go('odpady');setTimeout(()=>$('#o-ulica').focus(),80);};$('#onb-gps').onclick=()=>{window._onb=1;go('odpady');$('#o-gps').click();};}
 else{$('#s-waste').hidden=false;const ok=okregUlicy();H.innerHTML=`<div class="hello"><div><div class="h-t">${hi}</div><div class="mut">${esc(x.u)} · ${x.r.nazwa}${ok?' · okręg '+ok:''}</div></div><button class="btn sm line" data-go="odpady">Zmień</button></div>`;}
 const t0=today(),t1=addDays(t0,21),E=[];
 if(x)grupuj((x.r.terminy||[]).filter(t=>t.data>=t0).sort((a,b)=>a.data<b.data?-1:1)).slice(1).filter(([d])=>d<=t1).forEach(([d,b])=>E.push({d,k:'w',t:'Wywóz: '+b.map(p=>p.n).join(', '),go:'odpady',dots:b}));
 terminyPlatnosci().filter(z=>z.d<=t1).forEach(z=>E.push({d:z.d,k:/Sesja|Dyżur/.test(z.lab)?'r':'p',t:z.lab,go:/Sesja|Dyżur/.test(z.lab)?'rada':'urzad'}));
 const per={};EVS.filter(e=>e.kind==='miasto'&&!/raty podatku|opłat/i.test(e.title)&&e.d>=t0&&e.d<=addDays(t0,7)).sort((a,b)=>(a.d+a.t<b.d+b.t?-1:1)).forEach(e=>{per[e.d]=(per[e.d]||0)+1;if(per[e.d]<=2)E.push({d:e.d,k:'e',t:e.title.replace(/^\S+\s/,''),s:[e.t,e.miejsce].filter(Boolean).join(' · '),go:'kal'});else if(per[e.d]===3)E.push({d:e.d,k:'m',t:'Więcej wydarzeń tego dnia',go:'kal'});});
 const ord={w:0,p:1,r:2,e:3,m:4};E.sort((a,b)=>a.d<b.d?-1:a.d>b.d?1:ord[a.k]-ord[b.k]);
 const IK={w:['c1','♻'],p:['c3','zł'],r:['c4','R'],e:['c2','•'],m:['c6','…']};const days=[...new Set(E.map(e=>e.d))].slice(0,7);
 A.innerHTML=days.length?days.map(d=>`<div class="aday"><div class="aday-h">${kiedy(d)}</div><div class="card acard">${E.filter(e=>e.d===d).map(e=>`<button class="arow" data-go="${e.go}"><span class="ico ${IK[e.k][0]}">${IK[e.k][1]}</span><span class="grow"><b>${esc(e.t)}</b>${e.dots?`<span class="wtags">${e.dots.map(p=>`<span class="wtag"><i style="background:${p.c}"></i></span>`).join('')}</span>`:''}${e.s?`<small>${esc(e.s)}</small>`:''}</span></button>`).join('')}</div></div>`).join(''):'<div class="card mut">Na najbliższe dni nie mam nic w kalendarzu.</div>';}
'''
rep("/* ===== odpady ===== */",js+"\n/* ===== odpady ===== */")
css='''
/* ===== propozycja v2 ===== */
.welcome{background:linear-gradient(140deg,#14315A,#0E7C6B);color:#fff;border-radius:22px;padding:22px 18px;margin:8px 0 14px}.welcome .w-t{font-size:26px;font-weight:800;letter-spacing:-.02em}.welcome p{margin:8px 0 16px;font-size:15.5px;line-height:1.5;opacity:.95}
.welcome .w-b1{background:#fff;color:#14315A}.welcome .btn.line{background:transparent;color:#fff;border-color:rgba(255,255,255,.5)}
.hello{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:6px 0 10px}.hello .h-t{font-size:24px;font-weight:800;letter-spacing:-.02em}
.qa4{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:12px 0 4px}
.qa4 button{display:flex;flex-direction:column;align-items:center;gap:6px;border:0;background:none;font:inherit;font-size:13px;font-weight:600;color:var(--ink);padding:4px 0;cursor:pointer}
.qa4 .ico{width:54px;height:54px;border-radius:17px;display:grid;place-items:center}
.aday-h{font-size:14px;font-weight:700;margin:14px 2px 6px}.aday:first-child .aday-h{margin-top:4px}
.acard{padding:4px 14px;margin:0}
.arow{display:flex;align-items:center;gap:12px;width:100%;border:0;border-bottom:1px solid var(--line);background:none;font:inherit;color:var(--ink);text-align:left;padding:11px 0;cursor:pointer;min-height:56px}.arow:last-child{border-bottom:0}
.arow .ico{width:36px;height:36px;border-radius:11px;display:grid;place-items:center;flex:none;font-weight:800;font-size:14px}
.arow b{display:block;font-size:15px;font-weight:600;line-height:1.3}.arow small{display:block;color:var(--mut);font-size:13px}.arow .wtags{margin-top:3px}
#v-miasto .qa button{min-height:112px}
'''
rep('/* ===== tryb ciemny ===== */',css+'/* ===== tryb ciemny ===== */')
open('/home/claude/wc/kobylka/v2/index.html','w',encoding='utf-8').write(s)
print('ok')
