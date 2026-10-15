// usage: node sectionshots.cjs url outPrefix width height selector...
const { chromium } = require('playwright');
(async () => {
  const [url, prefix, w, h, ...sels] = process.argv.slice(2);
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  const p = await b.newPage({ viewport: { width: +w, height: +h }, isMobile: +w < 600, hasTouch: +w < 600 });
  await p.goto(url, { waitUntil: 'load' });
  await p.evaluate(() => document.fonts.ready);
  let i = 0;
  for (const sel of sels) {
    await p.evaluate(s => { const el = document.querySelector(s); window.scrollTo(0, el.getBoundingClientRect().top + window.scrollY - 64); }, sel);
    await p.waitForTimeout(700);
    await p.screenshot({ path: `${prefix}-${i++}.png` });
  }
  await b.close();
})();
