const C='zyciepw-app-v2';const CORE=['./','index.html','gminy.json','manifest.webmanifest','icon-192.png','icon-512.png','apple-touch-icon.png','og.png'];
self.addEventListener('install',e=>{e.waitUntil(caches.open(C).then(c=>Promise.all(CORE.map(u=>c.add(u).catch(()=>null)))));self.skipWaiting();});
self.addEventListener('activate',e=>{e.waitUntil(caches.keys().then(k=>Promise.all(k.filter(x=>x.indexOf('zyciepw-app-')===0&&x!==C&&x!==C+'-img').map(x=>caches.delete(x)))));self.clients.claim();});
function timeout(ms){return new Promise((_,rej)=>setTimeout(()=>rej(new Error('timeout')),ms));}
const SC=new URL(self.registration.scope).pathname;
self.addEventListener('fetch',e=>{const u=new URL(e.request.url);if(e.request.method!=='GET')return;
 if(u.origin===location.origin&&u.pathname.startsWith(SC)){ // pliki aplikacji: najpierw siec, potem pamiec
  const net=fetch(e.request).then(r=>{if(r.ok){const cp=r.clone();caches.open(C).then(c=>c.put(e.request,cp));}return r;});
  e.respondWith(Promise.race([net,timeout(4000)]).catch(()=>caches.match(e.request,{ignoreSearch:true}).then(r=>r||net).catch(()=>caches.match('index.html'))));return;}
 if(u.hostname.endsWith('zyciepw.pl')&&e.request.destination==='image'&&u.pathname.indexOf('/wp-content/uploads/')===0){ // zdjecia artykulow: z pamieci, gdy brak sieci
  e.respondWith(caches.open(C+'-img').then(c=>c.match(e.request).then(h=>h||fetch(e.request).then(r=>{if(r.ok||r.type==='opaque')c.put(e.request,r.clone());return r}))).catch(()=>fetch(e.request)));}
});
