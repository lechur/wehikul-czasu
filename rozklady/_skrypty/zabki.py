import pdfplumber,re,json,glob,os
TIME=re.compile(r'^\d\d:\d\d$')
out={}
for f in sorted(glob.glob('/tmp/zab/*.pdf')):
    ln='Z'+os.path.basename(f)[1:-4].upper()
    res={}
    for pi,p in enumerate(pdfplumber.open(f).pages):
        ws=p.extract_words(x_tolerance=1.5);txt=p.extract_text() or ''
        kp=[w for w in ws if w['text'] in('KP','kp')]
        if not kp: print(ln,pi,'brak KP');continue
        kp=kp[0]
        hdr=[w for w in ws if abs(w['top']-kp['top'])<3 and w['x0']>kp['x1'] and re.match(r'^Z\d',w['text'])]
        cols=[(w['x0']+w['x1'])/2 for w in hdr]
        rows={}
        for w in ws:
            if w['top']>kp['top']+3: rows.setdefault(round(w['top']/2.5),[]).append(w)
        stops=[]
        for k in sorted(rows):
            r=sorted(rows[k],key=lambda w:w['x0'])
            name=' '.join(w['text'] for w in r if w['x1']<kp['x0']-1)
            tt=[None]*len(cols);any_=False
            for w in r:
                if w['x0']>kp['x1'] and (TIME.match(w['text']) or w['text']=='~'):
                    any_=True
                    if TIME.match(w['text']):
                        c=min(range(len(cols)),key=lambda i:abs(cols[i]-(w['x0']+w['x1'])/2));tt[c]=w['text']
            if name and any_: stops.append((name,tt))
        if not stops: continue
        if re.search(r'robocz',txt): groups={'robocze':list(range(len(cols)))}
        else:
            first=[next((s[1][c] for s in stops if s[1][c]),None) for c in range(len(cols))]
            cut=next((c for c in range(1,len(cols)) if first[c] and first[c-1] and first[c]<first[c-1]),len(cols))
            groups={'sobota':list(range(cut)),'niedziela':list(range(cut,len(cols)))}
        for d,cs in groups.items():
            res[d]={'stops':[(n,[t[c] for c in cs]) for n,t in stops],'n':len(cs)}
        print(ln,pi,{d:len(c) for d,c in groups.items()},len(stops),'przyst.')
    m=re.search(r'obowiązuje od ([\d\.]+)',open(f,'rb').read().decode('latin1','ignore'))
    out[ln]=res
json.dump(out,open('/tmp/zab/zabki.json','w'),ensure_ascii=False)
