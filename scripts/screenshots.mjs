// Screenshots of Dog-ear Books for Flatpack's docs and Marketplace listing. Run against the
// demo with content generated and built, from the demo's folder:
//
//   php artisan demo:users
//   php artisan demo:content --shape=nested --size=large && php please flatpack:build
//   php artisan serve --port=8150
//   cd scripts && npm install && npm run screenshots
//
// DEMO_URL     the demo's address (default http://localhost:8150)
// SCREENSHOTS  where to write them (default ./screenshots)
// CHROME       a Chrome or Chromium to drive (default: Google Chrome on macOS)
import { chromium } from 'playwright-core';
import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync, readdirSync } from 'node:fs';

const base = (process.env.DEMO_URL || 'http://localhost:8150').replace(/\/$/, '');
const out = process.env.SCREENSHOTS || new URL('./screenshots', import.meta.url).pathname;
const chrome = process.env.CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const demo = new URL('..', import.meta.url).pathname;
mkdirSync(out, { recursive: true });

const php = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code], { cwd: demo }).toString().trim();

const browser = await chromium.launch({ executablePath: chrome });
const desktop = { viewport: { width: 1280, height: 860 }, deviceScaleFactor: 2, colorScheme: 'light' };

async function settle(page) {
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(800);
}

// The first published English book, with its page builder.
const [bookId, bookUri] = php(`$e = Statamic\\Facades\\Entry::query()->where("collection", "books")->where("site", "en")->where("published", true)->orderBy("id")->first(); echo $e->id()." ".$e->uri();`).split(' ');

// The site: that book's page.
const site = await browser.newContext(desktop);
const visitor = await site.newPage();
await visitor.goto(`${base}${bookUri}`);
await settle(visitor);
await visitor.screenshot({ path: `${out}/site-book.png` });
await visitor.screenshot({ path: `${out}/site-book-full.png`, fullPage: true });
await visitor.goto(`${base}/`);
await settle(visitor);
await visitor.screenshot({ path: `${out}/site-home.png` });

// The Control Panel, signed in as the demo admin.
const cp = await browser.newContext(desktop);
const admin = await cp.newPage();
await admin.goto(`${base}/cp/auth/login`);
await settle(admin);
await admin.locator('input[type=text], input[type=email]').first().fill('admin@example.com');
await admin.locator('input[type=password]').fill('password');
await admin.locator('button[type=submit]').first().click();
await admin.waitForURL((url) => !url.pathname.includes('/auth/'), { timeout: 20000 });

// Statamic's trial-mode licensing alert covers the page: snooze it.
async function quiet(page) {
    await settle(page);
    const snooze = page.getByText('Snooze', { exact: true });
    if (await snooze.isVisible().catch(() => false)) {
        await snooze.click();
        await settle(page);
    }
}

// A few content files changed on disk, as after a git pull, so the Flatpack page has a list.
// They are put back afterwards.
const blog = `${demo}content/collections/journal/en`;
const changed = readdirSync(blog).sort().slice(2, 5).map((f) => `${blog}/${f}`);
const before = changed.map((f) => readFileSync(f, 'utf8'));
changed.forEach((f, i) => writeFileSync(f, before[i].replace(/^title: .*$/m, "title: 'Edited after a git pull'")));

await admin.goto(`${base}/cp/utilities/flatpack`);
await quiet(admin);
await admin.screenshot({ path: `${out}/cp-flatpack.png` });

changed.forEach((f, i) => writeFileSync(f, before[i]));

await admin.goto(`${base}/cp/utilities`);
await quiet(admin);
await admin.screenshot({ path: `${out}/cp-utilities.png` });

// The book in the editor: its nested page builder.
await admin.goto(`${base}/cp/collections/books/entries/${bookId}`);
await quiet(admin);
await admin.screenshot({ path: `${out}/cp-book.png` });

await browser.close();
// Put the compiled copy back in step with the restored files.
execFileSync('php', ['please', 'flatpack:refresh', '-q'], { cwd: demo });
console.log(`Screenshots in ${out}`);
