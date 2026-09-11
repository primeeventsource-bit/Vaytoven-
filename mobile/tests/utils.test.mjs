import test from 'node:test';
import assert from 'node:assert/strict';
import { dollarsToCents, escapeHtml, safeUrl, formatMoney } from '../www/utils.js';
test('money input preserves cents without floating point conversion',()=>{
  assert.equal(dollarsToCents('123.45'),12345);assert.equal(dollarsToCents('1.01'),101);assert.equal(dollarsToCents('99999999'),9999999900);
  for(const input of ['0','-1','1.001','1e3','NaN','Infinity','99999999.99'])assert.throws(()=>dollarsToCents(input));
});
test('untrusted listing copy is escaped',()=>{assert.equal(escapeHtml('<script>"&\''),'&lt;script&gt;&quot;&amp;&#39;');});
test('links reject executable schemes',()=>{assert.equal(safeUrl('javascript:alert(1)','https://vaytoven.com'),'');assert.equal(safeUrl('/properties/3','https://vaytoven.com'),'https://vaytoven.com/properties/3');});
test('asking prices format correctly',()=>{assert.equal(formatMoney(12345),'$123.45');assert.equal(formatMoney(null),'Price on request');});
