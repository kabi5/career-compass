const CACHE='career-compass-public-v1';
const PUBLIC_FILES=['offline.html','assets/css/style.css','assets/js/app.js','assets/icons/compass.svg'];
self.addEventListener('install',event=>{event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(PUBLIC_FILES)).then(()=>self.skipWaiting()));});
self.addEventListener('activate',event=>{event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k.startsWith('career-compass-public-')&&k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim()));});
self.addEventListener('fetch',event=>{
 const url=new URL(event.request.url);const scope=new URL(self.registration.scope);
 if(event.request.method!=='GET'||url.origin!==scope.origin)return;
 const relative=url.pathname.slice(scope.pathname.length);
 // Exact public allowlist: never cache PHP, APIs, authentication, grades or PDFs.
 if(PUBLIC_FILES.includes(relative)&&!url.search){event.respondWith(caches.match(event.request).then(cached=>cached||fetch(event.request)));return;}
 if(event.request.mode==='navigate'){event.respondWith(fetch(event.request).catch(()=>caches.match(new URL('offline.html',scope).href)));}
});
