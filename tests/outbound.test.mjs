import test from 'node:test';
import assert from 'node:assert/strict';
import {CustomerSpeechGate, validateMission, EXAMPLE_MISSION, LEGACY_EXAMPLE_MISSION} from '../assets/outbound.mjs';

test('mission requires identity and rejects unauthorized price boundaries', () => {
  assert.equal(validateMission(LEGACY_EXAMPLE_MISSION).agent_name, 'عمر');
  assert.throws(() => validateMission({...LEGACY_EXAMPLE_MISSION, agent_name: ''}));
  assert.throws(() => validateMission({...LEGACY_EXAMPLE_MISSION, minimum_price: '4600000'}));
  assert.throws(() => validateMission({...LEGACY_EXAMPLE_MISSION, asking_price: '', minimum_price: '1'}));
  for (const asking_price of ['-1', '0', '1e6', '4,500,000', '1.234', 'Infinity']) {
    assert.throws(() => validateMission({...LEGACY_EXAMPLE_MISSION, asking_price}));
  }
  assert.doesNotThrow(() => validateMission({...LEGACY_EXAMPLE_MISSION, minimum_price: ''}));
});

test('single brief preserves multiline instructions and excludes hidden legacy fields', () => {
  const brief = 'اسمك كريم\nالعميل أحمد\nالسعر 320 جنيه، بدون خصم';
  assert.deepEqual(validateMission({brief: `  ${brief}  `, agent_name: 'اسم مخالف'}), {brief});
  assert.deepEqual(validateMission(EXAMPLE_MISSION), EXAMPLE_MISSION);
  for (const invalid of [null, [], {brief: []}, {brief: '  '}, {brief: 'أ'.repeat(8001)}, {brief: 'a\u0000b'}]) {
    assert.throws(() => validateMission(invalid));
  }
});

test('prepared agent cannot play audio, and arming alone never triggers speech', () => {
  const gate = new CustomerSpeechGate(true);
  assert.deepEqual(gate.accept({inputTranscription: {text: 'ألو'}, modelTurn: {parts: []}}), []);
  gate.arm();
  assert.equal(gate.heardCustomer, false);
  assert.deepEqual(gate.accept({inputTranscription: {text: '...'}}), []);
  assert.deepEqual(gate.accept({outputTranscription: {text: 'أهلاً'}}), []);
});

test('audio arriving before the transcript is released intact in order', () => {
  const gate = new CustomerSpeechGate(true);
  gate.arm();
  const first = {modelTurn: {parts: [{inlineData: {data: 'AAAA'}}]}};
  const second = {outputTranscription: {text: 'أنا عمر'}};
  const end = {turnComplete: true};
  assert.deepEqual(gate.accept(first), []);
  assert.deepEqual(gate.accept(second), []);
  assert.deepEqual(gate.accept(end), []);
  const input = {inputTranscription: {text: 'ألو مين معايا؟'}};
  assert.deepEqual(gate.accept(input), [input, first, second, end, {}]);
  assert.equal(gate.heardCustomer, true);
  assert.deepEqual(gate.accept(first), [first]);
});

test('interruption discards held audio, unrelated sessions have separate gates', () => {
  const gate = new CustomerSpeechGate(true);
  gate.arm();
  gate.accept({outputTranscription: {text: 'stale'}});
  gate.accept({interrupted: true});
  assert.deepEqual(gate.accept({inputTranscription: {text: 'ألو'}}), [{inputTranscription: {text: 'ألو'}}]);
  assert.equal(new CustomerSpeechGate(true).heardCustomer, false);
});

test('gate is bounded and direct demo output is unchanged', () => {
  const gate = new CustomerSpeechGate(true);
  gate.arm();
  assert.throws(() => gate.accept({outputTranscription: {text: 'x'.repeat(2 * 1024 * 1024)}}));
  const content = {outputTranscription: {text: 'أهلاً'}};
  assert.deepEqual(new CustomerSpeechGate(false).accept(content), [content]);
});
