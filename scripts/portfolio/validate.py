"""File-level self-check and machine-readable asset manifest. Does not certify manual review."""
import hashlib
import json
import pathlib
import re
import subprocess
import xml.etree.ElementTree as ET
from PIL import Image

root=pathlib.Path('docs/portfolio/travelops-v3')
source='11c0e7ae5fa828a69438f807be220c4f37be41c9'
shots=json.loads(pathlib.Path('.runtime/media/screenshots.json').read_text(encoding='utf-8'))
assert len(shots)==8
assets=[]
for s in shots:
    file=root/'screenshots'/s['file']
    im=Image.open(file);im.verify()
    width,height=Image.open(file).size
    assert width==s['viewport']['width']
    assert height>=s['viewport']['height']
    assert width==390 if 'mobile' in s['file'] else width==1440
    s.update(width=width,height=height,bytes=file.stat().st_size)
for file in root.rglob('*.png'):
    with Image.open(file) as im: im.verify()
for name in ['travelops-system-overview','travelops-system-overview-dark']:
    assert Image.open(root/'architecture'/f'{name}.png').size==(2800,1700)
assert Image.open(root/'architecture/travelops-transaction-flow.png').size[0]>=1600
for file in (root/'architecture').glob('*.svg'):
    svg=ET.parse(file).getroot(); assert svg.tag.endswith('svg')
    assert '<script' not in file.read_text(encoding='utf-8').lower()
video=root/'video/travelops-v3-demo.mp4'
probe=json.loads(subprocess.check_output(['ffprobe','-v','error','-show_format','-show_streams','-of','json',str(video)]))
stream=probe['streams'][0];duration=float(probe['format']['duration'])
assert stream['codec_name']=='h264' and stream['pix_fmt']=='yuv420p'
assert (stream['width'],stream['height'])==(1280,720) and 60<=duration<=90
assert all(s['codec_type']!='audio' for s in probe['streams'])
subprocess.run(['ffmpeg','-v','error','-i',str(video),'-f','null','-'],check=True,capture_output=True)
def seconds(t):
    h,m,s=t.replace(',','.').split(':'); return int(h)*3600+int(m)*60+float(s)
subtitle_times=[]
for lang in ['en','zh-tw']:
    text=(root/'video'/f'travelops-v3-demo.{lang}.srt').read_text(encoding='utf-8')
    times=[tuple(map(seconds,m)) for m in re.findall(r'(\d\d:\d\d:\d\d,\d{3}) --> (\d\d:\d\d:\d\d,\d{3})',text)]
    assert len(times)==10 and all(0<=s<e<=duration+.05 for s,e in times)
    assert all(a[1]<=b[0]+.001 for a,b in zip(times,times[1:]))
    subtitle_times.append(times)
assert subtitle_times[0]==subtitle_times[1]
public_text=[root/'TRAVELOPS_PORTFOLIO_ZH_TW.md',root/'TRAVELOPS_PORTFOLIO_EN.md',*(root/'video').glob('*.srt'),*(root/'architecture').glob('*.mmd')]
for file in public_text:
    text=file.read_text(encoding='utf-8')
    assert not re.search(r'github\.com/Cizing24/TravelOps|@travelops\.test|[A-Z]:\\Users\\|\b[a-f0-9]{48}\b|\b[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\b',text,re.I),file
    assert not re.search(r'[\u200b-\u200f\u202a-\u202e\u2060\ufeff]',text),file
for file in root.glob('TRAVELOPS_PORTFOLIO_*.md'):
    text=file.read_text(encoding='utf-8')
    assert 'visibility: private' in text and f'sourceCommit: {source}' in text
    assert not re.search(r'^(repository|demo):',text,re.M)
    description=re.search(r'^description: (.+)$',text,re.M)[1]
    assert 20<=len(description)<=200
assert not re.search(r'[\u3400-\u9fff]',(root/'TRAVELOPS_PORTFOLIO_EN.md').read_text(encoding='utf-8'))
rows=['# 截圖清單','',f'來源提交：`{source}`；擷取日期：2026-11-12。所有角色、場館及交易為合成資料。','',
      'PNG 為實際 Chrome full-page screenshot，寬度等於指定 viewport；高度可超過 viewport 以保留完整頁面。隱私遮蔽與素材說明已固化為像素。下表的可公開是技術審核候選，仍需擁有者核可。','',
      '| 檔案 | 路由 | 角色 | Viewport | PNG 尺寸 | Bytes | 用途 | 審查 |','| --- | --- | --- | --- | --- | --- | --- | --- |']
for s in shots:
    rows.append(f"| [文件](screenshots/{s['file']}) `{s['file']}` | `{s['route']}` | {s['role']} | {s['viewport']['width']}×{s['viewport']['height']} | {s['width']}×{s['height']} | {s['bytes']} | {s['purpose']} | 已遮蔽、已目視；可公開候選 |")
(root/'screenshots-manifest.md').write_text('\n'.join(rows)+'\n',encoding='utf-8',newline='\n')
for file in sorted(root.rglob('*')):
    if file.is_file() and file.suffix in ['.png','.svg','.html','.mmd','.mp4','.srt']:
        item={'file':file.relative_to(root).as_posix(),'bytes':file.stat().st_size,'sha256':hashlib.sha256(file.read_bytes()).hexdigest()}
        if file.suffix=='.png': item['dimensions']=Image.open(file).size
        assets.append(item)
assert sum(a['bytes'] for a in assets)<25*1024*1024
(root/'asset-manifest.json').write_text(json.dumps({'sourceCommit':source,'capturedAt':'2026-11-12','video':{'duration':duration,'codec':'h264','width':1280,'height':720,'audio':False},'assets':assets},indent=2),encoding='utf-8',newline='\n')
print(f'PASS: {len(shots)} screenshots; {len(assets)} assets; {duration:.2f}s H.264 full decode; subtitles, dimensions and publication text boundaries.')
