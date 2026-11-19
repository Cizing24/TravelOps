"""Build the official stack-diagram template. Tool assets remain in ignored storage."""
import argparse
import json
import pathlib
import re
import subprocess
import sys

p = argparse.ArgumentParser()
p.add_argument('--skill', type=pathlib.Path, required=True)
p.add_argument('--icons', type=pathlib.Path, required=True)
args = p.parse_args()
out = pathlib.Path('docs/portfolio/travelops-v3/architecture')
out.mkdir(parents=True, exist_ok=True)
template = (args.skill / 'assets/template.html').read_text(encoding='utf-8')
template = template.replace('width: 1000px; height: 540px', 'width: 1400px; height: 850px').replace('width:1000px; height:540px', 'width:1400px; height:850px')
template = template.replace('<!-- TITLE -->', 'TravelOps · System Overview').replace('<!-- KICKER: e.g. stack-diagram / author / 2026-08 -->', 'INDEPENDENT PORTFOLIO DEMO / V3')
template = template.replace('<!-- LEDE: 1-2 short sentences, or delete this <p> -->', 'Synthetic data · Local Mock integrations · Modular monolith')
template = template.replace('oklch(58% 0.20 256)', 'oklch(44% 0.08 166)').replace('oklch(70% 0.16 256)', 'oklch(80% 0.10 166)')
template = template.replace('</style>', ':root{--color-tile-ink:#414b67}.tile{color:var(--color-tile-ink)}</style>')
nodes = [
    ('Customer', 'storefront', 'people', 200, 100),
    ('Store POS', 'store membership', 'people', 420, 100),
    ('Venue staff', 'assigned venues', 'people', 640, 100),
    ('Operations', 'role-scoped access', 'people', 860, 100),
    ('Laravel 13', 'Blade / Livewire / Filament', 'laravel', 420, 340),
    ('Application', 'auth + transactions', 'tower', 680, 340),
    ('MySQL', 'orders / stock / tickets', 'mysql', 940, 340),
    ('Scheduler', 'durable work relay', 'clock', 1200, 340),
    ('Receivers', 'verified inbox / query', 'webhook', 420, 610),
    ('Local Mock', 'supplier / refund / payment', 'mock', 680, 610),
    ('Queue Worker', 'claim / call / confirm', 'queue', 940, 610),
    ('Redis', 'disposable job delivery', 'redis', 1200, 610),
]
groups = '<div class="group gb" style="left:320px;top:280px;width:980px;height:490px"><div class="g-label"><img src="%%ICON_docker%%" alt="">Docker Compose · isolated local runtime</div></div>'
groups += '<div class="group ga-nested" style="left:330px;top:315px;width:450px;height:190px"><div class="g-label">Laravel modular monolith</div></div>'
edges = []
def edge(d, label='', x=0, y=0, cls='e'):
    edges.append(f'<path class="{cls}" d="{d}" marker-end="url(#ar)"/>')
    if label:
        edges.append(f'<text class="el" x="{x}" y="{y}" text-anchor="middle">{label}</text>')
for x in [200,420,640,860]:
    edge(f'M{x} 210 V260 H300 V370 H384', cls='n')
edge('M456 370 H644', 'sync', 550, 360)
edge('M716 370 H904', 'SQL', 810, 360)
edge('M976 370 H1164', 'scan due rows', 1070, 360, 'n')
edge('M1200 446 V604', 'enqueue', 1250, 530)
edge('M1164 640 H976', 'async jobs', 1070, 630)
edge('M904 640 H716', 'local adapter', 810, 630)
edge('M644 640 H456', 'result / callback', 550, 630)
edge('M420 604 V530 H680 V446', 'verified apply', 560, 520, 'n')
edge('M940 604 V446', 'claim / confirm', 1010, 530, 'n')
svg='<svg class="edges" viewBox="0 0 1400 850" aria-hidden="true"><defs><marker id="ar" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6.5" markerHeight="6.5" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="context-stroke"/></marker></defs>'+''.join(edges)+'</svg>'
markup=''.join(f'<div class="node" style="left:{x-66}px;top:{y}px"><div class="tile"><img src="%%ICON_{icon}%%" alt=""></div><div class="name"><span>{name}</span></div><div class="role"><span>{role}</span></div></div>' for name,role,icon,x,y in nodes)
start=template.index('    <!-- GROUPS ')
end=template.index('\n  </div>\n  </div>',start)
template=template[:start]+'<div style="position:absolute;top:24px;left:36px;font:600 24px var(--font-display)">TravelOps / System Overview</div><div style="position:absolute;top:58px;left:36px;font:12px var(--font-mono);color:var(--color-neutral)">Independent demo · Synthetic data · Mock integrations</div>'+groups+svg+markup+'<p style="position:absolute;bottom:28px;left:36px;font-size:14px;color:var(--color-neutral)">Runtime flow · Supplier calls outside order locks · MySQL also stores refunds, Outbox, Inbox, audit and separate Mock ledgers</p>'+template[end:]
template=template.replace('request path','runtime flow').replace('internal</span>','SQL / verification</span>').replace('<span><span class="sw n dash"></span>dev / deploy</span>','')
template=re.sub(r'<!-- FOOT-LEFT:.*?-->', 'Synthetic data / Mock integrations / no production deployment', template)
template=re.sub(r'<!-- FOOT-RIGHT:.*?-->', 'icons: svgl / Lucide · Brand marks belong to their owners', template)
template=template.replace('</body>', '<details><summary>Accessible text / 無障礙替代說明</summary><p>Customers, POS, venue staff and operations enter role-scoped Laravel interfaces. Application services use MySQL transactions. The scheduler scans durable rows and dispatches Redis jobs. Workers call local Mock adapters outside order locks. Verified callbacks or queries confirm results. All data is synthetic.</p><p>旅客、門市、場館及營運透過具權限限制的 Laravel 介面操作。MySQL 保存交易與持久工作，排程將工作送入 Redis；Worker 在訂單鎖外呼叫本地 Mock，經回呼驗證或查詢後確認結果。所有資料皆為合成資料。</p></details></body>')
draft=pathlib.Path('.runtime/media/system-overview-draft.html')
draft.parent.mkdir(parents=True,exist_ok=True)
draft.write_text(template,encoding='utf-8',newline='\n')
subprocess.run([sys.executable,str(args.skill/'scripts/build.py'),str(draft),str(args.icons),str(out/'travelops-system-overview.html'),'--fonts',str(args.skill/'assets/fonts')],check=True)
html=out/'travelops-system-overview.html'
html.write_text('\n'.join(line.rstrip() for line in html.read_text(encoding='utf-8').splitlines())+'\n',encoding='utf-8',newline='\n')
assert len(nodes)==12 and '%%ICON_' not in (out/'travelops-system-overview.html').read_text(encoding='utf-8')
manifest=json.loads((args.icons/'manifest.json').read_text(encoding='utf-8'))
(out/'logo-sources.json').write_text(json.dumps({k:manifest[k] for k in ['people','laravel','tower','mysql','clock','webhook','mock','queue','redis','docker']},indent=2),encoding='utf-8',newline='\n')
