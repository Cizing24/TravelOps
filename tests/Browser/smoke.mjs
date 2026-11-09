import { chromium } from 'playwright-core';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const credentials = fs.readFileSync(process.env.TRAVELOPS_DEMO_CREDENTIALS ?? '.runtime/demo-credentials.txt', 'utf8');
const passwords = Object.fromEntries([...credentials.matchAll(/\|\s*([\w-]+)@travelops\.test\s*\|\s*([A-Za-z0-9]+)\s*\|/g)].map((match) => [match[1], match[2]]));
assert.ok(passwords.customer && passwords.operator && passwords.admin && passwords['merchant-a'] && passwords['merchant-b'], 'Missing demo credentials');

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
    await page.getByRole('button', { name: '執行模擬付款' }).click();
    return orderUrl;
};
const ticket = async (page) => {
    await page.getByRole('link', { name: '查看票券 1' }).click({ timeout: 45000 });
    await page.getByRole('img', { name: '場館核銷 QR Code' }).waitFor();
    return (await page.locator('code').textContent()).trim();
};
const redeem = async (page, code) => {
    await page.goto('/merchant/redemptions');
    await page.getByLabel('核銷代碼').fill(code);
    await page.getByRole('button', { name: '確認核銷' }).click();
};
try {
    const desktop = await browser.newPage({ baseURL, viewport: { width: 1280, height: 840 } });
    assert.equal((await desktop.goto('/up')).status(), 200);
    await desktop.goto('/products');
    await desktop.getByRole('heading', { name: '在售票券' }).waitFor();
    if (screenshots) await desktop.screenshot({ path: screenshots + '/catalog-desktop.png' });
    const customer = await open('customer', { width: 390, height: 844 });
    await customer.goto('/products');
    assert.ok(await customer.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    if (screenshots) await customer.screenshot({ path: screenshots + '/catalog-mobile.png' });
    const bundleUrl = await buy(customer, 'coast-four', 'duplicate');
    const bundleReference = bundleUrl.split('/').pop();
    const bundleCode = await ticket(customer);
    assert.match(bundleCode, /^[a-f0-9]{48}$/);
    for (const venue of ['潮線水族館', '港城設計館', '岬角觀景台', '風土工藝館']) {
        await customer.getByText(venue, { exact: true }).waitFor();
    }
    const ticketUrl = customer.url();
    const a = await open('merchant-a', { width: 390, height: 844 });
    await redeem(a, bundleCode);
    await a.getByText(/已完成核銷/).waitFor();
    await redeem(a, bundleCode);
    await a.getByText('此場館權益已核銷。', { exact: true }).waitFor();
    const b = await open('merchant-b');
    await redeem(b, bundleCode);
    await b.getByText(/已完成核銷/).waitFor();
    await customer.goto(ticketUrl);
    assert.equal(await customer.getByText('已核銷', { exact: true }).count(), 2);
    await customer.goto('/my/tickets');
    await customer.getByText('已使用 2 / 4 個場館').first().waitFor();
    console.log('PASS Chrome customer, QR, four-venue entitlement, venue A + B, duplicate rejection, 390px layout');

    const operator = await open('operator');
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
    console.log('PASS Chrome Filament operator/admin routes and authorization');

    const refundUrl = await buy(customer, 'bay-aquarium', 'success');
    await ticket(customer);
    const refundedTicket = customer.url();
    await customer.goto(refundUrl);
    await customer.getByRole('button', { name: '申請整筆退款' }).click();
    await customer.getByText('待審核', { exact: true }).waitFor();
    await operator.goto('/ops/refunds');
    const row = operator.getByRole('row').filter({ hasText: refundUrl.split('/').pop() });
    await row.getByText('核准模擬退款', { exact: true }).click();
    await operator.getByRole('button', { name: '確認退款', exact: true }).click();
    await row.getByText('已退款', { exact: true }).waitFor();
    await customer.goto(refundedTicket);
    await customer.getByText('已撤銷', { exact: true }).first().waitFor();
    assert.equal(await customer.getByRole('img', { name: '場館核銷 QR Code' }).count(), 0);
    assert.deepEqual(errors, []);
    console.log('PASS Chrome operator refund confirmation, revoked QR, no browser page errors');
} finally {
    await browser.close();
}
