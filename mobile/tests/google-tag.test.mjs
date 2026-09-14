import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
const source=readFileSync(new URL('../../public/vyt-google-tag.js',import.meta.url),'utf8');

test('Google tag preserves the existing queue and initializes only once',()=>{
 const existing=['existing-command'];const scripts=[];
 const window={dataLayer:[existing]};
 const context={window,document:{createElement:()=>({}),head:{appendChild:script=>scripts.push(script)}}};
 runInNewContext(source,context);runInNewContext(source,context);
 assert.equal(window.dataLayer[0],existing);
 assert.equal(window.dataLayer.length,3);
 assert.equal(window.dataLayer[1][0],'js');
 assert.equal(window.dataLayer[2][0],'config');
 assert.equal(window.dataLayer[2][1],'AW-18384124631');
 assert.equal(scripts.length,1);
 assert.equal(scripts[0].async,true);
 assert.equal(scripts[0].src,'https://www.googletagmanager.com/gtag/js?id=AW-18384124631');
});

test('Google tag reuses a pre-existing gtag function',()=>{
 const calls=[];const gtag=(...args)=>calls.push(args);
 const window={gtag};
 runInNewContext(source,{window,document:{createElement:()=>({}),head:{appendChild:()=>{}}}});
 assert.equal(window.gtag,gtag);
 assert.equal(calls.length,2);
 assert.deepEqual(calls[1],['config','AW-18384124631']);
});
