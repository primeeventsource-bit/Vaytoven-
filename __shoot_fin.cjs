const { chromium } = require('@playwright/test');
const BASE='http://127.0.0.1:8200', OUT=process.argv[2];
const pages=[['home','/'],['properties','/properties'],['become-a-host','/become-a-host'],['members','/members'],['event-centers','/event-centers'],['login','/login']];
(async()=>{
  const b=await chromium.launch();
  const ctx=await b.newContext({viewport:{width:1440,height:900}});
  const p=await ctx.newPage();
  const errs=[];
  p.on('console', m=>{ if(m.type()==='error') errs.push(m.text()); });
  p.on('pageerror', e=>errs.push('PAGEERROR: '+e.message));
  for(const [n,path] of pages){
    const r=await p.goto(BASE+path,{waitUntil:'networkidle',timeout:30000});
    await p.waitForTimeout(800);
    await p.screenshot({path:`${OUT}/${n}.png`});
    console.log(`  ${r.status()} ${n}`);
  }
  console.log(errs.length? '\n  JS ERRORS:\n   '+[...new Set(errs)].join('\n   ') : '\n  no JS console errors');
  await b.close();
})();
