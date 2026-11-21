const {spawnSync} = require('node:child_process');
const path = require('node:path');
const fs = require('node:fs');
const os = require('node:os');
const php = process.env.PHP_BIN || 'php';
const root = path.join(__dirname, '..');
for (const test of ['ai-http-provider.php','ai-admin-flow.php','ai-apply-storage.php']) {
 const r = spawnSync(php,[path.join(__dirname,test)],{cwd:root,stdio:'inherit'});
 if(r.error) {console.error(r.error.message);process.exit(1);}
 if(r.status!==0)process.exit(r.status||1);
}
const rendered=spawnSync(php,[path.join(__dirname,'ai-admin-flow.php'),'render'],{cwd:root,encoding:'utf8'});
if(rendered.status!==0){console.error(rendered.stderr||'Admin render failed');process.exit(1);}
const dir=fs.mkdtempSync(path.join(os.tmpdir(),'itkt-ai-fixture-'));
try {
 const file=path.join(dir,'admin.html'); fs.writeFileSync(file,rendered.stdout);
 const r=spawnSync(process.execPath,[path.join(__dirname,'ai-admin-browser.cjs')],{cwd:root,stdio:'inherit',env:{...process.env,AI_ADMIN_HTML:file}});
 if(r.status!==0)process.exitCode=r.status||1;
} finally {fs.rmSync(dir,{recursive:true,force:true});}
