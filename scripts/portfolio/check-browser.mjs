import { chromium } from 'playwright-core';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const root=path.resolve('docs/portfolio/travelops-v3');
const work=path.resolve('.runtime/media');
fs.mkdirSync(work,{recursive:true});
const movie=pathToFileURL(path.join(root,'video/travelops-v3-demo.mp4')).href;
const wrapper=path.join(work,'playback-check.html');
fs.writeFileSync(wrapper,`<!doctype html><meta charset="utf-8"><video controls muted src="${movie}" style="width:1280px;height:720px"></video>`);
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
    const page=await browser.newPage({viewport:{width:1440,height:900}});
    await page.goto(pathToFileURL(wrapper).href);
    await page.waitForFunction(()=>document.querySelector('video').readyState>=2);
    const metadata=await page.locator('video').evaluate(v=>({width:v.videoWidth,height:v.videoHeight,duration:v.duration,canPlay:v.canPlayType('video/mp4; codecs="avc1.64001f"')}));
    assert.equal(metadata.width,1280);assert.equal(metadata.height,720);
    assert.ok(metadata.duration>=60&&metadata.duration<=90);assert.ok(metadata.canPlay);
    await page.locator('video').evaluate(v=>v.play());
    await page.waitForTimeout(700);
    assert.ok(await page.locator('video').evaluate(v=>v.currentTime>.3&&!v.error));
    for(const time of [1,Math.floor(metadata.duration/2),metadata.duration-2]) {
        await page.locator('video').evaluate(async(v,t)=>{
            v.pause(); await new Promise(resolve=>{v.addEventListener('seeked',resolve,{once:true});v.currentTime=t;});
        },time);
        await page.locator('video').screenshot({path:path.join(work,`playback-${Math.floor(time)}.png`)});
    }
    for(const name of ['travelops-system-overview','travelops-transaction-flow']) {
        const file=pathToFileURL(path.join(root,`architecture/${name}.svg`)).href;
        await page.setContent(`<html><body style="margin:0;background:white"><img src="${file}" style="width:1200px;max-width:none"></body></html>`);
        await page.locator('img').evaluate(img=>img.decode());
        await page.locator('img').screenshot({path:path.join(work,`${name}-review.png`)});
    }
    const external=[];
    page.on('request',r=>{if(/^https?:/.test(r.url()))external.push(r.url());});
    await page.goto(pathToFileURL(path.join(root,'architecture/travelops-system-overview.html')).href);
    await page.evaluate(()=>document.fonts.ready);
    assert.deepEqual(external,[],'Overview must be self-contained offline');
    fs.writeFileSync(path.join(work,'browser-validation.json'),JSON.stringify({browser:browser.version(),...metadata,playback:true,seeks:3,offlineOverview:true},null,2));
    console.log('PASS Chrome MP4 decode/playback/three seeks, SVG rendering and offline overview.');
} finally {await browser.close();}
