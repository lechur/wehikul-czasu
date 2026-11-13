/* Życie Powiatu – plakaty: mapy, niebo, miasto z lotu ptaka. Działa w artefakcie i w WordPressie. */
(function(){
'use strict';
const CFG = Object.assign({
  dataUrl: '', wydawca: 'Życie Powiatu na Mazowszu', portal: 'zyciepw.pl',
  kontakt: 'reklama@zyciepw.pl', tel: '22 787 77 83', orderUrl: '', nonce: ''
}, window.ZPW_PLAKATY || {});
const $ = s => document.querySelector(s);

/* ---------- ceny (brutto, zł) ---------- */
const PRICE = {
  '30x40': {plakat: 79, rama: 139},
  '50x70': {plakat: 119, rama: 199},
};
const DUET = {'30x40': {plakat: 149, rama: 259}, '50x70': {plakat: 219, rama: 369}};
const SHIP = {paczkomat: 15, kurier: 19, odbior: 0};

/* ---------- PUWG 1992 <-> WGS84 ---------- */
const a=6378137, f=1/298.257222101, k0=0.9993, lon0=19*Math.PI/180, FE=500000, FN=-5300000;
const n=f/(2-f), A=a/(1+n)*(1+n*n/4+n**4/64);
const al=[n/2-2*n*n/3+5*n**3/16, 13*n*n/48-3*n**3/5, 61*n**3/240];
const be=[n/2-2*n*n/3+37*n**3/96, n*n/48+n**3/15, 17*n**3/480];
const de=[2*n-2*n*n/3-2*n**3, 7*n*n/3-8*n**3/5, 56*n**3/15];
const e=Math.sqrt(f*(2-f));
function inv(E,N){
  const xi=(N-FN)/(k0*A), eta=(E-FE)/(k0*A); let x=xi, y=eta;
  for(let j=0;j<3;j++){x-=be[j]*Math.sin(2*(j+1)*xi)*Math.cosh(2*(j+1)*eta); y-=be[j]*Math.cos(2*(j+1)*xi)*Math.sinh(2*(j+1)*eta);}
  const chi=Math.asin(Math.sin(x)/Math.cosh(y)); let lat=chi;
  for(let j=0;j<3;j++) lat+=de[j]*Math.sin(2*(j+1)*chi);
  return [ (lon0+Math.atan2(Math.sinh(y),Math.cos(x)))*180/Math.PI, lat*180/Math.PI ];
}
function dms(v,p,m){const s=Math.round(Math.abs(v)*3600),d=Math.floor(s/3600),mm=Math.floor(s%3600/60),ss=s%60;return d+'°'+String(mm).padStart(2,'0')+'′'+String(ss).padStart(2,'0')+'″ '+(v>=0?p:m);}

/* ---------- dane ---------- */
let MAP=null, SKY=null;
function decode(arr){const p=[];let x=0,y=0;for(let i=0;i<arr.length;i+=2){x+=arr[i];y+=arr[i+1];p.push(x,y);}return p;}
function bbox(p){let a=1e12,b=1e12,c=-1e12,d=-1e12;for(let i=0;i<p.length;i+=2){if(p[i]<a)a=p[i];if(p[i]>c)c=p[i];if(p[i+1]<b)b=p[i+1];if(p[i+1]>d)d=p[i+1];}return [a,b,c,d];}
function prep(list){return list.map(l=>{const p=decode(l);return {p,b:bbox(p)};});}
async function load(){
  const base=CFG.dataUrl;
  const [m,s]=await Promise.all([fetch(base+'powiat.json').then(r=>r.json()),fetch(base+'sky.json').then(r=>r.json())]);
  MAP={roads:m.roads.map(prep),rail:prep(m.rail),water:prep(m.water),forest:prep(m.forest)};
  SKY=s;
}

/* ---------- style ---------- */
const STYLES = {
  krem:   {name:'Krem',   bg:'#F3EDE2', ink:'#1E2A36', acc:'#C8553D', water:'#A9C1CE', forest:'#E2DCCB'},
  noc:    {name:'Noc',    bg:'#0F1B2D', ink:'#E9E2D0', acc:'#E7C46C', water:'#24405E', forest:'#15243A'},
  las:    {name:'Las',    bg:'#EEF1EA', ink:'#1F3A2B', acc:'#2E7D4F', water:'#9CC0D0', forest:'#D2E0CF'},
  terra:  {name:'Terakota',bg:'#F6E9DD',ink:'#5A2E1E', acc:'#C8553D', water:'#B8CFD6', forest:'#EBD8C6'},
  czern:  {name:'Czerń',  bg:'#121212', ink:'#F2F2F2', acc:'#FF6B4A', water:'#2A3440', forest:'#1C1C1C'},
};
const RW=[4.4,3.4,2.4,1.5,1.05,0.55];

/* ---------- stan ---------- */
const S = {
  produkt:'mapa', miasto:'Kobyłka', E:0, N:0, zoom:1400, styl:'krem',
  tytul:'TU MIESZKAMY', podtytul:'Kobyłka · od 2021 roku', pin:'serce',
  data:'2020-02-14', godz:'21:00', skyTytul:'Tu się zaczęło', skyPodtytul:'Anna & Tomek',
  skyStyl:'noc', format:'50x70', rama:'plakat', ramaKolor:'czarna', ilosc:1,
  miastoProd:'wzrost'
};
function townXY(t){const c=SKY.towns[t];return c;}

/* ---------- rysowanie mapy ---------- */
function drawMap(ctx,W,H,x,y,w,h,st,pinOn){
  const s=STYLES[st];
  const scale=w/S.zoom; const cx=S.E, cy=S.N;
  const X=v=>x+w/2+(v-cx)*scale, Y=v=>y+h/2-(v-cy)*scale;
  const vb=[cx-(w/2)/scale,cy-(h/2)/scale,cx+(w/2)/scale,cy+(h/2)/scale];
  const vis=b=>!(b[2]<vb[0]||b[0]>vb[2]||b[3]<vb[1]||b[1]>vb[3]);
  ctx.save(); ctx.beginPath(); ctx.rect(x,y,w,h); ctx.clip();
  ctx.fillStyle=s.bg; ctx.fillRect(x,y,w,h);
  const poly=(list,col)=>{ctx.fillStyle=col;for(const o of list){if(!vis(o.b))continue;const p=o.p;ctx.beginPath();ctx.moveTo(X(p[0]),Y(p[1]));for(let i=2;i<p.length;i+=2)ctx.lineTo(X(p[i]),Y(p[i+1]));ctx.closePath();ctx.fill();}};
  poly(MAP.forest,s.forest); poly(MAP.water,s.water);
  const k=Math.max(0.6,Math.min(2.2,1600/S.zoom))*(w/900);
  ctx.strokeStyle=s.ink; ctx.lineCap='round'; ctx.lineJoin='round';
  for(let c=5;c>=0;c--){ctx.lineWidth=RW[c]*k;ctx.globalAlpha=c===5?0.55:1;ctx.beginPath();
    for(const o of MAP.roads[c]){if(!vis(o.b))continue;const p=o.p;ctx.moveTo(X(p[0]),Y(p[1]));for(let i=2;i<p.length;i+=2)ctx.lineTo(X(p[i]),Y(p[i+1]));}
    ctx.stroke();}
  ctx.globalAlpha=1; ctx.setLineDash([4*k,3*k]); ctx.lineWidth=1.8*k; ctx.beginPath();
  for(const o of MAP.rail){if(!vis(o.b))continue;const p=o.p;ctx.moveTo(X(p[0]),Y(p[1]));for(let i=2;i<p.length;i+=2)ctx.lineTo(X(p[i]),Y(p[i+1]));}
  ctx.stroke(); ctx.setLineDash([]);
  if(pinOn){const px=X(S.E),py=Y(S.N),r=w*0.024; ctx.fillStyle=s.acc; ctx.strokeStyle=s.bg; ctx.lineWidth=r*0.35;
    if(S.pin==='serce'){heart(ctx,px,py,r*1.6,s.acc,s.bg);} else {ctx.beginPath();ctx.arc(px,py,r,0,7);ctx.fill();ctx.stroke();ctx.beginPath();ctx.arc(px,py,r*2.6,0,7);ctx.strokeStyle=s.acc;ctx.lineWidth=r*0.18;ctx.stroke();}}
  ctx.restore();
  return {X,Y,scale};
}
function heart(ctx,x,y,r,fill,stroke){ctx.save();ctx.translate(x,y);ctx.beginPath();ctx.moveTo(0,r*0.35);
  ctx.bezierCurveTo(-r*1.1,-r*0.35,-r*0.55,-r*1.1,0,-r*0.45);ctx.bezierCurveTo(r*0.55,-r*1.1,r*1.1,-r*0.35,0,r*0.35);
  ctx.fillStyle=fill;ctx.fill();ctx.lineWidth=r*0.18;ctx.strokeStyle=stroke;ctx.stroke();ctx.restore();}

/* ---------- niebo ---------- */
function lst(t,lon){const jd=t/86400000+2440587.5,d=jd-2451545;return ((280.46061837+360.98564736629*d+lon)%360+360)%360;}
function altaz(ra,dec,L,lat){const r=Math.PI/180,ha=(L-ra)*r,de=dec*r,la=lat*r;
  const alt=Math.asin(Math.sin(de)*Math.sin(la)+Math.cos(de)*Math.cos(la)*Math.cos(ha));
  const az=Math.atan2(-Math.sin(ha)*Math.cos(de),Math.cos(la)*Math.sin(de)-Math.sin(la)*Math.cos(de)*Math.cos(ha));
  return [alt/r,((az/r)%360+360)%360];}
function proj(alt,az){const r=Math.tan((90-alt)*Math.PI/360),z=az*Math.PI/180;return [-r*Math.sin(z),-r*Math.cos(z)];}
function moon(t){const jd=t/86400000+2440587.5,T=(jd-2451545)/36525,r=Math.PI/180;
  const L=218.316+481267.881*T,M=134.963+477198.867*T,F=93.272+483202.018*T;
  const l=(L+6.289*Math.sin(M*r))*r,b=5.128*Math.sin(F*r)*r,e=23.439*r;
  const ra=Math.atan2(Math.sin(l)*Math.cos(e)-Math.tan(b)*Math.sin(e),Math.cos(l)),dec=Math.asin(Math.sin(b)*Math.cos(e)+Math.cos(b)*Math.sin(e)*Math.sin(l));
  const Ls=280.459+36000.77*T+1.915*Math.sin((357.529+35999.05*T)*r);const el=(((l/r)-Ls)%360+360)%360;
  return {ra:((ra/r)%360+360)%360,dec:dec/r,ill:(1-Math.cos(el*r))/2,el};}
function localToUTC(dateStr,timeStr){ // Europe/Warsaw
  const [Y,M,D]=dateStr.split('-').map(Number),[h,mi]=timeStr.split(':').map(Number);
  let t=Date.UTC(Y,M-1,D,h,mi)-3600000; // try CET
  const fmt=new Intl.DateTimeFormat('en-GB',{timeZone:'Europe/Warsaw',hour:'2-digit',minute:'2-digit',hour12:false});
  for(let k=0;k<2;k++){const p=fmt.format(new Date(t)).split(':').map(Number);const diff=(h*60+mi)-(p[0]*60+p[1]);if(diff===0)break;t+=((diff+720)%1440-720)*60000;}
  return t;}
function drawSky(ctx,cx,cy,R,lat,lon,t,st){
  const s=STYLES[st]; const L=lst(t,lon);
  ctx.save(); ctx.beginPath(); ctx.arc(cx,cy,R,0,7); ctx.clip();
  ctx.strokeStyle=s.ink; ctx.globalAlpha=0.45; ctx.lineWidth=Math.max(0.6,R/700);
  ctx.beginPath();
  for(const g of SKY.lines){const A=altaz(g[0],g[1],L,lat),B=altaz(g[2],g[3],L,lat);if(A[0]<0||B[0]<0)continue;
    const p=proj(A[0],A[1]),q=proj(B[0],B[1]);ctx.moveTo(cx+p[0]*R,cy+p[1]*R);ctx.lineTo(cx+q[0]*R,cy+q[1]*R);}
  ctx.stroke(); ctx.globalAlpha=1; ctx.fillStyle=s.ink;
  for(const st2 of SKY.stars){const A=altaz(st2[0],st2[1],L,lat);if(A[0]<0)continue;const p=proj(A[0],A[1]);
    const rr=Math.max(0.35,(5.9-st2[2]))*R/520; ctx.beginPath(); ctx.arc(cx+p[0]*R,cy+p[1]*R,rr,0,7); ctx.fill();}
  const m=moon(t),MA=altaz(m.ra,m.dec,L,lat);
  if(MA[0]>0){const p=proj(MA[0],MA[1]),mx=cx+p[0]*R,my=cy+p[1]*R,mr=R*0.03;
    ctx.fillStyle=s.ink;ctx.beginPath();ctx.arc(mx,my,mr,0,7);ctx.fill();
    ctx.fillStyle=s.bg;ctx.globalAlpha=0.85;ctx.beginPath();ctx.arc(mx+(m.el<180?-1:1)*mr*2*m.ill,my,mr,0,7);ctx.fill();ctx.globalAlpha=1;}
  ctx.restore();
  ctx.strokeStyle=s.ink; ctx.lineWidth=Math.max(1,R/260); ctx.beginPath(); ctx.arc(cx,cy,R,0,7); ctx.stroke();
  ctx.fillStyle=s.ink; ctx.font=`${Math.round(R/26)}px system-ui, sans-serif`; ctx.textAlign='center'; ctx.textBaseline='middle';
  ctx.globalAlpha=.7; [['PN',0,-1],['WSCH',-1,0],['PD',0,1],['ZACH',1,0]].forEach(([l,dx,dy])=>ctx.fillText(l,cx+dx*R*1.07,cy+dy*R*1.07)); ctx.globalAlpha=1;
}

/* ---------- miasto z lotu ptaka (gotowe obrazy) ---------- */
const CITY={
  'Kobyłka':{wzrost:'img/kobylka_wzrost.jpg',rzezba:'img/kobylka_rzezba.jpg',budynki:'img/kobylka_budynki.jpg'},
  'Marki':{wzrost:'img/marki_wzrost.jpg',rzezba:'img/marki_rzezba.jpg',budynki:'img/marki_budynki.jpg'},
};
const CITYTXT={wzrost:['Jak rosło miasto','Na kolorowo budynki, które przybyły w latach 2001–2024'],rzezba:['Na czym stoi miasto','Rzeźba terenu z lotniczego skanowania laserowego (lidar)'],budynki:['Każdy budynek','Wszystkie budynki miasta, każdy w swoim kształcie']};
const imgCache={};
function getImg(src){if(imgCache[src])return imgCache[src];const im=new Image();im.src=CFG.dataUrl+src;im.onload=()=>render();imgCache[src]=im;return im;}

/* ---------- plakat ---------- */
function poster(ctx,W,H){
  const P=S.produkt; const st=P==='niebo'?S.skyStyl:(P==='miasto'?(S.miastoProd==='wzrost'?'czern':S.miastoProd==='rzezba'?'terra':'krem'):S.styl);
  const s=STYLES[st]; ctx.fillStyle=s.bg; ctx.fillRect(0,0,W,H);
  const m=W*0.08; const lonlat=inv(S.E,S.N);
  const serif='Georgia, "Times New Roman", serif', sans='system-ui, -apple-system, "Segoe UI", Arial, sans-serif';
  const text=(t,y,size,font,col,alpha,weight,spacing)=>{ctx.fillStyle=col;ctx.globalAlpha=alpha||1;ctx.font=`${weight||''} ${Math.round(size)}px ${font}`;ctx.textAlign='center';ctx.textBaseline='alphabetic';
    if(spacing){ctx.letterSpacing=spacing+'px';} ctx.fillText(t,W/2,y); ctx.letterSpacing='0px'; ctx.globalAlpha=1;};
  if(P==='mapa'){
    const mh=H*0.70; drawMap(ctx,W,H,m,m,W-2*m,mh-m,st,true);
    ctx.strokeStyle=s.ink;ctx.globalAlpha=.25;ctx.lineWidth=W/600;ctx.strokeRect(m,m,W-2*m,mh-m);ctx.globalAlpha=1;
    text(S.tytul,H*0.80,W*0.072,serif,s.ink,1,'',W*0.006);
    text(S.podtytul,H*0.845,W*0.026,sans,s.acc,1,'600');
    text(dms(lonlat[1],'N','S')+'   ·   '+dms(lonlat[0],'E','W'),H*0.88,W*0.018,sans,s.ink,.65);
  } else if(P==='niebo'){
    const tc=localToUTC(S.data,S.godz); const R=W*0.36;
    drawSky(ctx,W/2,H*0.36,R,lonlat[1],lonlat[0],tc,st);
    text(S.skyTytul,H*0.78,W*0.064,serif,s.ink);
    text(S.skyPodtytul.toUpperCase(),H*0.825,W*0.026,sans,s.acc,1,'600',W*0.004);
    const d=new Date(S.data+'T12:00:00');const ds=d.toLocaleDateString('pl-PL',{day:'numeric',month:'long',year:'numeric'});
    text(S.miasto+'  ·  '+ds+'  ·  '+S.godz,H*0.865,W*0.021,sans,s.ink,.85);
    text(dms(lonlat[1],'N','S')+'   '+dms(lonlat[0],'E','W'),H*0.895,W*0.016,sans,s.ink,.6);
  } else if(P==='duet'){
    // mapa po lewej, niebo po prawej (podgląd dwóch plakatów obok siebie)
    const hw=W/2; drawMap(ctx,W,H,m*0.6,m,hw-m*1.2,H*0.62,st,true);
    drawSky(ctx,hw+hw/2,m+H*0.31,hw*0.4,lonlat[1],lonlat[0],localToUTC(S.data,S.godz),st);
    text(S.tytul,H*0.80,W*0.05,serif,s.ink); text(S.podtytul,H*0.845,W*0.022,sans,s.acc,1,'600');
  } else if(P==='miasto'){
    const src=(CITY[S.miasto]||CITY['Kobyłka'])[S.miastoProd]; const im=getImg(src);
    const mw=W-2*m, mh=H*0.68;
    if(im.complete&&im.naturalWidth){const r=Math.max(mw/im.naturalWidth,mh/im.naturalHeight),iw=im.naturalWidth*r,ih=im.naturalHeight*r;
      ctx.save();ctx.beginPath();ctx.rect(m,m,mw,mh);ctx.clip();ctx.drawImage(im,m+(mw-iw)/2,m+(mh-ih)/2,iw,ih);ctx.restore();}
    else {ctx.fillStyle=s.forest;ctx.fillRect(m,m,mw,mh);}
    const city=(CITY[S.miasto]?S.miasto:'Kobyłka');
    text(city.toUpperCase(),H*0.80,W*0.08,serif,s.ink,1,'',W*0.012);
    text(CITYTXT[S.miastoProd][0],H*0.845,W*0.026,sans,s.acc,1,'600');
    text(CITYTXT[S.miastoProd][1],H*0.88,W*0.018,sans,s.ink,.7);
  }
  ctx.font=`${Math.round(W*0.011)}px ${sans}`; ctx.fillStyle=s.ink; ctx.globalAlpha=.45; ctx.textAlign='center';
  const src=P==='niebo'?'Gwiazdy: HYG Database (CC BY-SA), gwiazdozbiory: Stellarium (CC BY-SA)':'Dane: GUGiK (BDOT10k, NMT)';
  ctx.fillText(CFG.portal+'  ·  '+src,W/2,H*0.965); ctx.globalAlpha=1;
}

/* ---------- UI ---------- */
let cv, ctx, wall=false;
function sizeRatio(){return S.format==='30x40'?40/30:70/50;}
function render(){
  if(!MAP)return; const r=sizeRatio(); const box=$('#zp-stage'); const bw=box.clientWidth-24, bh=Math.min(window.innerHeight*0.78,900);
  let w=Math.min(bw*(wall?0.45:1), (wall?bh*0.62:bh)/r); if(S.produkt==='duet') w=Math.min(bw*(wall?0.7:1), bh*1.4/r*1.0);
  const h=S.produkt==='duet'? w/1.4*r*0.72 : w*r; const dpr=window.devicePixelRatio||1;
  cv.width=Math.round(w*dpr); cv.height=Math.round(h*dpr); cv.style.width=w+'px'; cv.style.height=h+'px';
  ctx.setTransform(1,0,0,1,0,0); poster(ctx,cv.width,cv.height);
  const fr=$('#zp-frame'); fr.className='zp-frame '+(S.rama==='rama'?('rama-'+S.ramaKolor):'bez');
  $('#zp-stage').classList.toggle('wall',wall);
  price(); $('#zp-wallnote').textContent=wall?`Format ${S.format.replace('x','×')} cm na ścianie`:'';
}
function price(){
  const tab=S.produkt==='duet'?DUET:PRICE; const p=tab[S.format][S.rama]*S.ilosc; const sh=SHIP[$('#zp-dost')?.value||'paczkomat'];
  $('#zp-cena').textContent=p+' zł'; $('#zp-razem').textContent=(p+sh)+' zł';
  $('#zp-cena-opis').textContent=`${S.produkt==='duet'?'2 plakaty':'plakat'} ${S.format.replace('x','×')} cm${S.rama==='rama'?', w ramie ('+S.ramaKolor+')':''}${S.ilosc>1?' × '+S.ilosc:''}`;
}
function bind(id,key,fn){const el=$(id);if(!el)return;el.value=S[key];el.addEventListener('input',()=>{S[key]=el.type==='number'?+el.value:el.value;fn&&fn();render();});}
function showPanels(){document.querySelectorAll('[data-for]').forEach(el=>{el.style.display=el.dataset.for.split(' ').includes(S.produkt)?'':'none';});
  document.querySelectorAll('.zp-tab').forEach(b=>b.classList.toggle('on',b.dataset.p===S.produkt));}
function setTown(){const c=townXY(S.miasto);S.E=c[0];S.N=c[1];}
function canvasClick(ev){if(S.produkt!=='mapa'&&S.produkt!=='duet')return;
  const rect=cv.getBoundingClientRect(),W=cv.width,H=cv.height,dpr=W/rect.width;const x=(ev.clientX-rect.left)*dpr,y=(ev.clientY-rect.top)*dpr;
  const m=W*0.08; let mx,my,mw,mh;
  if(S.produkt==='mapa'){mx=m;my=m;mw=W-2*m;mh=H*0.70-m;} else {mx=m*0.6;my=m;mw=W/2-m*1.2;mh=H*0.62;}
  if(x<mx||x>mx+mw||y<my||y>my+mh)return; const scale=mw/S.zoom;
  S.E+= (x-(mx+mw/2))/scale; S.N-= (y-(my+mh/2))/scale; render();}
async function printPreview(){
  const r=sizeRatio(); const W=S.format==='30x40'?1772:2953; const H=S.produkt==='duet'?Math.round(W/1.4*r*0.72):Math.round(W*r);
  const c=document.createElement('canvas');c.width=W;c.height=H;poster(c.getContext('2d'),W,H);
  const url=c.toDataURL('image/jpeg',0.92); const ov=$('#zp-print'); ov.querySelector('img').src=url; ov.style.display='flex';
  ov.querySelector('.zp-print-info').textContent=`Plik do druku: ${W}×${H} px (150 dpi w formacie ${S.format.replace('x','×')} cm). Powiększ, żeby zobaczyć szczegóły.`;
  return c;
}
async function downloadPrint(){
  const c=await printPreview(); const blob=await new Promise(r=>c.toBlob(r,'image/jpeg',0.92));
  try{const d=window.claude&&await window.claude.use('downloads'); if(d){await d.save({filename:'podglad-plakatu.jpg',data:blob});return;}}catch(e){}
  try{const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='podglad-plakatu.jpg';a.click();}catch(e){}
}
function orderText(){
  const lonlat=inv(S.E,S.N);
  const lines=[`Produkt: ${({mapa:'Mapa „Tu mieszkamy”',niebo:'Niebo w ważnym dniu',duet:'Duet: mapa + niebo',miasto:'Miasto z lotu ptaka'})[S.produkt]}`,
   `Format: ${S.format} cm, ${S.rama==='rama'?'w ramie ('+S.ramaKolor+')':'sam plakat'}, ilość: ${S.ilosc}`,
   `Miasto: ${S.miasto}; punkt: ${lonlat[1].toFixed(5)}, ${lonlat[0].toFixed(5)}; skala: ${S.zoom} m`];
  if(S.produkt==='mapa'||S.produkt==='duet') lines.push(`Napis: ${S.tytul} / ${S.podtytul}; styl: ${STYLES[S.styl].name}; znacznik: ${S.pin}`);
  if(S.produkt==='niebo'||S.produkt==='duet') lines.push(`Niebo: ${S.data} ${S.godz}; napis: ${S.skyTytul} / ${S.skyPodtytul}; styl: ${STYLES[S.skyStyl].name}`);
  if(S.produkt==='miasto') lines.push(`Mapa miasta: ${CITYTXT[S.miastoProd][0]}`);
  return lines.join('\n');
}
async function order(ev){
  ev.preventDefault(); const f=ev.target; const fd=Object.fromEntries(new FormData(f).entries());
  if(!fd.zgoda){$('#zp-msg').textContent='Zaznacz zgodę na przetwarzanie danych do realizacji zamówienia.';return;}
  const code='ZP-'+Date.now().toString(36).toUpperCase().slice(-6);
  const tab=S.produkt==='duet'?DUET:PRICE; const total=tab[S.format][S.rama]*S.ilosc+SHIP[fd.dostawa];
  const payload={kod:code,konfiguracja:JSON.parse(JSON.stringify(S)),opis:orderText(),klient:fd,cena:total};
  if(CFG.orderUrl){
    try{const r=await fetch(CFG.orderUrl,{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':CFG.nonce},body:JSON.stringify(payload)});
      if(!r.ok)throw new Error(r.status); $('#zp-msg').innerHTML=`<b>Dziękujemy! Zamówienie ${code} przyjęte.</b> W ciągu 24 godzin wyślemy na ${fd.email} projekt do akceptacji i link do płatności.`; f.reset(); return;
    }catch(e){ $('#zp-msg').textContent='Nie udało się wysłać zamówienia. Skopiuj jego treść i wyślij na '+CFG.kontakt+'.'; }
  }
  const body=`Zamówienie ${code}\n${orderText()}\nCena: ${total} zł (z dostawą)\nKlient: ${fd.imie}, ${fd.email}, ${fd.telefon}\nDostawa: ${fd.dostawa} ${fd.adres||''}\nUwagi: ${fd.uwagi||''}`;
  $('#zp-msg').innerHTML=`<b>To jest wersja pokazowa: zamówienie nie zostało wysłane.</b> Na portalu trafi prosto do redakcji. Treść zamówienia ${code}:<pre>${body.replace(/</g,'&lt;')}</pre><button type="button" id="zp-copy">Kopiuj treść</button> i wyślij na <b>${CFG.kontakt}</b>`;
  $('#zp-copy').onclick=async()=>{try{await navigator.clipboard.writeText(body);$('#zp-copy').textContent='Skopiowano';}catch(e){}};
}
async function init(){
  cv=$('#zp-canvas'); ctx=cv.getContext('2d');
  $('#zp-load').textContent='Wczytuję mapy powiatu i katalog gwiazd…';
  await load(); $('#zp-load').textContent='';
  const ts=$('#zp-miasto'); Object.keys(SKY.towns).forEach(t=>{const o=document.createElement('option');o.textContent=t;ts.appendChild(o);});
  const ss=$('#zp-styl'),sk=$('#zp-skystyl'); Object.entries(STYLES).forEach(([k,v])=>{ss.add(new Option(v.name,k));sk.add(new Option(v.name,k));});
  setTown();
  bind('#zp-miasto','miasto',()=>{setTown(); if(S.produkt!=='niebo'&&!S._podtytulTouched) {S.podtytul=S.miasto+' · od 2021 roku'; $('#zp-podtytul').value=S.podtytul;}});
  bind('#zp-zoom','zoom'); bind('#zp-styl','styl'); bind('#zp-tytul','tytul'); bind('#zp-podtytul','podtytul',()=>S._podtytulTouched=true); bind('#zp-pin','pin');
  bind('#zp-data','data'); bind('#zp-godz','godz'); bind('#zp-skytytul','skyTytul'); bind('#zp-skypodtytul','skyPodtytul'); bind('#zp-skystyl','skyStyl');
  bind('#zp-format','format'); bind('#zp-rama','rama'); bind('#zp-ramakolor','ramaKolor'); bind('#zp-ilosc','ilosc'); bind('#zp-miastoprod','miastoProd');
  $('#zp-dost').addEventListener('input',price);
  document.querySelectorAll('.zp-tab').forEach(b=>b.onclick=()=>{S.produkt=b.dataset.p; if(S.produkt==='miasto'&&!CITY[S.miasto]){S.miasto='Kobyłka';$('#zp-miasto').value='Kobyłka';setTown();} showPanels(); render();});
  cv.addEventListener('click',canvasClick);
  $('#zp-wall').onclick=()=>{wall=!wall;$('#zp-wall').textContent=wall?'Pokaż sam plakat':'Zobacz na ścianie';render();};
  $('#zp-printbtn').onclick=printPreview; $('#zp-dl').onclick=downloadPrint;
  $('#zp-print').onclick=e=>{if(e.target.id==='zp-print'||e.target.classList.contains('zp-x'))$('#zp-print').style.display='none';};
  $('#zp-form').addEventListener('submit',order);
  window.addEventListener('resize',render); showPanels(); render();
  (async()=>{try{const d=window.claude&&await window.claude.use('downloads'); if(!d && !document.createElement('a').download===undefined) $('#zp-dl').style.display='none';}catch(e){}})();
}
document.addEventListener('DOMContentLoaded',init);
})();
