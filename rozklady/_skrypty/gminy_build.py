"""Buduje rozklady/<gmina>.json dla wszystkich gmin powiatu wołomińskiego.
Wejścia: /tmp/ztm2.json (scraping ZTM), /tmp/osm.json (przystanki OSM), PDF-y sparsowane do JSON."""
import json, re, math, unicodedata, os
G = json.load(open('/home/claude/wc/wtyczki/zycie-dziki/api/gminy.json'))
Z = json.load(open('/tmp/ztm2.json'))
ZTM, C = Z['ztm'], Z['coords']
OSM = json.load(open('/tmp/osm.json'))
POW = json.load(open('/tmp/pow/powiat.json'))
RAD = json.load(open('/tmp/rad/radzymin.json'))
ZAB = json.load(open('/tmp/zab/zabki.json'))
RAIL = {'R6': json.load(open('/tmp/km/r6all.json')), 'R92': json.load(open('/tmp/km/r92.json')), 'R61': json.load(open('/tmp/km/r61.json'))}
OUT = '/home/claude/wc/rozklady'
os.makedirs(OUT, exist_ok=True)
DNI = ('robocze', 'sobota', 'niedziela')

def pip(lat, lon, poly):
    ins = False
    for ring in (poly if isinstance(poly[0][0], list) else [poly]):
        n = len(ring)
        for i in range(n):
            a, b = ring[i], ring[(i + 1) % n]
            if (a[1] > lon) != (b[1] > lon) and lat < (b[0] - a[0]) * (lon - a[1]) / (b[1] - a[1] + 1e-12) + a[0]:
                ins = not ins
    return ins

def gmina_of(lat, lon):
    if lat is None: return None
    for k, g in G.items():
        if pip(lat, lon, g['p']): return k
    return None

def norm(s):
    s = unicodedata.normalize('NFKD', s.lower()).encode('ascii', 'ignore').decode()
    s = re.sub(r'\b(ul|al|pl|os)\.?\s', '', s)
    return re.sub(r'[^a-z0-9]+', ' ', s).strip()

def dist(a, b):
    return math.hypot((a[0] - b[0]) * 111000, (a[1] - b[1]) * 68000)

# --- stacje kolejowe (współrzędne z OSM)
OSM_ST = {}
for n, typ, lat, lon, ref, x in OSM:
    if typ in ('station', 'halt') and n:
        OSM_ST.setdefault(norm(n), (lat, lon))
def st_coord(name):
    k = norm(name.replace('Maz.', 'Mazowiecka'))
    return OSM_ST.get(k) or OSM_ST.get(norm(name)) or next((v for kk, v in OSM_ST.items() if kk.startswith(norm(name.split()[0])) and len(name.split()) == 1), None)

STAC_GM = {}
for R_ in RAIL.values():
    for nm in R_:
        c = st_coord(nm)
        g = gmina_of(*c) if c else None
        if g: STAC_GM[nm] = g
print('stacje->gminy', STAC_GM)
# --- dopasowanie przystanków spoza ZTM do OSM po nazwie
OSM_BUS = {}
for n, typ, lat, lon, ref, x in OSM:
    if typ not in ('station', 'halt') and n:
        OSM_BUS.setdefault(norm(re.sub(r'\s\d\d$', '', n)), []).append((lat, lon))
def geo(name, gm):
    cands = []
    parts = [name] + re.split(r'\s*[,/]\s*', name)
    for p in parts[::-1]:
        p = re.sub(r'\s(\d\d|nż|NŻ)$', '', p.strip())
        p = re.sub(r'\s\d\d$', '', p)
        for k in {norm(p)}:
            if len(k) < 3: continue
            for c in OSM_BUS.get(k, []):
                if gm is None or gmina_of(*c) == gm: cands.append(c)
        if cands: break
    if not cands: return None
    # środek skupiska (dwa słupki po obu stronach ulicy)
    if max(dist(cands[0], c) for c in cands) > 600: return None
    return (round(sum(c[0] for c in cands) / len(cands), 5), round(sum(c[1] for c in cands) / len(cands), 5))

def hm(m):
    m %= 1440
    return '%02d:%02d' % (m // 60, m % 60)
def tm(s):
    a, b = s.split(':'); return int(a) * 60 + int(b)

linie = {k: [] for k in G}

# --- ZTM
for ln, l in ZTM.items():
    if l.get('err') or not l.get('kierunki'): continue
    if ln.startswith('N'): continue
    gms = set(); kier = []
    for k in l['kierunki']:
        prz = []
        for i, p in enumerate(k['przystanki']):
            c = C.get(p.get('ref') or '')
            g = gmina_of(*c) if c else None
            if g: gms.add(g)
            d = {'nazwa': p['nazwa'], 'slupek': p.get('slupek', '')}
            if c: d['lat'], d['lon'] = c
            if g: d['gm'] = g
            prz.append(d)
        czasy = {}
        for i, v in (k.get('czasy') or {}).items():
            vv = {d: t for d, t in v.items() if t is not None}
            if vv: czasy[str(i)] = vv
        kier.append({'do': k['do'], 'przystanki': prz, 'minuty': k['minuty'], 'odjazdy': k['odjazdy'], 'czasy': czasy})
    if not gms: continue
    p0 = l['kierunki'][0]['przystanki']
    L = {'id': ln, 'grupa': 'ZTM Warszawa', 'opis': p0[0]['nazwa'] + ' – ' + p0[-1]['nazwa'],
         'url': 'https://www.wtp.waw.pl/rozklady-jazdy/?wtp_md=3&wtp_ln=' + ln, 'kierunki': kier}
    for g in gms: linie[g].append(L)

# --- linie z rozkładów PDF (czas dla każdego przystanku)
def kier_z_tabeli(stops_by_day, do, gm_default=None, gm_rows=None):
    """stops_by_day: {dzien: [(nazwa,[czasy kursów])]} – ta sama kolejność przystanków."""
    base = None
    for d in DNI:
        if d in stops_by_day: base = stops_by_day[d]; break
    prz = []
    for i, (n, t) in enumerate(base):
        gm = (gm_rows[i] if gm_rows else gm_default)
        d = {'nazwa': n, 'slupek': ''}
        c = geo(n, gm if gm in G else None)
        if c: d['lat'], d['lon'] = c
        if gm in G: d['gm'] = gm
        prz.append(d)
    czasy = {}
    for d, rows in stops_by_day.items():
        if len(rows) != len(base): continue
        for i, (n, t) in enumerate(rows):
            v = sorted(x for x in t if x)
            czasy.setdefault(str(i), {})[d] = v
    for i in range(len(base)):
        for d in DNI: czasy.setdefault(str(i), {}).setdefault(d, [])
    odj = {d: czasy['0'][d] for d in DNI}
    return {'do': do, 'przystanki': prz, 'odjazdy': odj, 'czasy': czasy}

# powiat
TYT = ['Radzymin – Klembów – Poświętne', 'Radzymin – Wola Rasztowska – Tłuszcz', 'Tłuszcz – Międzyleś – Strachówka', 'Tłuszcz – Strachówka – Strachów',
       'Wołomin – Zabraniec – Poświętne', 'Wołomin – Tłuszcz – Jadów', 'Wołomin – Radzymin – Nowe Załubice', 'Wołomin – Wola Rasztowska – Dąbrówka']
for li in range(8):
    tam, pow_ = POW[2 * li], POW[2 * li + 1]
    kier = []; gms = set()
    for t in (tam, pow_):
        rows = t['rows']
        if rows[0]['t'][0] is not None and rows[-1]['t'][0] is not None and rows[0]['t'][0] > rows[-1]['t'][0]: rows = rows[::-1]
        rows = [dict(r, nazwa=re.sub(r'\s+\d{1,3}$', '', r['nazwa']).strip()) for r in rows]
        st = [(r['nazwa'], [hm(x) if x is not None else None for x in r['t']]) for r in rows]
        gm_rows = [r['gm'] for r in rows]
        gms |= {g for g in gm_rows if g in G}
        kier.append(kier_z_tabeli({'robocze': st}, rows[-1]['nazwa'], gm_rows=gm_rows))
    L = {'id': 'P' + str(li + 1), 'grupa': 'Linie powiatowe', 'opis': TYT[li] + ' (dni robocze)', 'wazny_od': tam['wazny_od'],
         'url': 'https://samorzad.gov.pl/web/powiat-wolominski/rozklad-jazdy2', 'kierunki': kier}
    for g in gms: linie[g].append(L)

# Radzymin – linie okrężne: jeden kierunek (trasa)
for ln, dd in RAD.items():
    sb = {d: [(re.sub(r'\s+', ' ', n), t) for n, t in v['stops']] for d, v in dd.items()}
    base = next(sb[d] for d in DNI if d in sb)
    for d in list(sb):
        if len(sb[d]) != len(base): sb[d] = None
    sb = {d: v for d, v in sb.items() if v}
    k = kier_z_tabeli(sb, base[-1][0], gm_default='radzymin')
    for p in k['przystanki']:
        if 'lat' in p:
            g = gmina_of(p['lat'], p['lon'])
            if g: p['gm'] = g
    dni = '/'.join({'robocze': 'pn–pt', 'sobota': 'sob', 'niedziela': 'nd'}[d] for d in DNI if d in dd)
    L = {'id': ln, 'grupa': 'Komunikacja gminna Radzymin', 'opis': base[0][0] + ' – ' + base[-1][0] + ' (' + dni + ')',
         'wazny_od': next(iter(dd.values()))['wazny_od'], 'url': 'https://radzymin.pl/1246/aktualne-rozklady-jazdy-gminnej-komunikacji.html', 'kierunki': [k]}
    linie['radzymin'].append(L)

# Ząbki
for ln, dd in ZAB.items():
    sb = {d: v['stops'] for d, v in dd.items()}
    base = sb['robocze']
    for d in list(sb):
        if [n for n, t in sb[d]] != [n for n, t in base]: sb[d] = None
    sb = {d: v for d, v in sb.items() if v}
    clean = lambda n: re.sub(r'\s+', ' ', n.split('/')[-1]).strip() if '/' in n else n
    sb2 = {d: [(clean(n), t) for n, t in v] for d, v in sb.items()}
    k = kier_z_tabeli(sb2, clean(base[-1][0]), gm_default='zabki')
    L = {'id': ln.replace('Z', 'Ząbki-', 1), 'grupa': 'Ząbkowska Komunikacja Miejska', 'opis': clean(base[0][0]) + ' – ' + clean(base[-1][0]),
         'url': 'https://www.zabki.pl/komunikacja', 'kierunki': [k]}
    linie['zabki'].append(L)

# --- pociągi
def poc_dla(gm):
    out = {}
    for name, g in STAC_GM.items():
        if g != gm: continue
        st = {}
        for linia, R in RAIL.items():
            for kk, tt in (R.get(name) or {}).items():
                for d, L in tt.items():
                    st.setdefault(kk, {}).setdefault(d, []).extend([dict(x, linia=linia) for x in L])
        if st:
            for kk in st:
                for d in st[kk]: st[kk][d].sort(key=lambda x: x['g'])
            out[name] = st
    return out

# --- powiązanie przystanków z pobliską stacją (przesiadki)
def podepnij_stacje(lin, stacje):
    sc = {s: st_coord(s) for s in stacje}
    for L in lin:
        for k in L['kierunki']:
            k['stacje'] = []
            best = {}
            for i, p in enumerate(k['przystanki']):
                if 'lat' not in p: continue
                for s, c in sc.items():
                    if not c: continue
                    dd = dist((p['lat'], p['lon']), c)
                    if dd < 450 and (s not in best or dd < best[s][1]): best[s] = (i, dd)
            for i, p in enumerate(k['przystanki']):
                nn = norm(p['nazwa'])
                if not re.search(r'\b(pkp|dworzec|stacj)', nn): continue
                for s in stacje:
                    ns = norm(s.replace('Maz.', ''))
                    if s not in best and (ns in nn or len(stacje) == 1):
                        best[s] = (i, 150)
            k['stacje'] = [{'i': i, 'stacja': s, 'm': round(dd)} for s, (i, dd) in best.items()]
    return sc

INDEX = {}
for gm, g in G.items():
    lin = sorted(linie[gm], key=lambda L: ({'ZTM Warszawa': 0, 'Ząbkowska Komunikacja Miejska': 1, 'Komunikacja gminna Radzymin': 1, 'Linie powiatowe': 2}.get(L['grupa'], 3), len(L['id']), L['id']))
    poc = poc_dla(gm)
    sc = podepnij_stacje(lin, poc.keys())
    for L in lin:
        for k in L['kierunki']:
            for p in k['przystanki']:
                p['wg'] = 1 if p.get('gm') == gm else 0
    D = {'stan': '2026-11-15', 'gmina': gm, 'nazwa': g['n'], 'linie': lin,
         'zrodla': {'ZTM': 'https://www.wtp.waw.pl/rozklady-jazdy/', 'KM': 'https://www.mazowieckie.com.pl/pl/podstawowe-informacje/rozklad-jazdy-pociagow-km-wazny-w-dniach-25-x-12-xii-2026r',
                    'Powiat': 'https://samorzad.gov.pl/web/powiat-wolominski/rozklad-jazdy2'},
         'pociagi': {'wazny': '2 listopada – 12 grudnia 2026 (R6), 25 października – 12 grudnia 2026 (R61, R92)', 'zrodlo': 'https://www.mazowieckie.com.pl/pl/podstawowe-informacje/rozklad-jazdy-pociagow-km-wazny-w-dniach-25-x-12-xii-2026r',
                     'stacje': poc, 'wsp': {s: c for s, c in sc.items() if c}}}
    if gm == 'kobylka':
        kk = json.load(open('/home/claude/wc/kobylka/komunikacja.json'))
        D['bilety'] = kk.get('bilety', []); D['zrodlo'] = kk.get('zrodlo')
    if gm == 'zabki': D['bilety_info'] = 'Linie Ząbkowskiej Komunikacji Miejskiej – taryfa na zabki.pl/komunikacja.'
    json.dump(D, open(f'{OUT}/{gm}.json', 'w'), ensure_ascii=False, separators=(',', ':'))
    INDEX[gm] = {'n': g['n'], 'plik': f'https://lechur.github.io/wehikul-czasu/rozklady/{gm}.json', 'linie': len(lin), 'stacje': list(poc)}
    nwsp = sum(1 for L in lin for k in L['kierunki'] for p in k['przystanki'] if p.get('wg') and 'lat' in p)
    nw = sum(1 for L in lin for k in L['kierunki'] for p in k['przystanki'] if p.get('wg'))
    print(gm, len(lin), 'linii:', ' '.join(L['id'] for L in lin), '| stacje', list(poc), '| wsp', nwsp, '/', nw, '| podpięte', {s: c is not None for s, c in sc.items()})
INDEX['kobylka']['app'] = 'https://lechur.github.io/wehikul-czasu/kobylka/#kom'
json.dump(INDEX, open(f'{OUT}/index.json', 'w'), ensure_ascii=False, indent=1)
