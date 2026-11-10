import { chromium } from 'playwright-core';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const credentials = fs.readFileSync(process.env.TRAVELOPS_DEMO_CREDENTIALS ?? '.runtime/demo-credentials.txt', 'utf8');
const passwords = Object.fromEntries([...credentials.matchAll(/\|\s*([\w-]+)@travelops\.test\s*\|\s*([A-Za-z0-9]+)\s*\|/g)].map((match) => [match[1], match[2]]));
assert.ok(['customer', 'operator', 'admin', 'merchant-a', 'merchant-b', 'pos-a', 'pos-b', 'pos-supervisor-a'].every(role => passwords[role]), 'Missing demo credentials');

const browser = await chromium.launch({ channel: process.env.TRAVELOPS_CHROME_CHANNEL ?? 'chrome', headless: true });
const baseURL = process.env.TRAVELOPS_URL ?? 'http://localhost:8080';
const screenshots = process.env.TRAVELOPS_SMOKE_SCREENSHOTS;
if (screenshots) fs.mkdirSync(screenshots, { recursive: true });
const errors = [];
const open = async (role, viewport = { width: 1280, height: 840 }) => {
    const context = await browser.newContext({ baseURL, viewport });
    const page = await context.newPage();
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill(role + '@travelops.test');
    await page.getByLabel('密碼', { exact: true }).fill(passwords[role]);
    await page.getByRole('button', { name: '登入', exact: true }).click();
    await page.waitForURL((url) => url.pathname !== '/login');
    return page;
};
const buy = async (page, slug, scenario) => {
    await page.goto('/products/' + slug);
    await page.getByRole('button', { name: '建立訂單' }).click();
    await page.waitForURL('**/my/orders/*');
    const orderUrl = page.url();
    await page.getByLabel('模擬付款情境').selectOption(scenario);
    const result = page.waitForResponse(response => response.url() === orderUrl + '/simulate' && response.request().method() === 'POST');
    await page.getByRole('button', { name: '執行模擬付款' }).click();
    assert.equal((await result).status(), 302);
    await page.getByRole('status').filter({ hasText: '已送出模擬付款結果。' }).waitFor();
    return orderUrl;
};
const ticket = async (page) => {
    await page.getByRole('link', { name: '查看票券 1' }).click({ timeout: 90000 });
    await page.getByRole('img', { name: '場館核銷 QR Code' }).waitFor();
    return (await page.locator('code').textContent()).trim();
};
const redeem = async (page, code) => {
    await page.goto('/merchant/redemptions');
    await page.getByLabel('核銷代碼').fill(code);
    await page.getByRole('button', { name: '確認核銷' }).click();
};
const assertResponsive = async (page) => {
    const original = page.viewportSize();
    for (const width of [390, 768, 1280]) {
        await page.setViewportSize({ width, height: original.height });
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
            'Page-level overflow at ' + width + ' px for ' + new URL(page.url()).pathname.split('/').slice(0, 3).join('/'));
    }
    await page.setViewportSize(original);
};
try {
    const desktop = await browser.newPage({ baseURL, viewport: { width: 1280, height: 840 }, reducedMotion: 'reduce' });
    assert.equal((await desktop.goto('/up')).status(), 200);
    await desktop.goto('/products');
    await desktop.getByRole('heading', { name: '在售票券' }).waitFor();
    await desktop.keyboard.press('Tab');
    assert.equal(await desktop.evaluate(() => document.activeElement.textContent.trim()), '跳至主要內容');
    assert.equal(await desktop.evaluate(() => getComputedStyle(document.activeElement).outlineStyle), 'solid');
    await desktop.keyboard.press('Enter');
    assert.equal(await desktop.evaluate(() => document.activeElement.id), 'content');
    assert.equal(await desktop.getByRole('link', { name: '登入', exact: true }).evaluate(link => getComputedStyle(link).transitionDuration), '0s');
    await assertResponsive(desktop);
    if (screenshots) await desktop.screenshot({ path: screenshots + '/catalog-desktop.png' });
    const customer = await open('customer', { width: 390, height: 844 });
    await customer.goto('/my/notifications');
    const checkbox = customer.getByLabel('模擬 Email', { exact: true });
    assert.ok(await checkbox.evaluate(input => input.offsetWidth < 32 && input.closest('label').offsetHeight >= 44));
    await assertResponsive(customer);
    await customer.getByLabel('模擬 Email', { exact: true }).check();
    await customer.getByLabel('模擬 LINE', { exact: true }).check();
    await customer.getByRole('button', { name: '儲存通知偏好' }).click();
    await customer.getByText('通知偏好已儲存。', { exact: true }).waitFor();
    await customer.goto('/products');
    assert.ok(await customer.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    if (screenshots) await customer.screenshot({ path: screenshots + '/catalog-mobile.png' });
    await customer.goto('/products/coast-four');
    await customer.getByLabel('購買數量').fill('2');
    assert.equal(await customer.locator('[data-total]').textContent(), 'NT$ 1,760');
    await assertResponsive(customer);
    const bundleUrl = await buy(customer, 'coast-four', 'duplicate');
    const bundleReference = bundleUrl.split('/').pop();
    const bundleCode = await ticket(customer);
    assert.match(bundleCode, /^[a-f0-9]{48}$/);
    for (const venue of ['潮線水族館', '港城設計館', '岬角觀景台', '風土工藝館']) {
        await customer.getByText(venue, { exact: true }).waitFor();
    }
    const ticketUrl = customer.url();
    await assertResponsive(customer);
    const a = await open('merchant-a', { width: 390, height: 844 });
    await redeem(a, bundleCode);
    await a.getByText(/已完成核銷/).waitFor();
    await assertResponsive(a);
    await redeem(a, bundleCode);
    await a.getByText('此場館權益已核銷。', { exact: true }).waitFor();
    const b = await open('merchant-b');
    await redeem(b, bundleCode);
    await b.getByText(/已完成核銷/).waitFor();
    await customer.goto(ticketUrl);
    assert.equal(await customer.getByText('已核銷', { exact: true }).count(), 2);
    await customer.getByText('2 個場館', { exact: true }).waitFor();
    await customer.goto('/my/tickets');
    await customer.getByText('已使用 2 / 4 個場館').first().waitFor();
    console.log('PASS Chrome customer, QR, four-venue entitlement, venue A + B, duplicate rejection, 390px layout');

    const operator = await open('operator');
    assert.equal((await operator.goto('/ops')).status(), 200);
    await operator.getByRole('link', { name: /開券待處理異常/ }).waitFor();
    await assertResponsive(operator);
    assert.equal((await operator.goto('/ops/orders')).status(), 200);
    const bundleRow = operator.getByRole('row').filter({ hasText: bundleReference });
    await bundleRow.getByText('交易紀錄', { exact: true }).click();
    const transaction = operator.getByRole('dialog');
    await transaction.getByText(bundleReference, { exact: true }).waitFor();
    await transaction.getByText('付款結果', { exact: true }).waitFor();
    await transaction.getByText('開券追蹤編號', { exact: true }).waitFor();
    assert.equal((await operator.goto('/ops/users')).status(), 403);
    const admin = await open('admin');
    assert.equal((await admin.goto('/ops/users')).status(), 200);
    await admin.getByText('新增帳號', { exact: true }).waitFor();
    await assertResponsive(admin);
    console.log('PASS Chrome Filament operator/admin routes and authorization');

    const refundUrl = await buy(customer, 'bay-aquarium', 'success');
    await ticket(customer);
    const refundedTicket = customer.url();
    await customer.goto(refundUrl);
    await customer.locator('[aria-current="step"]').getByText(/查看可用權益/).waitFor();
    await customer.getByRole('button', { name: '申請整筆退款' }).click();
    await customer.getByText('待審核', { exact: true }).waitFor();
    await operator.goto('/ops/refunds');
    await assertResponsive(operator);
    const row = operator.getByRole('row').filter({ hasText: refundUrl.split('/').pop() });
    await row.getByText('核准模擬退款', { exact: true }).click();
    await operator.getByLabel('模擬退款情境').selectOption('delayed');
    await operator.getByRole('button', { name: '確認退款', exact: true }).click();
    await row.getByText('待處理', { exact: true }).waitFor({ timeout: 40000 });
    await row.getByText('已退款', { exact: true }).waitFor({ timeout: 60000 });
    await customer.goto(refundedTicket);
    await customer.getByText('已撤銷', { exact: true }).first().waitFor();
    assert.equal(await customer.getByRole('img', { name: '場館核銷 QR Code' }).count(), 0);
    console.log('PASS Chrome Journey H pending refund, signed settlement and revoked QR');

    const asyncUrl = await buy(customer, 'island-craft', 'duplicate');
    assert.match(await ticket(customer), /^[a-f0-9]{48}$/);
    await customer.goto(asyncUrl);
    assert.equal(await customer.getByRole('link', { name: /查看票券/ }).count(), 1);
    await operator.goto('/ops/outbox-events');
    const issuance = operator.getByRole('row').filter({ hasText: asyncUrl.split('/').pop() });
    await issuance.getByText('島岸合作票務', { exact: true }).waitFor();
    await issuance.getByText('成功', { exact: true }).waitFor();
    console.log('PASS Chrome Journey F synchronous and asynchronous suppliers');

    const clerk = await open('pos-a', { width: 390, height: 844 });
    await clerk.getByRole('link', { name: '灣岸展示門市', exact: true }).click();
    const storePath = new URL(clerk.url()).pathname;
    assert.ok(await clerk.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    const saleOption = clerk.locator('#product option').filter({ hasText: '潮線水族館單館票' });
    assert.equal(await saleOption.textContent(), '潮線水族館單館票');
    await clerk.getByLabel('商品', { exact: true }).selectOption(await saleOption.getAttribute('value'));
    await clerk.getByLabel('張數').fill('2');
    await clerk.getByLabel('付款方式').selectOption('mock_card');
    assert.equal(await clerk.locator('[data-total]').textContent(), 'NT$ 640');
    assert.equal(await clerk.locator('[data-unit]').textContent(), 'NT$ 320');
    assert.equal(await clerk.locator('[data-method]').textContent(), '模擬卡片');
    assert.match(await clerk.locator('[data-stock]').textContent(), /^\d+ 張$/);
    await assertResponsive(clerk);
    if (screenshots) await clerk.screenshot({ path: screenshots + '/pos-summary-mobile.png', fullPage: true, mask: [clerk.getByRole('link', { name: /×/ })] });
    await saleOption.evaluate(option => { option.dataset.available = '1'; });
    await clerk.getByLabel('張數').fill('3');
    await clerk.getByText(/張數超過畫面顯示的可售量/).waitFor();
    const submit = clerk.getByRole('button', { name: '確認門市銷售' });
    const pending = await submit.evaluate(button => {
        const form = button.form;
        form.addEventListener('submit', event => event.preventDefault(), { once: true });
        form.requestSubmit();
        return {
            disabled: button.disabled,
            status: form.querySelector('[data-submit-status]').textContent,
            duplicateBlocked: !form.dispatchEvent(new SubmitEvent('submit', { cancelable: true })),
        };
    });
    assert.deepEqual(pending, { disabled: true, status: '正在確認門市銷售…', duplicateBlocked: true });
    await clerk.reload();
    await clerk.getByLabel('商品', { exact: true }).selectOption(await saleOption.getAttribute('value'));
    await clerk.getByLabel('張數').fill('2');
    await clerk.getByLabel('付款方式').selectOption('cash');
    await submit.click();
    await clerk.getByRole('heading', { name: '門市銷售明細' }).waitFor();
    await clerk.getByText('NT$ 640', { exact: true }).waitFor();
    await clerk.getByRole('link', { name: '重新整理開券結果' }).or(clerk.getByRole('link', { name: '查看憑證 1' })).waitFor();
    const saleUrl = clerk.url();
    const deadline = Date.now() + 45_000;
    while (await clerk.getByRole('link', { name: '查看憑證 1' }).count() === 0 && Date.now() < deadline) {
        await clerk.waitForTimeout(1000);
        await clerk.reload();
    }
    await clerk.getByRole('link', { name: '查看憑證 1' }).click();
    await clerk.getByRole('img', { name: '場館核銷 QR Code' }).waitFor();
    const otherClerk = await open('pos-b');
    assert.equal((await otherClerk.goto(saleUrl)).status(), 403);
    const supervisor = await open('pos-supervisor-a');
    assert.equal((await supervisor.goto(storePath + '/closing')).status(), 200);
    await supervisor.getByText('現金', { exact: true }).waitFor();
    await assertResponsive(supervisor);
    assert.equal((await operator.goto('/ops/inventory-movements')).status(), 200);
    await operator.getByRole('row').filter({ hasText: '潮線水族館單館票' }).filter({ hasText: '出售' }).first().waitFor();
    console.log('PASS Chrome Journey G POS sale, ticket, store isolation and daily closing');

    await operator.goto('/ops/reconciliation');
    await operator.getByRole('button', { name: '重新取得帳冊並對帳' }).click();
    const orphan = operator.locator('article').filter({ hasText: '000000000080' });
    await orphan.getByRole('heading', { name: /外部有紀錄但內部無紀錄/ }).waitFor();
    await orphan.getByText('內部帳冊', { exact: true }).waitFor();
    await orphan.getByText('外部帳冊', { exact: true }).waitFor();
    await assertResponsive(operator);
    await orphan.getByLabel('處理原因').fill('已確認為展示帳冊差異');
    await orphan.getByRole('button', { name: '標記已處理' }).click();
    await orphan.getByText('處理原因 已確認為展示帳冊差異', { exact: true }).waitFor();
    if (screenshots) await orphan.screenshot({ path: screenshots + '/reconciliation-reviewed.png', mask: [orphan.locator('details')] });
    await operator.getByRole('button', { name: '重新取得帳冊並對帳' }).click();
    await operator.waitForFunction(() => document.querySelectorAll('article').length > 0);
    assert.equal(await orphan.count(), 1);
    console.log('PASS Chrome Journey I reconciliation, review reason and rerun');

    await customer.goto('/my/notifications');
    for (const title of ['付款已確認', '票券已開立', '退款已確認']) {
        await customer.getByRole('heading', { name: title, exact: true }).first().waitFor();
    }
    await customer.getByRole('button', { name: '標記已讀' }).first().click();
    await customer.getByText(/· 已讀/).first().waitFor();
    const sentFilter = new URLSearchParams({ 'filters[status][value]': 'sent' });
    assert.equal((await operator.goto('/ops/notification-deliveries?' + sentFilter)).status(), 200);
    await operator.getByRole('row').filter({ hasText: '付款已確認' }).filter({ hasText: '已模擬投遞' }).first().waitFor();
    await operator.goto('/ops/reports');
    await operator.getByLabel('通路', { exact: true }).selectOption('pos');
    await operator.getByRole('button', { name: '套用報表篩選' }).click();
    await operator.getByText('門市 灣岸展示門市 · 1 筆 · NT$ 640', { exact: true }).waitFor();
    await assertResponsive(operator);
    if (screenshots) await operator.screenshot({ path: screenshots + '/reports-desktop.png' });
    const download = operator.waitForEvent('download');
    await operator.getByRole('link', { name: '匯出 CSV' }).click();
    const csv = fs.readFileSync(await (await download).path(), 'utf8');
    assert.match(csv, /灣岸展示門市,64000,1/);
    console.log('PASS Chrome Journey J inbox, simulated delivery, filtered report and CSV');
    assert.deepEqual(errors, []);
} finally {
    await browser.close();
}
