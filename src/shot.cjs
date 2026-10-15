// usage: node shot.cjs file.html out.png width height [scale] [fullPage]
const { chromium } = require('playwright');
(async () => {
  const [file, out, w, h, scale = '1', full = '0'] = process.argv.slice(2);
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  const p = await b.newPage({ viewport: { width: +w, height: +h }, deviceScaleFactor: +scale });
  await p.goto(file.startsWith('http') ? file : 'file://' + require('path').resolve(file));
  await p.evaluate(() => document.fonts.ready);
  await p.waitForTimeout(300);
  await p.screenshot({ path: out, fullPage: full === '1' });
  await b.close();
})();
