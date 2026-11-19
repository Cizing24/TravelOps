"""Encode actual Playwright clips, title cards and burned-in Traditional Chinese captions."""
import argparse
import json
import pathlib
import subprocess
from PIL import Image, ImageDraw, ImageFont

work=pathlib.Path('.runtime/media')
parser=argparse.ArgumentParser()
parser.add_argument('--traveller-cut',nargs=2,type=float,metavar=('START','END'))
args=parser.parse_args()
out=pathlib.Path('docs/portfolio/travelops-v3/video')
out.mkdir(parents=True,exist_ok=True)
font='C:/Windows/Fonts/msjh.ttc'
def run(args):
    subprocess.run(args,check=True,stdout=subprocess.DEVNULL,stderr=subprocess.PIPE)
def duration(file):
    return float(subprocess.check_output(['ffprobe','-v','error','-show_entries','format=duration','-of','default=noprint_wrappers=1:nokey=1',str(file)]))
def timecode(t, sep=','):
    ms=round(t*1000)
    return f'{ms//3600000:02}:{ms//60000%60:02}:{ms//1000%60:02}{sep}{ms%1000:03}'
def card(name,title,subtitle,lines):
    im=Image.new('RGB',(1280,720),'#123e35'); d=ImageDraw.Draw(im)
    d.text((90,145),title,font=ImageFont.truetype(font,64),fill='#f5f7ed')
    d.text((94,248),subtitle,font=ImageFont.truetype(font,28),fill='#b4d5c5')
    for i,line in enumerate(lines): d.text((94,355+i*54),line,font=ImageFont.truetype(font,26),fill='white')
    file=work/f'{name}.png';im.save(file)
    run(['ffmpeg','-y','-loop','1','-i',str(file),'-t','5','-vf','fps=25,format=yuv420p','-c:v','libx264','-crf','20','-an',str(work/f'{name}.mp4')])
card('intro','TravelOps','Ticket Fulfillment & Operations',['獨立電子票券履約與營運展示','Independent portfolio demo / Synthetic data / Mock integrations'])
card('outro','Transactions you can trace','Laravel / MySQL / Redis / Transactional Outbox',['共用庫存、鎖外履約、退款互斥與交易對帳','合成資料 · 本地 Mock 整合 · 未正式部署'])
names=['traveller','pos','operations']
lengths=[]
for name in names:
    src=work/f'{name}.webm'
    if name=='traveller' and args.traveller_cut:
        start,end=args.traveller_cut
        assert 0<start<end<duration(src)
        filters=f'[0:v]split[a][b];[a]trim=end={start},setpts=PTS-STARTPTS[a1];[b]trim=start={end},setpts=PTS-STARTPTS[b1];[a1][b1]concat=n=2:v=1:a=0,fps=25,scale=1280:720,setsar=1,format=yuv420p[v]'
        run(['ffmpeg','-y','-i',str(src),'-filter_complex',filters,'-map','[v]','-c:v','libx264','-crf','20','-an',str(work/f'{name}.mp4')])
    else:
        run(['ffmpeg','-y','-i',str(src),'-vf','fps=25,scale=1280:720,setsar=1,format=yuv420p','-c:v','libx264','-crf','20','-an',str(work/f'{name}.mp4')])
    lengths.append(duration(work/f'{name}.mp4'))
total=10+sum(lengths)
assert 60<=total<=90, f'Actual footage + cards is {total:.2f}s; edit deliberately instead of padding.'
files=['intro',*names,'outro']
(work/'concat.txt').write_text(''.join(f"file '{name}.mp4'\n" for name in files),encoding='utf-8',newline='\n')
run(['ffmpeg','-y','-f','concat','-safe','0','-i',str(work/'concat.txt'),'-c','copy',str(work/'assembled.mp4')])
a=5; b=a+lengths[0]; c=b+lengths[1]; e=c+lengths[2]
captions=[
    (0,5,'獨立作品展示｜合成資料與 Mock 整合','Independent portfolio demo | Synthetic data and Mock integrations'),
    (a,a+9,'旅客先查看商品、場館、效期與購票預估','Browse tickets, venues, validity and estimated totals.'),
    (a+9,b-5,'建立訂單、模擬重複付款回呼，追蹤付款與開券','Place an order, simulate duplicate payment callbacks and follow issuance.'),
    (b-5,b,'查看場館權益；QR 與核銷憑證已遮蔽','Inspect venue entitlements; QR and redemption credentials are redacted.'),
    (b,b+9,'門市變更張數與付款方式，即時核對銷售摘要','Change POS quantity and payment method; check the live summary.'),
    (b+9,c,'確認兩張 NT$ 640；後端負責計價與共用庫存','Confirm two tickets at NT$640; server pricing and shared stock remain authoritative.'),
    (c,c+5,'營運端追蹤開券工作與異常','Operations traces issuance work and failures.'),
    (c+5,e-5,'比較內外合成帳冊，保留差異與處理入口','Compare internal and Mock ledgers, with differences and disposition controls.'),
    (e-5,e,'依條件檢視營運報表；這些不是實際業績','Filter operational reports; these are synthetic results, not real business performance.'),
    (e,total,'共用庫存 × Outbox × 退款互斥｜未正式部署','Shared stock, Outbox and refund coordination | No production deployment'),
]
for lang,index in [('zh-tw',2),('en',3)]:
    (out/f'travelops-v3-demo.{lang}.srt').write_text('\n\n'.join(f'{i+1}\n{timecode(s)} --> {timecode(t)}\n{row[index]}' for i,row in enumerate(captions) for s,t in [row[:2]])+'\n',encoding='utf-8',newline='\n')
ass='''[Script Info]
ScriptType: v4.00+
PlayResX: 1280
PlayResY: 720
[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Default,Microsoft JhengHei,24,&H00FFFFFF,&H00FFFFFF,&H001A332B,&H001A332B,0,0,0,0,100,100,0,0,3,3,0,2,30,30,18,1
Style: Disclosure,Arial,15,&H00FFFFFF,&H00FFFFFF,&H001A332B,&H001A332B,0,0,0,0,100,100,0,0,3,2,0,9,20,20,12,1
[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
'''
def at(t): return timecode(t,'.')[:-1]
ass+=f'Dialogue: 1,0:00:00.00,{at(total)},Disclosure,,0,0,0,,Synthetic data / Mock integrations\n'
for s,t,zh,en in captions: ass+=f'Dialogue: 0,{at(s)},{at(t)},Default,,0,0,0,,{zh}\n'
if args.traveller_cut:
    cut=5+args.traveller_cut[0]
    ass+=f'Dialogue: 1,{at(cut)},{at(cut+3)},Disclosure,,0,0,38,,等待片段已剪短 / Wait shortened\n'
(work/'captions.ass').write_text(ass,encoding='utf-8',newline='\n')
run(['ffmpeg','-y','-i',str(work/'assembled.mp4'),'-vf',f'ass={work.as_posix()}/captions.ass','-c:v','libx264','-crf','21','-preset','medium','-pix_fmt','yuv420p','-movflags','+faststart','-an',str(out/'travelops-v3-demo.mp4')])
run(['ffmpeg','-y','-ss','8','-i',str(out/'travelops-v3-demo.mp4'),'-frames:v','1',str(out/'travelops-v3-demo-poster.png')])
metadata=json.loads(subprocess.check_output(['ffprobe','-v','error','-show_format','-show_streams','-of','json',str(out/'travelops-v3-demo.mp4')]))
(work/'video-metadata.json').write_text(json.dumps(metadata,indent=2),encoding='utf-8',newline='\n')
(work/'timeline.json').write_text(json.dumps({'clips':dict(zip(names,lengths)),'duration':duration(out/'travelops-v3-demo.mp4'),'captions':captions},ensure_ascii=False,indent=2),encoding='utf-8',newline='\n')
# Contact sheet is private evidence; inspect it alongside full-resolution frames.
sheet=Image.new('RGB',(4*480,3*294),'#eeeeee');draw=ImageDraw.Draw(sheet)
for i,t in enumerate([1,8,15,23,31,b-2,b+4,b+12,c+4,c+11,e-3,total-2]):
    frame=work/f'check-{i:02}.png'
    run(['ffmpeg','-y','-ss',str(t),'-i',str(out/'travelops-v3-demo.mp4'),'-frames:v','1',str(frame)])
    image=Image.open(frame); image.thumbnail((480,270));x=i%4*480;y=i//4*294
    sheet.paste(image,(x,y));draw.text((x+8,y+272),f'{t:.2f}s',fill='black')
sheet.save(work/'video-contact-sheet.png')
print(f'Encoded actual interaction demo: {duration(out/"travelops-v3-demo.mp4"):.2f}s, 1280x720, H.264, no audio.')
