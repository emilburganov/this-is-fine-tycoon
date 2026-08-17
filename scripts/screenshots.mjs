import fs from 'node:fs';
import puppeteer from 'puppeteer-core';

const out = new URL('../docs/screenshots/', import.meta.url);
fs.mkdirSync(out, { recursive: true });

const browser = await puppeteer.launch({
    executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    headless: true,
    args: ['--hide-scrollbars', '--no-sandbox'],
});

const page = await browser.newPage();

async function api(page, path, body) {
    return page.evaluate(async (path, body) => {
        const token = document.querySelector('meta[name="csrf-token"]').content;
        const res = await fetch(path, {
            method: body ? 'POST' : 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Fine-Save': localStorage.getItem('fine_save') || '',
            },
            body: body ? JSON.stringify(body) : undefined,
        });
        const data = await res.json();
        const uuid = data?.player?.uuid || data?.state?.player?.uuid;
        if (uuid) localStorage.setItem('fine_save', uuid);
        return data;
    }, path, body);
}

await page.setViewport({ width: 1440, height: 900, deviceScaleFactor: 2 });
await page.goto('http://127.0.0.1:8001', { waitUntil: 'networkidle0', timeout: 20000 });
await page.evaluate(() => localStorage.removeItem('fine_save'));
await api(page, '/api/game/reset', {});
await page.reload({ waitUntil: 'networkidle0' });
await page.waitForSelector('.dog-svg');
await new Promise((r) => setTimeout(r, 500));
await page.screenshot({ path: new URL('01-kitchen-desktop.png', out).pathname, type: 'png' });

await api(page, '/api/game/sip', { count: 25, combo: 1 });
await api(page, '/api/game/upgrade', { id: 'mug' });
await page.reload({ waitUntil: 'networkidle0' });
await page.waitForSelector('.dog-svg');
await new Promise((r) => setTimeout(r, 400));
await page.screenshot({ path: new URL('02-shop-desktop.png', out).pathname, type: 'png' });

await api(page, '/api/game/monetize', { sku: 'starter' });
await api(page, '/api/game/monetize', { sku: 'fine_pass' });
await api(page, '/api/game/sip', { count: 40, combo: 1 });
await page.reload({ waitUntil: 'networkidle0' });
await page.waitForSelector('.tabs');
const buttons = await page.$$('.tabs button');
await buttons[1].click();
await new Promise((r) => setTimeout(r, 400));
await page.screenshot({ path: new URL('03-monetization-desktop.png', out).pathname, type: 'png' });

await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 2, isMobile: true });
await page.reload({ waitUntil: 'networkidle0' });
await page.waitForSelector('.dog-svg');
await new Promise((r) => setTimeout(r, 400));
await page.screenshot({ path: new URL('04-mobile.png', out).pathname, type: 'png', fullPage: true });

await browser.close();
console.log('screenshots saved');
