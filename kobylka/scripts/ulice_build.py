import json, sys, math, collections, unicodedata
sys.path.insert(0,'/home/claude/scripts'); sys.argv=['x']
exec(open('/home/claude/scripts/ulice_geo.py').read().split("d=json.load")[0])  # norm, parse
import sqlite3
D=json.load(open('/home/claude/wc/kobylka/dane.json'))
FIX={'Kostrowskiego Kościelna':['Kostrowskiego','Kościelna'],'Logistyczna Longinusa':['Logistyczna','Longinusa'],'Chałbińskiego':['Chałubińskiego'],'Bohusza':['Bohusza-Szyszko'],'Szyszko':[],'Frycza Modrzewskiego':['Frycza-Modrzewskiego'],'Przerwy Tetmajera':['Przerwy-Tetmajera'],'Skłodowskiej Curie':['Skłodowskiej-Curie']}
for r in D['odpady']['rejony']:
    new=[]
    for u in r['ulice']: new+=FIX.get(u,[u])
    r['ulice']=sorted(set(new),key=lambda s:norm(s))
reg={}
for r in D['odpady']['rejony']:
    for u in r['ulice']: reg[norm(u)]=(u,r['nazwa'])
c=sqlite3.connect('/tmp/bdot/PL.PZGiK.330.BDOT10k.1434__OT_SKJZ_L.gpkg')
pts=collections.defaultdict(set); disp={}
for g,n1,n2,cech in c.execute("select geometria,ULICANAZWA1,ULICANAZWA2,ULICACECHA from OT_SKJZ_L where IDENTYFIKATORSIMC='0920539' and ULICANAZWA1 is not null"):
    full=' '.join(x for x in [n2,n1] if x)
    k=next((reg[x][0] for x in [norm(full),norm(n1)] if x in reg),None) or next((v[0] for kk,v in reg.items() if norm(n1).endswith(kk) or (len(kk)>5 and kk.endswith(norm(n1)))),None)
    name=k or full.replace('Rondo "Cudu nad Wisłą"','Rondo Cudu nad Wisłą')
    if not k: disp[name]=True
    for line in parse(g):
        for i in range(len(line)-1):
            (x1,y1),(x2,y2)=line[i],line[i+1]; L=math.hypot(x2-x1,y2-y1); n=max(1,int(L/30))
            for j in range(n+1): pts[name].add((round((x1+(x2-x1)*j/n)/10),round((y1+(y2-y1)*j/n)/10)))
# infer region for unlisted streets by nearest listed-street points (majority of 15 nearest)
known=[(p,reg[norm(s)][1]) for s,ps in pts.items() if norm(s) in reg for p in ps]
inferred={}
for s in disp:
    votes=collections.Counter()
    for p in list(pts[s])[::max(1,len(pts[s])//20)]:
        near=sorted(known,key=lambda kp:(kp[0][0]-p[0])**2+(kp[0][1]-p[1])**2)[:9]
        for kp,rn in near: votes[rn]+=1
    rn,cnt=votes.most_common(1)[0]; share=cnt/sum(votes.values())
    inferred[s]=(rn,round(share,2))
for s,(rn,sh) in sorted(inferred.items()): print(f'{s:45s} -> {rn} ({sh})')
nr={r['nazwa']:r for r in D['odpady']['rejony']}
for s,(rn,sh) in inferred.items():
    if s.startswith('Rondo'): continue
    nr[rn].setdefault('ulice_sasiedztwo',[]).append(s)
for r in D['odpady']['rejony']:
    if 'ulice_sasiedztwo' in r: r['ulice_sasiedztwo'].sort(key=norm)
json.dump(D,open('/home/claude/wc/kobylka/dane.json','w'),ensure_ascii=False,indent=1)
O=(64000,49000)
geo={s:[[x-O[0],y-O[1]] for x,y in sorted(ps)] for s,ps in pts.items() if not s.startswith('Rondo')}
json.dump({'origin10m':O,'ulice':geo},open('/home/claude/wc/kobylka/ulice_geo.json','w'),ensure_ascii=False,separators=(',',':'))
print('listed missing geometry:',[v[0] for k,v in reg.items() if v[0] not in pts])
print('points',sum(len(v) for v in geo.values()))
