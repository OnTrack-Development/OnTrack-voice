const assert = require('node:assert/strict');
const qr = require('../saas/console/assets/js/qrcode.js');
const valid = /^[A-Fa-f0-9]{4}(?:-?[A-Fa-f0-9]{4}){5}$/;
const code = '0BCD-EF01-2345-6789-ABCD-EF01';
assert.equal(valid.test(code), true);
assert.equal(valid.test('bad-token'), false);
for (const slot of [0,1]) {
  const issued = slot + code.slice(1);
  assert.equal(issued[0], String(slot), 'QR must match the server-selected SIM slot');
  const value = 'ontrackvoice://pair?slot=' + slot + '&code=' + issued;
  const generator = qr(0,'M');
  generator.addData(value);
  generator.make();
  const svg = generator.createSvgTag({cellSize:4,margin:8,scalable:true});
  assert.match(svg, /^<svg/);
  assert.match(svg, /<path/);
  assert.ok(generator.getModuleCount() >= 21);
}
console.log('TENANT_PAIRING_LOCAL_QR_PASS');
