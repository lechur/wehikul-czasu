"""Pobiera oficjalne kalendarze wywozu odpadów (Google Calendar iCal, Urząd Miasta Kobyłka) i zapisuje terminy.json."""
import json, re, urllib.request, datetime as dt
d = json.load(open('dane.json', encoding='utf-8'))
today = dt.date.today(); od = today - dt.timedelta(days=3); do = today + dt.timedelta(days=150)
out = {'pobrano': dt.datetime.utcnow().isoformat(timespec='minutes') + 'Z', 'zrodlo': d['odpady'].get('zrodlo'), 'rejony': {}}
def unfold(t): return re.sub(r'\r?\n[ \t]', '', t)
for r in d['odpady']['rejony']:
    url = r.get('ical')
    if not url: continue
    try:
        txt = unfold(urllib.request.urlopen(urllib.request.Request(url, headers={'User-Agent': 'aplikacja-kobylka'}), timeout=60).read().decode('utf-8', 'replace'))
    except Exception as e:
        print('BŁĄD', r['nazwa'], e); continue
    items = []
    for ev in txt.split('BEGIN:VEVENT')[1:]:
        m = re.search(r'\nDTSTART[^:]*:(\d{8})', '\n' + ev); s = re.search(r'\nSUMMARY[^:]*:(.*)', '\n' + ev)
        if not m: continue
        day = dt.datetime.strptime(m.group(1), '%Y%m%d').date()
        if od <= day <= do:
            items.append({'data': day.isoformat(), 'frakcja': (s.group(1).strip().replace('\\,', ',') if s else 'odbiór odpadów')})
    items.sort(key=lambda x: x['data']); out['rejony'][r['nazwa']] = items
    print(r['nazwa'], len(items))
json.dump(out, open('terminy.json', 'w', encoding='utf-8'), ensure_ascii=False, indent=0)
