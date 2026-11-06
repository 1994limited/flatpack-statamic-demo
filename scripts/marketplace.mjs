// Flatpack's Marketplace images and the performance charts in its README, drawn from the
// demo's measurements (run `php artisan demo:compare --all` first). Every figure on them comes
// from storage/demo-results/*.json, so re-measuring and re-running this updates them all.
//
//   npm run marketplace
//
// ADDON  Flatpack's folder (default ../../flatpack-statamic)
// CHROME a Chrome or Chromium to drive (default: Google Chrome on macOS)
import { chromium } from 'playwright-core';
import { readFileSync, writeFileSync, readdirSync, mkdirSync } from 'node:fs';

const demo = new URL('..', import.meta.url).pathname;
const addon = process.env.ADDON || new URL('../../flatpack-statamic', import.meta.url).pathname;
const chrome = process.env.CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const work = new URL('./screenshots/', import.meta.url).pathname;
mkdirSync(work, { recursive: true });
mkdirSync(`${addon}/docs/marketplace`, { recursive: true });
mkdirSync(`${addon}/docs/images`, { recursive: true });

// ---------------------------------------------------------------- the measurements

const SHAPES = ['fixed', 'builder', 'nested'];
const SIZES = ['small', 'medium', 'large'];
const SHAPE_NAMES = { fixed: 'Fixed fields', builder: 'Page builder', nested: 'Nested page builder' };
const results = {};
let machine = '';
for (const f of readdirSync(`${demo}storage/demo-results`)) {
    const r = JSON.parse(readFileSync(`${demo}storage/demo-results/${f}`, 'utf8'));
    results[`${r.generated.shape}-${r.generated.size}`] = r;
    machine = `PHP ${r.php}`;
}
for (const s of SHAPES) for (const z of SIZES) {
    if (!results[`${s}-${z}`]) throw new Error(`No result for ${s} / ${z}: run php artisan demo:compare --all`);
}
const median = (a) => { const v = [...a].sort((x, y) => x - y); const n = v.length; return n % 2 ? v[(n - 1) / 2] : (v[n / 2 - 1] + v[n / 2]) / 2; };
const metric = {
    build: { label: 'Build at deploy', get: (r, m) => r[m].build.ms, unit: 'ms' },
    memory: { label: 'Build memory', get: (r, m) => r[m].build.peak_mb, unit: 'MB' },
    page: { label: 'Typical page load', get: (r, m) => median(Object.values(r[m].pages)), unit: 'ms' },
    save: { label: 'Saving an entry', get: (r, m) => r[m].save.ms, unit: 'ms' },
};
const val = (key, shape, size, mode) => metric[key].get(results[`${shape}-${size}`], mode);
const fmt = (v, unit) => unit === 'MB' ? (v >= 1000 ? `${(v / 1024).toFixed(1)} GB` : `${Math.round(v)} MB`) : (v >= 1000 ? `${(v / 1000).toFixed(1)} s` : `${Math.round(v)} ms`);
const ratio = (key, shape, size) => val(key, shape, size, 'statamic') / val(key, shape, size, 'flatpack');
const x = (n) => `${n.toFixed(1)}×`;
const entries = { small: '375', medium: '2,250', large: '9,000' };

// ---------------------------------------------------------------- the look (as the other 1994 addons)

const brick = '#9A4A12';
const kraft = '#D9A57C';
const grey = '#B9B0A2';
const mark = readFileSync(`${addon}/resources/brand/mark-colour.svg`, 'utf8').replace(/width="512" height="512"/, 'width="40" height="40"');

const page = (body, w = 1600, h = 900) => `<!doctype html><html><head><meta charset="utf-8">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Gloock&family=Public+Sans:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
    * { box-sizing: border-box; }
    body { margin: 0; width: ${w}px; height: ${h}px; overflow: hidden; background: #F2EDE3; color: #1d1d1f; font-family: 'Public Sans', sans-serif; position: relative; }
    .brand { position: absolute; left: 80px; top: 56px; display: flex; align-items: center; gap: 14px; font: 40px/1 Gloock, serif; color: ${brick}; }
    .for { position: absolute; font-size: 22px; font-weight: 500; color: #57534e; }
    .num { font: italic 30px Gloock, serif; color: ${brick}; margin: 0 0 14px; }
    h1 { font: 64px/1.02 Gloock, serif; letter-spacing: -0.02em; margin: 0 0 26px; }
    .lede { font-size: 23px; line-height: 1.45; color: #57534e; margin: 0; }
    .copy { position: absolute; left: 80px; top: 260px; width: 440px; }
    .panel { position: absolute; background: #fff; border: 1px solid #e4ddd0; border-radius: 18px; box-shadow: 0 30px 60px rgba(40, 30, 20, .10); }
    .note { position: absolute; font-size: 15px; color: #8a8378; }
    .key { display: flex; gap: 22px; font-size: 17px; color: #57534e; }
    .key i { display: inline-block; width: 14px; height: 14px; border-radius: 3px; margin-right: 8px; vertical-align: -1px; }
    .stat { font: 76px/1 Gloock, serif; color: ${brick}; letter-spacing: -0.02em; }
    .from { font-size: 20px; color: #8a8378; }
</style></head><body>${body}</body></html>`;

const brand = `<div class="brand">${mark}<span>Flatpack</span></div>`;
const source = (extra = '') => `Measured with the Dog-ear Books demo (php artisan demo:compare), ${machine}, Apple M1 Pro. ${extra}`;

// Horizontal bars, Statamic against Flatpack, for every shape and size of one measure.
function bars(key, { width = 900, rowGap = 14, barH = 15, showRatio = true } = {}) {
    const unit = metric[key].unit;
    let max = 0;
    for (const s of SHAPES) for (const z of SIZES) max = Math.max(max, val(key, s, z, 'statamic'), val(key, s, z, 'flatpack'));
    const labelW = 150, valueW = 230, track = width - labelW - valueW;
    const w = (v) => Math.max(2, Math.round((v / max) * track));
    let html = '';
    for (const s of SHAPES) {
        html += `<div style="font:600 17px 'Public Sans';color:#1d1d1f;margin:${s === 'fixed' ? 0 : 22}px 0 8px">${SHAPE_NAMES[s]}</div>`;
        for (const z of SIZES) {
            const a = val(key, s, z, 'statamic'), b = val(key, s, z, 'flatpack');
            html += `<div style="display:flex;align-items:center;margin-bottom:${rowGap}px">
                <div style="width:${labelW}px;font-size:16px;color:#57534e">${z[0].toUpperCase() + z.slice(1)} <span style="color:#a39b8f">· ${entries[z]}</span></div>
                <div style="width:${track}px">
                    <div style="height:${barH}px;width:${w(a)}px;background:${grey};border-radius:3px;margin-bottom:4px"></div>
                    <div style="height:${barH}px;width:${w(b)}px;background:${brick};border-radius:3px"></div>
                </div>
                <div style="width:${valueW}px;padding-left:16px;font-size:16px;color:#57534e;white-space:nowrap">${fmt(a, unit)} → <b style="color:#1d1d1f">${fmt(b, unit)}</b>${showRatio ? ` <span style="color:${brick};font-weight:600">${x(a / b)}</span>` : ''}</div>
            </div>`;
        }
    }
    return html;
}

const legend = `<div class="key"><span><i style="background:${grey}"></i>Statamic</span><span><i style="background:${brick}"></i>With Flatpack</span></div>`;

function chartScreen(num, title, lede, key, extra = '') {
    return page(`${brand}
        <div class="copy"><p class="num">${num}</p><h1>${title}</h1><p class="lede">${lede}</p></div>
        <div class="for" style="left:80px;bottom:56px">For Statamic</div>
        <div class="panel" style="left:600px;top:70px;width:940px;padding:30px 34px 24px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px"><div style="font:28px Gloock,serif">${metric[key].label}</div>${legend}</div>
            ${bars(key, { width: 870, rowGap: 9, barH: 13 })}
        </div>
        <div class="note" style="left:600px;bottom:22px;width:940px">${source(extra)}</div>`);
}

// ---------------------------------------------------------------- the Marketplace images

const nl = (k) => ratio(k, 'nested', 'large');
const frames = {};

frames['cover-1600x900'] = page(`${brand}
    <div class="for" style="right:80px;top:68px">For Statamic · Free</div>
    <h1 style="position:absolute;left:80px;top:180px;font-size:104px;line-height:.98">Same files.<br><span style="color:${brick}">Less waiting.</span></h1>
    <p class="lede" style="position:absolute;left:80px;top:430px;width:560px">Flatpack compiles Statamic's flat-file content at deploy into PHP that OPcache serves from memory. Your Markdown and YAML stay the source of truth.</p>
    <div style="position:absolute;left:80px;top:640px;display:flex;gap:56px">
        <div><div class="stat">${x(nl('build'))}</div><div class="from">faster builds</div></div>
        <div><div class="stat">${x(nl('memory'))}</div><div class="from">less memory</div></div>
        <div><div class="stat">${x(nl('save'))}</div><div class="from">faster saves</div></div>
    </div>
    <div class="panel" style="left:830px;top:150px;width:690px;padding:30px 34px">
        <div style="font:26px Gloock,serif;margin-bottom:4px">A 9,000-entry site with deep page builders</div>
        <div style="font-size:16px;color:#8a8378;margin-bottom:26px">Statamic → with Flatpack</div>
        ${[['build', 'Build at deploy'], ['memory', 'Build memory'], ['save', 'Saving an entry']].map(([k, l]) => {
            const a = val(k, 'nested', 'large', 'statamic'), b = val(k, 'nested', 'large', 'flatpack');
            return `<div style="margin-bottom:26px"><div style="display:flex;justify-content:space-between;font-size:18px;margin-bottom:8px"><span>${l}</span><span style="color:#57534e">${fmt(a, metric[k].unit)} → <b style="color:#1d1d1f">${fmt(b, metric[k].unit)}</b></span></div>
                <div style="height:18px;background:${grey};border-radius:4px;margin-bottom:5px"></div>
                <div style="height:18px;width:${Math.round((b / a) * 100)}%;background:${brick};border-radius:4px"></div></div>`;
        }).join('')}
        ${legend}
    </div>
    <div class="note" style="left:830px;top:600px;width:690px">${source()}</div>`);

frames['01-build'] = chartScreen('01', 'Builds in a fraction of the time',
    `On a 9,000-entry site with nested page builders, a deploy's content build goes from ${fmt(val('build', 'nested', 'large', 'statamic'), 'ms')} to ${fmt(val('build', 'nested', 'large', 'flatpack'), 'ms')}. The bigger and deeper the content, the bigger the gap.`, 'build');

frames['02-memory'] = chartScreen('02', 'A fraction of the memory',
    `Big page builders aren't parsed until a page needs them, so a build that took ${fmt(val('memory', 'nested', 'large', 'statamic'), 'MB')} now fits in ${fmt(val('memory', 'nested', 'large', 'flatpack'), 'MB')}. Room to spare on small servers.`, 'memory');

const deeperRows = [['build', 'Build at deploy', 'faster'], ['memory', 'Build memory', 'less'], ['save', 'Saving an entry', 'faster']];
const cols = { fixed: 'Fixed fields', builder: 'Page builder', nested: 'Nested builder' };
frames['03-deeper'] = page(`${brand}
    <div class="copy"><p class="num">03</p><h1>The deeper your page builder, the more it helps</h1><p class="lede">Fixed fields gain a little. Page builders gain a lot. Sections inside columns inside blocks gain the most.</p></div>
    <div class="for" style="left:80px;bottom:56px">For Statamic</div>
    <div class="panel" style="left:600px;top:232px;width:920px;padding:0">
        <div style="display:grid;grid-template-columns:200px repeat(3,1fr);border-bottom:1px solid #eee7da">
            <div style="padding:26px 30px;font-size:15px;color:#8a8378;align-self:end">9,000 entries,<br>with Flatpack</div>
            ${SHAPES.map((sh) => `<div style="padding:26px 24px;border-left:1px solid #eee7da"><div style="font:26px Gloock,serif">${cols[sh]}</div></div>`).join('')}
        </div>
        ${deeperRows.map(([k, label, word], ri) => {
            const top = Math.max(...SHAPES.map((sh) => ratio(k, sh, 'large')));
            return `<div style="display:grid;grid-template-columns:200px repeat(3,1fr);${ri < 2 ? 'border-bottom:1px solid #eee7da' : ''}">
                <div style="padding:30px;font-size:18px;color:#57534e;align-self:center">${label}</div>
                ${SHAPES.map((sh) => { const r = ratio(k, sh, 'large'); return `<div style="padding:26px 24px;border-left:1px solid #eee7da">
                    <div style="display:flex;align-items:baseline;gap:8px"><span class="stat" style="font-size:56px">${r.toFixed(1)}×</span><span style="font-size:17px;color:#8a8378">${word}</span></div>
                    <div style="height:10px;background:#f1ebe0;border-radius:5px;margin-top:14px"><div style="height:10px;width:${Math.round((r / top) * 100)}%;background:${brick};border-radius:5px"></div></div>
                </div>`; }).join('')}
            </div>`;
        }).join('')}
    </div>
    <div class="note" style="left:600px;bottom:40px;width:920px">${source('Saves are single measurements.')}</div>`);

frames['04-saves'] = chartScreen('04', 'Saves that don’t wait for the whole site',
    'Saving in the Control Panel writes exactly the same file Statamic does, then updates only what changed in the compiled copy.', 'save', 'Saves are single measurements, so treat them as a direction.');

const code = (lines) => `<pre style="margin:0;font:19px/1.65 'JetBrains Mono',monospace;color:#1d1d1f;white-space:pre">${lines}</pre>`;
frames['05-deploy'] = page(`${brand}
    <div class="copy"><p class="num">05</p><h1>One line in your deploy</h1><p class="lede">Swap Statamic's Stache warm for a Flatpack build. On Laravel Forge, in the deploy script. On Laravel Cloud, in the build commands. No extra PHP extensions.</p></div>
    <div class="for" style="left:80px;bottom:56px">For Statamic</div>
    <div class="panel" style="left:640px;top:150px;width:880px;padding:32px 38px">
        <div style="font-size:15px;font-weight:600;color:#8a8378;letter-spacing:.08em;margin-bottom:14px">YOUR DEPLOY SCRIPT</div>
        ${code(`composer install --no-dev --optimize-autoloader
php artisan config:cache
<span style="color:#a39b8f;text-decoration:line-through">php please stache:warm</span>
<b style="color:${brick}">php please flatpack:build</b>
php please static:warm --queue`)}
    </div>
    <div style="position:absolute;left:640px;top:520px;width:880px;display:grid;grid-template-columns:repeat(3,1fr);gap:22px">
        ${[['Forge', 'Runs on every server in its own deploy.'], ['Laravel Cloud', 'In build commands, baked into every replica.'], ['Switched off?', 'It warms Statamic’s own Stache instead, so the script still works.']].map(([t, d]) => `<div class="panel" style="position:relative;padding:24px 26px"><div style="font:24px Gloock,serif;color:${brick};margin-bottom:8px">${t}</div><div style="font-size:17px;line-height:1.45;color:#57534e">${d}</div></div>`).join('')}
    </div>`);

const icon = (path) => `<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="${brick}" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="${path}"/></svg>`;
const features = [
    ['M4 4h12l4 4v12H4z M8 12h8M8 16h5', 'Files stay the truth', 'Markdown and YAML, edited and committed as now. Flatpack is only a cache.'],
    ['M13 2L4 14h7l-1 8 9-12h-7z', 'Compiled for OPcache', 'Content becomes plain PHP files served from shared memory.'],
    ['M3 7h18M3 12h18M3 17h12', 'Big fields on demand', 'Page builders and long Bard fields are parsed when a page reads them.'],
    ['M3 12a9 9 0 1 0 3-6.7M3 4v5h5', 'Refresh after a pull', 'Only changed files are re-read, deletions and page trees included.'],
    ['M4 4h16v16H4zM4 9h16M9 20V9', 'In the Control Panel', 'What’s compiled, what changed on disk, and a rebuild button.'],
    ['M9 12l2 2 4-4M12 3l7 4v5c0 4-3 7-7 9-4-2-7-5-7-9V7z', 'Tested against Statamic', 'Reads, saves and refreshes checked to match plain Statamic, on 5 and 6.'],
    ['M12 3l9 5-9 5-9-5zM3 13l9 5 9-5', 'Statamic 5.11+ and 6', 'One package for both. PHP 8.2 or later.'],
    ['M4 4h16v6H4zM4 14h16v6H4zM8 7h.01M8 17h.01', 'No extensions', 'Runs on a stock Forge or Laravel Cloud server.'],
    ['M6 6l12 12M18 6L6 18', 'Easy to switch off', 'One environment variable puts Statamic back on its own Stache.'],
];
frames['06-everything'] = page(`${brand}
    <div class="for" style="right:80px;top:68px">For Statamic · Free</div>
    <h1 style="position:absolute;left:80px;top:128px;font-size:60px">Everything in Flatpack</h1>
    <div style="position:absolute;left:80px;top:236px;width:1440px;display:grid;grid-template-columns:repeat(3,1fr);gap:20px">
        ${features.map(([path, title, text]) => `<div style="background:#fff;border:1px solid #e4ddd0;border-radius:14px;padding:26px 28px;height:156px">
            <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px"><span style="display:grid;place-items:center;width:40px;height:40px;border-radius:9px;background:#F5E7DA">${icon(path)}</span><span style="font:30px Gloock,serif;color:${brick}">${title}</span></div>
            <div style="font-size:19px;line-height:1.4;color:#57534e">${text}</div></div>`).join('')}
    </div>
    <div style="position:absolute;left:80px;bottom:52px;display:flex;gap:36px;font-size:19px;font-weight:500">
        <span>flatpack:build · flatpack:refresh · flatpack:status</span><span style="color:#57534e">Statamic 5.11+ and 6 · PHP 8.2+ · MIT licence</span>
    </div>`);

// ---------------------------------------------------------------- README charts (one per measure)

const readme = {};
for (const key of Object.keys(metric)) {
    readme[`performance-${key}`] = page(`<div style="padding:34px 40px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px"><div style="font:30px Gloock,serif">${metric[key].label}</div>${legend}</div>
        ${bars(key, { width: 1120, rowGap: 9, barH: 13 })}
        <div style="font-size:14px;color:#8a8378;margin-top:12px">${source(key === 'save' ? 'Saves are single measurements.' : '')} Entries include French and German translations.</div>
    </div>`, 1200, 600);
}

// ---------------------------------------------------------------- render

const browser = await chromium.launch({ executablePath: chrome });
async function render(name, html, dir, w = 1600, h = 900, scales = [[1, ''], [2, '-2x']], transparent = false) {
    const file = `${work}${name}.html`;
    writeFileSync(file, html);
    for (const [scale, suffix] of scales) {
        const context = await browser.newContext({ viewport: { width: w, height: h }, deviceScaleFactor: scale });
        const tab = await context.newPage();
        await tab.goto(`file://${file}`);
        await tab.evaluate(() => document.fonts.ready);
        await tab.waitForTimeout(400);
        await tab.screenshot({ path: `${dir}/${name}${suffix}.png`, omitBackground: transparent });
        await context.close();
    }
}
for (const [name, html] of Object.entries(frames)) await render(name, html, `${addon}/docs/marketplace`);
for (const [name, html] of Object.entries(readme)) await render(name, html, `${addon}/docs/images`, 1200, 600, [[2, '']]);

// The addon icon at 512 and 1024.
const iconSvg = readFileSync(`${addon}/docs/marketplace/addon-icon.svg`, 'utf8');
await render('addon-icon', `<html><body style="margin:0;background:transparent">${iconSvg}</body></html>`, `${addon}/docs/marketplace`, 512, 512, [[1, ''], [2, '-2x']], true);

await browser.close();
console.log(`Marketplace images in ${addon}/docs/marketplace, README charts in ${addon}/docs/images`);
