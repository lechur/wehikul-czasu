import pdfplumber,re,json,glob,os
TIME=re.compile(r'^\d\d:\d\d$')
def parse(f):
    pdf=pdfplumber.open(f);stops=[];cols=None;lin=None;waz=''
    for p in pdf.pages:
        ws=p.extract_words(keep_blank_chars=False,x_tolerance=1.5)
        rows={}
        for w in ws: rows.setdefault(round(w['top']/2),[]).append(w)
        txt=p.extract_text()
        m=re.search(r'ważny od (\S+)',txt);waz=m.group(1) if m else waz
        ar=[w for w in ws if w['text']=='→']
        if ar:
            a=ar[0];hdr=[w for w in ws if abs(w['top']-a['top'])<3 and w['x0']>a['x1']]
            cols=[(w['x0']+w['x1'])/2 for w in hdr]
            ml=re.search(r'^\s*(R\d+)\b',txt,re.M);lin=ml.group(1) if ml else lin
        if not cols: continue
        x0=min(cols)-25
        for k in sorted(rows):
            r=sorted(rows[k],key=lambda w:w['x0'])
            name=' '.join(w['text'] for w in r if w['x1']<x0)
            vals=[w for w in r if w['x0']>=x0]
            if not name or not vals or not any(TIME.match(w['text']) or w['text'] in('—','-') for w in vals): continue
            if name.startswith('Długość') or 'Trasa' in name: continue
            t=[None]*len(cols)
            for w in vals:
                if TIME.match(w['text']):
                    c=min(range(len(cols)),key=lambda i:abs(cols[i]-(w['x0']+w['x1'])/2))
                    t[c]=w['text']
            stops.append((name,t))
    return lin,waz,cols,stops
out={}
for f in sorted(glob.glob('/tmp/rad/*.pdf')):
    b=os.path.basename(f)[:-4];ln,dz=b.split('-')
    lin,waz,cols,stops=parse(f)
    d={'pp':'robocze','sob':'sobota','nd':'niedziela'}[dz]
    out.setdefault(ln.upper(),{})[d]={'wazny_od':waz,'stops':stops,'n':len(cols or [])}
    print(b,lin,waz,len(cols or []),'kursów',len(stops),'wierszy')
json.dump(out,open('/tmp/rad/radzymin.json','w'),ensure_ascii=False)
