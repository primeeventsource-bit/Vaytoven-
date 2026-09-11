import { spawnSync } from 'node:child_process';
if (!process.env.VAYTOVEN_API_URL) throw new Error('Set VAYTOVEN_API_URL to the Laravel server origin before syncing native projects.');
for (const [command,args] of [['node',['scripts/build.mjs']],['npx',['cap','sync']]]) {
  const result=spawnSync(command,args,{stdio:'inherit',env:process.env});
  if(result.status!==0)process.exit(result.status||1);
}
