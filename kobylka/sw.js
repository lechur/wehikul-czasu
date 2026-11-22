const C='kobylka-v44';const CORE=['./','index.html','sky.json','wydarzenia.json','mapa.json','dane.json','terminy.json','ulice_geo.json','firmy.json','radni.json','radni_aktywnosc.json','moduly.js?v=37','komunikacja.json','budzet.json','bo.json','manifest.webmanifest','icon-192.png','icon-512.png','apple-touch-icon.png','qr.png','zwierzeta.html','img/wzrost.jpg','img/rzezba.jpg','img/budynki.jpg','img/ulice.jpg','img/wtedy_2001.jpg','img/dzis_2024.jpg'];
self.addEventListener('install',e=>{e.waitUntil(caches.open(C).then(c=>Promise.all(CORE.map(u=>c.add(u).catch(()=>null)))));self.skipWaiting();});
self.addEventListener('activate',e=>{e.waitUntil(caches.keys().then(k=>Promise.all(k.filter(x=>x!==C).map(x=>caches.delete(x)))));self.clients.claim();});
function timeout(ms){return new Promise((_,rej)=>setTimeout(()=>rej(new Error('timeout')),ms));}
self.addEventListener('fetch',e=>{const u=new URL(e.request.url);if(e.request.method!=='GET'||u.origin!==location.origin)return;
 const net=fetch(e.request).then(r=>{if(r.ok){const cp=r.clone();caches.open(C).then(c=>c.put(e.request,cp));}return r;});
 e.respondWith(Promise.race([net,timeout(4000)]).catch(()=>caches.match(e.request,{ignoreSearch:true}).then(r=>r||net).catch(()=>caches.match('index.html'))));});
