import re,json,glob,sys
GM={'011':'kobylka','021':'marki','031':'zabki','041':'zielonka','052':'dabrowka','062':'jadow','072':'klembow','082':'poswietne','094':'radzymin','095':'radzymin','102':'strachowka','114':'tluszcz','115':'tluszcz','124':'wolomin','064':'jadow','063':'jadow','113':'tluszcz','123':'wolomin','093':'radzymin','053':'dabrowka','125':'wolomin'}
ROW=re.compile(r'^\s*(\d+)\.\s+(1434\d{3})\s*(.*?)\s*(?:(?:DW|DK)\d+|\d{4}W|[Gg]minna|Powiatowa)?\s*(\d\d:\d\d)\s+[\d,\.]+\s+[\d,\.]+\s.*?\b([op])\.\s+(.*)$')
def tm(s):
    s=s.replace(';',':').replace('>',':')
    m=re.match(r'^(\d{1,2}):(\d\d)$',s);return int(m.group(1))*60+int(m.group(2)) if m else None
out=[]
for f in sorted(glob.glob('/tmp/pow/p*.pdf.txt')):
    T=open(f).read()
    mt=re.search(r'([A-ZŁŚŻŹĆĄĘÓŃ]{3,}[A-ZŁŚŻŹĆĄĘÓŃ\. ]*(?:\s+-\s+[A-ZŁŚŻŹĆĄĘÓŃ][A-ZŁŚŻŹĆĄĘÓŃ\. ]*?)+)(?=\s{2,}|$)',T,re.M)
    tyt=mt.group(1).strip() if mt else '?'
    waz=re.search(r'ważny od dnia ([\d\.]+)',T)
    rows=[];seen=set()
    for line in T.splitlines():
        m=ROW.match(line)
        if not m: continue
        n=int(m.group(1))
        if n in seen: break
        seen.add(n)
        times=[tm(x) for x in re.findall(r'\d{1,2}[:;>]\d\d',m.group(6))]
        nm=re.sub(r'\s+(czasowo zawieszony)$','',m.group(3)).strip()
        nm=re.sub(r'(\D)(\d{4}W)$',r'\1',nm).strip()
        rows.append({'nazwa':nm,'gm':GM.get(m.group(2)[4:],'?'),'t':times,'kod':m.group(2)})
    if not rows: print('BRAK',f);continue
    nc=max(len(r['t']) for r in rows)
    bad=[r['nazwa'] for r in rows if len(r['t'])!=nc]
    # fix non-monotonic glitches per column
    for c in range(nc):
        col=[r['t'][c] if c<len(r['t']) else None for r in rows]
        for i in range(1,len(col)-1):
            a,b,x=col[i-1],col[i+1],col[i]
            if None not in(a,b,x) and not(a<=x<=b) and a<=b: rows[i]['t'][c]=a+(b-a)//2
    out.append({'plik':f.split('/')[-1][:3],'tytul':tyt,'wazny_od':waz.group(1) if waz else '','rows':rows,'nc':nc,'bad':bad})
    print(f.split('/')[-1][:3],tyt,'|',len(rows),'przyst',nc,'kursów','| złe:',bad[:3],'| gminy',sorted(set(r['gm'] for r in rows)))
json.dump(out,open('/tmp/pow/powiat.json','w'),ensure_ascii=False)
