// usage: node render.cjs <dir> [scale] [only...]  — renders <dir>/<name>.html per <dir>/sizes.json into out/<dir>/<name>.png
const { chromium } = require('playwright');
const fs = require('fs'); const path = require('path');
(async () => {
  const [dir, scale = '2', ...only] = process.argv.slice(2);
  const root = path.resolve(__dirname, '..');
  const sizes = JSON.parse(fs.readFileSync(path.join(root, dir, 'sizes.json')));
  fs.mkdirSync(path.join(root, 'out', dir), { recursive: true });
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  for (const [name, [w, h]] of Object.entries(sizes)) {
    if (only.length && !only.includes(name)) continue;
    const p = await b.newPage({ viewport: { width: w, height: h }, deviceScaleFactor: +scale });
    await p.goto('file://' + path.join(root, dir, name + '.html'));
    await p.evaluate(() => document.fonts.ready);
    await p.waitForTimeout(250);
    await p.screenshot({ path: path.join(root, 'out', dir, name + '.png') });
    await p.close();
  }
  await b.close();
})();
