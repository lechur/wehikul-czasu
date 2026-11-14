import sqlite3, struct, json, sys, math, unicodedata
sys.path.insert(0,'/home/claude'); from tm import inv
def norm(s): return ''.join(c for c in unicodedata.normalize('NFD',s.lower()) if unicodedata.category(c)!='Mn').replace('-go','').replace('.','').replace('  ',' ').strip()
def parse(b):
    flags=b[3]; et=(flags>>1)&7; off=8+[0,32,48,48,64][et]; w=b[off:]
    def rd(w,o):
        bo='<' if w[o]==1 else '>'; t=struct.unpack(bo+'I',w[o+1:o+5])[0]; o+=5
        dim=3 if (t>1000 and t<2000) or t&0x80000000 else 2; t=t%1000 if t<0x80000000 else t&0xffff
        if t==2:
            n=struct.unpack(bo+'I',w[o:o+4])[0];o+=4;pts=[]
            for i in range(n): pts.append(struct.unpack(bo+'d'*dim,w[o:o+8*dim])[:2]);o+=8*dim
            return [pts],o
        if t==5:
            n=struct.unpack(bo+'I',w[o:o+4])[0];o+=4;res=[]
            for i in range(n): r,o=rd(w,o);res+=r
            return res,o
        raise Exception('type %d'%t)
    return rd(w,0)[0]
d=json.load(open('/home/claude/wc/kobylka/dane.json'))
reg={}
for r in d['odpady']['rejony']:
    for u in r['ulice']: reg[norm(u)]=u
c=sqlite3.connect('/tmp/bdot/PL.PZGiK.330.BDOT10k.1434__OT_SKJZ_L.gpkg')
pts={};names=set();unmatched=set()
for g,n1,n2,cech in c.execute("select geometria,ULICANAZWA1,ULICANAZWA2,ULICACECHA from OT_SKJZ_L where IDENTYFIKATORSIMC='0920539' and ULICANAZWA1 is not null"):
    full=' '.join(x for x in [n2,n1] if x); names.add(full)
    cands=[norm(full),norm(n1),norm((cech or '')+' '+full)]
    key=next((reg[k] for k in cands if k in reg),None)
    if not key:
        key=next((v for k,v in reg.items() if norm(n1).endswith(k) or k.endswith(norm(n1))),None)
    if not key: unmatched.add(full); continue
    for line in parse(g):
        for i in range(len(line)-1):
            (x1,y1),(x2,y2)=line[i],line[i+1]; L=math.hypot(x2-x1,y2-y1); k=max(1,int(L/35))
            for j in range(k+1):
                x=x1+(x2-x1)*j/k; y=y1+(y2-y1)*j/k
                pts.setdefault(key,set()).add((round(x/10),round(y/10)))
print('streets matched',len(pts),'of',len(set(reg.values())),'unmatched bdot names',sorted(unmatched)[:40])
print('missing from bdot:',sorted(set(reg.values())-set(pts))[:60])
# output compact: per street list of [lon,lat] 5 decimals
out={}
for k,s in pts.items():
    arr=[]
    for X,Y in sorted(s):
        # x=E?  BDOT geometry stores (N,E)? detect later
        arr.append((X,Y))
    out[k]=arr
json.dump(out,open('/tmp/ulice_raw.json','w'))
print(list(out.items())[0][1][:3])
