import pdfplumber, re, json, datetime as dt, sys
PDF=sys.argv[1]
pdf=pdfplumber.open(PDF)
TIME=re.compile(r'^\d\d:\d\d$')
RZ={'I':1,'II':2,'III':3,'IV':4,'V':5,'VI':6,'VII':7,'VIII':8,'IX':9,'X':10,'XI':11,'XII':12}
Y=2026
W0,W1=dt.date(2026,11,16),dt.date(2026,12,12)
def d(s):
    a,m=s.split('.');return dt.date(Y,RZ[m],int(a))
def dates(s):
    out=set()
    for part in re.split(r'[;,]\s*',s.strip()):
        part=part.strip()
        if not part: continue
        m=re.match(r'^(\d+)(?:\.([IVX]+))?-(\d+)\.([IVX]+)$',part)
        if m:
            a=dt.date(Y,RZ[m.group(2) or m.group(4)],int(m.group(1)));b=dt.date(Y,RZ[m.group(4)],int(m.group(3)))
            x=a
            while x<=b: out.add(x);x+=dt.timedelta(1)
            continue
        m=re.match(r'^(\d+)\.([IVX]+)$',part)
        if m: out.add(d(part))
    return out
def kod_ok(k,x):
    wd=x.isoweekday()
    if k is None: return True
    if k in('A','D'): return wd<=5
    if k=='C': return wd>=6
    if k=='B': return wd<=5 or wd==7
    if '-' in k: a,b=k.split('-');return int(a)<=wd<=int(b)
    if k.isdigit(): return wd==int(k)
    return True
def runs(base,note,x):
    if not note: return kod_ok(base,x)
    t=note
    k=base
    m=re.search(r'w \((\w)\)(?:-\((\d)\))?',t)
    if m: k=m.group(1)+('-'+m.group(2) if m.group(2) else '')
    exc=set();add=set()
    m=re.search(r'oprócz ([\d\.,;\-IVX ]+)',t)
    if m: exc=dates(m.group(1))
    m=re.search(r'oraz ([\d\.,;\-IVX ]+)',t)
    if m: add=dates(m.group(1))
    od=re.search(r'od (\d+\.[IVX]+)',t);do=re.search(r'do (\d+\.[IVX]+)',t)
    m=re.match(r'kursuje ((?:\d[\d\.,;\-IVX ]*))(?: w|$| oraz| oprócz)',t)
    lst=dates(m.group(1)) if m else None
    if x in exc: return False
    if x in add: return True
    if lst is not None: return x in lst and kod_ok(k,x)
    if od and x<d(od.group(1)): return False
    if do and x>d(do.group(1)): return False
    return kod_ok(k,x)
def typ(x):
    w=x.isoweekday();return 'robocze' if w<=5 else ('sobota' if w==6 else 'niedziela')
okno=[W0+dt.timedelta(i) for i in range((W1-W0).days+1)]
wynik={}
log=[]
for pi,p in enumerate(pdf.pages):
    txt=p.extract_text()
    mh=re.search(r'^\s*([A-ZŁŚŻ][^\n]*?) - ([A-ZŁŚŻ][\wąćęłńóśźżŁŚŻ\. ]*?)(?:\s{2,}|\s+\d|$)',txt,re.M)
    A,B=mh.group(1).strip(),mh.group(2).strip()
    kier='do_warszawy' if B.startswith('Warszawa') else 'z_warszawy' if A.startswith('Warszawa') else 'kier_'+B
    notes={}
    for m in re.finditer(r'\[(\d)\]\s+(kursuje.*?)(?=\s\[\d\]\s|\s\([A-Z0-9]\)\s+kursuje|\n|$)',txt):
        notes[m.group(1)]=m.group(2).strip()
    ws=p.extract_words()
    from collections import Counter
    MX=Counter(round(w['x0']) for w in ws if w['text']=='o').most_common(1)[0][0]
    rows={}
    for w in ws: rows.setdefault(round(w['top']),[]).append(w)
    tops=sorted(rows)
    numrow=[t for t in tops if t<40 and sum(1 for w in rows[t] if re.match(r'^\d{5}',w['text']))>=1 and t>28][0]
    cols=[ (w['x0']+w['x1'])/2 for w in rows[numrow] if re.match(r'^\d{5}',w['text'])]
    nums=[w['text'] for w in rows[numrow] if re.match(r'^\d{5}',w['text'])]
    def col(x):
        b=min(range(len(cols)),key=lambda i:abs(cols[i]-x));return b if abs(cols[b]-x)<28 else None
    codes=[[None,None] for _ in cols]
    for t in tops:
        if 40<t<55:
            for w in rows[t]:
                c=col(w['x0']+12)
                m=re.match(r'^\((\w)\)$',w['text']);n=re.match(r'^\[(\d)\]$',w['text'])
                if c is None: continue
                if m: codes[c][0]=m.group(1)
                if n:
                    c2=col(w['x0']-4) if codes[col(w['x0']-4) or 0][0] is not None else c
                    codes[c2 if c2 is not None else c][1]=n.group(1)
    def dalej(label_word):
        lt=[t for t in tops if any(w['text']==label_word for w in rows[t]) and any(w['text']=='stacji' for w in rows[t])]
        res={}
        if not lt: return res
        for t in tops:
            if lt[0]-3<=t<=lt[0]+3:
                for w in sorted(rows[t],key=lambda w:w['x0']):
                    if w['x0']<160: continue
                    c=col(w['x0']+10)
                    if c is not None: res[c]=(res.get(c,'')+' '+w['text']).strip()
        return res
    DOS=dalej('Do');ODS=dalej('Od')
    # station rows
    st=[];tops_st=[]
    for t in tops:
        r=sorted(rows[t],key=lambda w:w['x0'])
        mk=[w for w in r if w['text'] in('o','p') and abs(w['x0']-MX)<8]
        if not mk: continue
        name=' '.join(w['text'] for w in r if 45<w['x0']<140 and not re.match(r'^[\d,]+$',w['text']))
        times={}
        for w in r:
            if w['x0']>160:
                c=col((w['x0']+w['x1'])/2)
                if c is not None: times[c]=w['text']
        st.append((name,mk[0]['text'],times));tops_st.append(t)
    nazwy=[]
    for t in tops:
        r=rows[t]
        if any(w['text'] in('o','p') and abs(w['x0']-MX)<8 for w in r): continue
        nm=' '.join(w['text'] for w in sorted(r,key=lambda w:w['x0']) if 45<w['x0']<140 and not re.match(r'^[\d,]+$',w['text']))
        if nm and t>55: nazwy.append((t,nm))
    st2=[]
    for (name,mk,times),t in zip(st,tops_st):
        if not name:
            c=[(abs(tt-t),nm) for tt,nm in nazwy if abs(tt-t)<=10]
            name=min(c)[1] if c else '?'
        st2.append((name,mk,times))
    for c,num in enumerate(nums):
        seq=[(n,m,ti[c]) for n,m,ti in st2 if c in ti]
        tims=[(n,m,v) for n,m,v in seq if TIME.match(v)]
        if not tims: continue
        cel=DOS.get(c) or tims[-1][0]; start=ODS.get(c) or tims[0][0]
        for stacja in sorted(set(n for n,m,v in seq if TIME.match(v))):
            o=[v for n,m,v in seq if n==stacja and m=='o' and TIME.match(v)]
            if not o: continue
            base,nt=codes[c]
            note=notes.get(nt) if nt else None
            dni={};run={}
            for x in okno:
                dni.setdefault(typ(x),[0,0]);dni[typ(x)][1]+=1
                if runs(base,note,x): dni[typ(x)][0]+=1;run.setdefault(typ(x),[]).append(x.isoformat())
            for ty,(a,b) in dni.items():
                if not a: continue
                bucket=wynik.setdefault(stacja,{}).setdefault(kier,{}).setdefault(ty,{})
                key=(o[0],num.split('/')[0])
                e=bucket.setdefault(key,{'g':o[0],'do':cel,'z':start,'nr':num,'_d':set(),'_n':b})
                e['_d'].update(run[ty])
            log.append((pi,num,stacja,o[0],base,nt,note,{k:v for k,v in dni.items()},start,cel))
out={}
for s,kk in wynik.items():
    for k,tt in kk.items():
        for ty,dd in tt.items():
            L=[]
            for e in sorted(dd.values(),key=lambda r:r['g']):
                d_=sorted(e.pop('_d'));n=e.pop('_n')
                if len(d_)<n: e['daty']=d_
                L.append(e)
            out.setdefault(s,{}).setdefault(k,{})[ty]=L
json.dump(out,open(sys.argv[2],'w'),ensure_ascii=False)
print({s:{k:{t:len(v) for t,v in tt.items()} for k,tt in kk.items()} for s,kk in out.items()})
