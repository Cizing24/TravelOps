import { chromium } from 'playwright-core';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

// Run only against the dedicated synthetic media environment.
const baseURL = process.env.TRAVELOPS_URL ?? 'http://localhost:18084';
assert.equal(new URL(baseURL).hostname, 'localhost');
assert.equal(new URL(baseURL).port, '18084');
const root = 'docs/portfolio/travelops-v3';
const privateDir = '.runtime/media';
fs.mkdirSync(`${root}/screenshots`, { recursive: true });
fs.mkdirSync(privateDir, { recursive: true });
const credentials = fs.readFileSync(process.env.TRAVELOPS_DEMO_CREDENTIALS ?? '.runtime/demo-credentials.txt', 'utf8');
const passwords = Object.fromEntries([...credentials.matchAll(/\|\s*([\w-]+)@travelops\.test\s*\|\s*([A-Za-z0-9]+)\s*\|/g)].map(m => [m[1], m[2]]));
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const errors = [];
const shots = [];
const clips = [];

// Redactions are flattened into PNG/video pixels. No transaction value is changed.
async function redact() {
    const scrub = () => {
        if (!document.documentElement) return;
        if (!document.getElementById('media-redaction')) {
            const style = document.createElement('style');
            style.id = 'media-redaction';
            style.textContent = 'img[alt="場館核銷 QR Code"],code.credential{visibility:hidden!important}';
            document.documentElement.append(style);
        }
        const walker = document.createTreeWalker(document.body ?? document.documentElement, NodeFilter.SHOW_TEXT);
        const nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);
        for (const n of nodes) {
            if (['SCRIPT','STYLE','TEXTAREA'].includes(n.parentElement?.tagName)) continue;
            const safe = n.textContent.replace(/\b[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\b/gi, '［交易識別碼已遮蔽］').replace(/\b[a-f0-9]{48}\b/gi, '［核銷憑證已遮蔽］').replace(/[\w.+-]+@travelops\.test/g, '［帳號已遮蔽］');
            if (safe !== n.textContent) n.textContent = safe;
        }
        const qr = document.querySelector('img[alt="場館核銷 QR Code"]');
        if (qr && !document.getElementById('media-qr-note')) {
            const note = document.createElement('p');
            note.id = 'media-qr-note';
            note.textContent = '公開素材：QR 與核銷憑證已遮蔽';
            note.style.cssText = 'padding:18px;background:#123e35;color:white;border-radius:8px;margin-top:16px';
            qr.before(note);
        }
        if (document.body && !document.getElementById('media-disclosure')) {
            const note=document.createElement('p');
            note.id='media-disclosure';
            note.textContent='素材說明：合成資料 / Mock integrations / Independent portfolio demo';
            note.style.cssText='padding:12px 20px;margin:0;background:#123e35;color:white;font:13px system-ui;text-align:center';
            document.body.append(note);
        }
    };
    new MutationObserver(scrub).observe(document, { subtree:true, childList:true, characterData:true });
    scrub();
}

async function authenticate(role) {
    assert.ok(passwords[role], `Missing credentials for ${role}`);
    const context = await browser.newContext({baseURL});
    const page = await context.newPage();
    await page.goto('/login');
    await page.getByLabel('Email', {exact:true}).fill(role + '@travelops.test');
    await page.getByLabel('密碼', {exact:true}).fill(passwords[role]);
    await page.getByRole('button', {name:'登入',exact:true}).click();
    await page.waitForURL(u => u.pathname !== '/login');
    const state = await context.storageState();
    await context.close();
    return state;
}
async function open(role, state, viewport={width:1440,height:900}, video=false) {
    const context = await browser.newContext({baseURL, storageState:state, viewport, reducedMotion:'reduce', ...(video ? {recordVideo:{dir:privateDir,size:viewport}} : {})});
    await context.addInitScript(redact);
    const page = await context.newPage();
    page.on('pageerror', e => errors.push(e.message));
    return {context,page,role};
}
async function ready(page) {
    await page.waitForLoadState('networkidle');
    await page.evaluate(() => document.fonts.ready);
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Page overflow');
    assert.doesNotMatch(await page.locator('body').innerText(), /\b[a-f0-9]{48}\b|\b[a-f0-9]{8}-[a-f0-9-]{27,}\b|[\w-]+@travelops\.test/gi);
    assert.ok(await page.locator('img[alt="場館核銷 QR Code"],code.credential').evaluateAll(elements=>elements.every(el=>getComputedStyle(el).visibility==='hidden')),'Credential redaction must be opaque in the final pixels');
}
async function shot(session, name, purpose, route) {
    await ready(session.page);
    const target=`${root}/screenshots/${name}.png`;
    await session.page.screenshot({path:target,fullPage:true});
    shots.push({file:path.basename(target),route,role:session.role,viewport:session.page.viewportSize(),purpose,bytes:fs.statSync(target).size,redacted:true});
}
async function buy(page) {
    await page.goto('/products/coast-four');
    await page.getByRole('button',{name:'建立訂單',exact:true}).click();
    await page.waitForURL('**/my/orders/*');
    const url=page.url();
    await page.getByLabel('模擬付款情境').selectOption('duplicate');
    await page.getByRole('button',{name:'執行模擬付款',exact:true}).click();
    await page.getByRole('link',{name:'查看票券 1',exact:true}).waitFor({timeout:90000});
    return url;
}
async function pos(page) {
    await page.goto('/pos');
    await page.getByRole('link',{name:'灣岸展示門市',exact:true}).click();
    const product=await page.getByLabel('商品',{exact:true}).locator('option').filter({hasText:'潮線水族館'}).getAttribute('value');
    await page.getByLabel('商品',{exact:true}).selectOption(product);
    await page.getByLabel('張數',{exact:true}).fill('2');
    await page.getByLabel('付款方式',{exact:true}).selectOption('mock_card');
    assert.equal(await page.locator('[data-total]').innerText(),'NT$ 640');
}
const pause=(page,ms)=>page.waitForTimeout(ms);
async function finishClip(s, name) {
    const video=s.page.video();
    await s.context.close();
    const file=`${privateDir}/${name}.webm`;
    await video.saveAs(file);
    clips.push({name,file});
}
try {
    const states={};
    for(const role of ['customer','pos-a','operator']) states[role]=await authenticate(role);
    const customer=await open('customer',states.customer);
    await customer.page.goto('/products');
    await shot(customer,'travelops-catalog-desktop','目錄與封面','/products');
    await customer.page.setViewportSize({width:390,height:844});
    await customer.page.goto('/products/coast-four');
    await shot(customer,'travelops-product-mobile','手機票券、效期、庫存與預估','/products/coast-four');
    await customer.page.setViewportSize({width:1440,height:900});
    await buy(customer.page);
    await shot(customer,'travelops-order-progress','付款及開券進度','/my/orders/{redacted}');
    await customer.page.getByRole('link',{name:'查看票券 1',exact:true}).click();
    await shot(customer,'travelops-ticket-entitlements','多場館權益；憑證遮蔽','/my/tickets/{id}');
    const staff=await open('pos-a',states['pos-a']);
    await pos(staff.page);
    await shot(staff,'travelops-pos-summary','門市商品、付款及 NT$ 640 摘要','/pos/{store}');
    await staff.page.setViewportSize({width:390,height:844});
    await shot(staff,'travelops-pos-mobile','390 px 完整結帳摘要','/pos/{store}');
    const ops=await open('operator',states.operator);
    await ops.page.goto('/ops/reconciliation');
    await ops.page.getByRole('button',{name:'重新取得帳冊並對帳',exact:true}).click();
    await ops.page.getByText('內部帳冊',{exact:true}).first().waitFor();
    await pause(ops.page,6500);
    await shot(ops,'travelops-ops-reconciliation','合成內外帳冊與差異處理','/ops/reconciliation');
    await ops.page.goto('/ops/reports');
    await shot(ops,'travelops-ops-report','合成資料營運 KPI 與篩選','/ops/reports');
    for(const s of [customer,staff,ops]) await s.context.close();
    fs.writeFileSync(`${privateDir}/screenshots.json`,JSON.stringify(shots,null,2));

    if (!process.argv.includes('--screenshots-only')) {
    // Login is completed before recording. These clips contain real browser actions.
    const traveller=await open('customer',states.customer,{width:1280,height:720},true);
    await traveller.page.goto('/products'); await ready(traveller.page); await pause(traveller.page,4000);
    await traveller.page.getByRole('link',{name:'查看 灣岸四館聯票',exact:true}).click();
    await pause(traveller.page,4000);
    await traveller.page.goto('/products/coast-four');
    await traveller.page.getByRole('button',{name:'建立訂單',exact:true}).click();
    await traveller.page.waitForURL('**/my/orders/*'); await pause(traveller.page,3000);
    await traveller.page.getByLabel('模擬付款情境').selectOption('duplicate');
    await traveller.page.getByRole('button',{name:'執行模擬付款',exact:true}).click();
    await traveller.page.getByRole('link',{name:'查看票券 1',exact:true}).waitFor({timeout:90000});
    await pause(traveller.page,3000);
    await traveller.page.getByRole('link',{name:'查看票券 1',exact:true}).click();
    await ready(traveller.page); await pause(traveller.page,4500);
    await finishClip(traveller,'traveller');

    const sale=await open('pos-a',states['pos-a'],{width:1280,height:720},true);
    await pos(sale.page); await ready(sale.page); await pause(sale.page,4000);
    await sale.page.getByLabel('張數',{exact:true}).fill('1'); await pause(sale.page,2500);
    await sale.page.getByLabel('張數',{exact:true}).fill('2'); await pause(sale.page,3000);
    await sale.page.getByRole('button',{name:'確認門市銷售',exact:true}).click();
    await sale.page.waitForURL('**/sales/*'); await pause(sale.page,5000);
    await finishClip(sale,'pos');

    const operation=await open('operator',states.operator,{width:1280,height:720},true);
    await operation.page.goto('/ops/outbox-events'); await ready(operation.page); await pause(operation.page,3500);
    await operation.page.goto('/ops/reconciliation'); await ready(operation.page); await pause(operation.page,2500);
    await operation.page.getByRole('button',{name:'重新取得帳冊並對帳',exact:true}).click();
    await operation.page.getByText('內部帳冊',{exact:true}).first().waitFor();
    await pause(operation.page,2500);
    await operation.page.getByText('內部帳冊',{exact:true}).first().scrollIntoViewIfNeeded(); await pause(operation.page,4500);
    await operation.page.goto('/ops/reports'); await ready(operation.page); await pause(operation.page,5000);
    await finishClip(operation,'operations');
    fs.writeFileSync(`${privateDir}/clips.json`,JSON.stringify(clips,null,2));
    }
    assert.deepEqual(errors,[],'Browser JavaScript errors');
    assert.equal(shots.length,8);
    console.log(`Captured 8 redacted screenshots${process.argv.includes('--screenshots-only') ? '' : ' and 3 actual interaction clips'}.`);
} finally { await browser.close(); }
