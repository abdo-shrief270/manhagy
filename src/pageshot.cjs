// usage: node pageshot.cjs url out.png width [height] [scale] [full]
const { chromium } = require('playwright');
(async () => {
  const [url, out, w, h = '900', scale = '1', full = '1'] = process.argv.slice(2);
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  const p = await b.newPage({ viewport: { width: +w, height: +h }, deviceScaleFactor: +scale, isMobile: +w < 600, hasTouch: +w < 600 });
  const errors = [];
  p.on('pageerror', e => errors.push(e.message));
  p.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  await p.addInitScript(() => document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('img[loading=lazy]').forEach(i => i.removeAttribute('loading'))));
  await p.goto(url, { waitUntil: 'load' });
  await p.evaluate(() => document.fonts.ready);
  // load lazy images
  await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 60)); } window.scrollTo(0, 0); });
  await p.evaluate(async () => {
    document.querySelectorAll('img[loading=lazy]').forEach(i => { i.loading = 'eager'; });
    await Promise.all([...document.images].map(i => i.complete ? null : new Promise(r => { i.onload = i.onerror = r; })));
  });
  await p.waitForTimeout(500);
  const overflow = await p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  await p.screenshot({ path: out, fullPage: full === '1' });
  console.log(JSON.stringify({ overflow, errors }));
  await b.close();
})();
