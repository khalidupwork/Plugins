const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ deviceScaleFactor: 1.25 });
  await p.goto('file://' + process.argv[2]); await p.waitForTimeout(1500);
  const N = 40;
  for (const id of ['tracking', 'booking'])
    for (let i = 0; i < N; i++) {
      await p.evaluate(t => frame(t), i / N);
      await p.locator('#' + id).screenshot({ path: `${process.argv[3]}/${id}-${String(i).padStart(2,'0')}.png`, omitBackground: true });
    }
  await b.close();
})();
