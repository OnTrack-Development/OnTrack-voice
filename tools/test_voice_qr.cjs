const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
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
// Exercise the real browser QR handler with a minimal DOM, not only the library.
function renderPanel(slot, code) {
  const panel = {
    textContent: '', innerHTML: '',
    getAttribute: name => name === 'data-pair-code' ? code : name === 'data-pair-slot' ? String(slot) : null
  };
  const document = {
    querySelectorAll: () => [panel],
    querySelector: () => null
  };
  const script = fs.readFileSync(require('node:path').join(__dirname,
    '../saas/console/assets/js/pairing-qr.js'), 'utf8');
  vm.runInNewContext(script, { document, qrcode: qr });
  return panel;
}
assert.match(renderPanel(0, '1BCD-EF01-2345-6789-ABCD-EF01').textContent, /غير صالح/,
  'browser must reject SIM 2 ticket presented for SIM 1');
assert.match(renderPanel(1, '1BCD-EF01-2345-6789-ABCD-EF01').innerHTML, /<svg/,
  'browser renders local QR for the correct SIM only');
console.log('TENANT_PAIRING_LOCAL_QR_PASS');
