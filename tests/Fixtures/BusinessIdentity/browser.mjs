import fs from 'node:fs';
import { chromium } from '/home/laudaerp/.nvm/versions/node/v22.23.3/lib/node_modules/@playwright/cli/node_modules/playwright-core/index.mjs';
import { proxyFromEnvironment } from '/home/laudaerp/.agents/skills/lauda-ui-ux/scripts/browser-audit.mjs';
const base=process.argv[2];
if(!/^\/tmp\/lauda-business-[a-f0-9]{16}$/.test(base)) throw new Error('Synthetic runtime required');
const browser=await chromium.launch({headless:true});
const proxy=proxyFromEnvironment(process.env);
if(proxy) proxy.bypass='127.0.0.1,localhost';
const context=await browser.newContext({ignoreHTTPSErrors:true,viewport:{width:1365,height:900},...(proxy?{proxy}:{})});
const projects=['central','ecf','pos','bys','cumplimiento'];
for(const project of projects) await context.addCookies(JSON.parse(fs.readFileSync(base+'/'+project+'/auth.json','utf8')).cookies);
const page=await context.newPage();
const errors=[];page.on('pageerror',error=>errors.push(error.message));
const results=[]; const fonts={};
const check=async(label)=>{
    await page.getByRole('heading',{level:1}).waitFor();
    await page.evaluate(()=>document.fonts.ready);
    const cdp=await context.newCDPSession(page);
    await cdp.send('DOM.enable'); await cdp.send('CSS.enable');
    const doc=await cdp.send('DOM.getDocument');
    const node=await cdp.send('DOM.querySelector',{nodeId:doc.root.nodeId,selector:'h1'});
    fonts[label]=(await cdp.send('CSS.getPlatformFontsForNode',{nodeId:node.nodeId})).fonts.map(f=>({name:f.familyName,custom:f.isCustomFont}));
    await cdp.detach();
    for(const [name,width,height] of [['desktop',1365,900],['mobile',390,844]]){
        await page.setViewportSize({width,height});
        await page.screenshot({path:base+'/'+label+'-'+name+'.png',fullPage:true});
        if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth)) throw new Error(label+' overflows '+name);
    }
    await page.setViewportSize({width:1365,height:900});
};
try{
    for(let i=0;i<projects.length;i++){
        const response=await page.goto('https://127.0.0.1:'+(9440+i)+'/business/identity');
        if(response.status()!==200) throw new Error(projects[i]+' identity HTTP '+response.status());
        await page.getByText('Ana Rivera',{exact:true}).waitFor();
        await check(projects[i]+'-connections');
        results.push(projects[i]+' page and responsive layout');
    }
    for(const [key,label] of [['ecf','e-CF'],['pos','POS'],['bys','BYS'],['cumplimiento','Cumplimiento']]){
        await page.goto('https://127.0.0.1:9440/business/identity');
        const row=page.locator('div.flex.flex-col').filter({has:page.getByRole('heading',{name:label,exact:true})});
        await row.getByRole('button',{name:'Conectar',exact:true}).click();
        await page.getByRole('heading',{name:'Elige la empresa en '+label}).waitFor();
        if(key==='bys') await page.getByLabel('Empresa a vincular').selectOption('1');
        if(await page.evaluate(()=>location.hash || JSON.stringify(history.state).includes('token='))) throw new Error('Fragment was not cleared from URL/history');
        await check(key+'-selection');
        if(key==='bys') {
            await page.route('**/business/identity/proofs', async route=>{ await route.fetch(); await route.abort('failed'); }, {times:1});
            await page.getByRole('button',{name:'Revisar en LAUDA'}).click();
            await page.getByRole('alert').filter({hasText:'No se recibió la respuesta'}).waitFor();
            results.push('lost BYS proof response is recoverable from the same screen');
        }
        await page.getByRole('button',{name:'Revisar en LAUDA'}).click();
        await page.getByRole('heading',{name:'Confirma esta conexión'}).waitFor();
        await check(key+'-confirmation');
        if(key==='bys') {
            await page.route('**/business/identity/bys/confirm', async route=>{ await route.fetch(); await route.abort('failed'); }, {times:1});
            await page.getByRole('button',{name:'Confirmar conexión'}).click();
            await page.getByRole('alert').filter({hasText:'No se recibió la respuesta'}).waitFor();
            results.push('lost central confirmation is recoverable without duplicate consent');
        }
        await page.getByRole('button',{name:'Confirmar conexión'}).click();
        await page.getByText('Conexión completada. Ya puedes abrir la aplicación desde LAUDA.').waitFor({timeout:20000});
        await page.getByRole('link',{name:'Abrir '+label,exact:false}).waitFor();
        results.push(key+' bilateral connection completed via real HTTP/CSRF');
    }
    await check('all-connected');
    // Respect the production 20/minute throttle before checking all four launches.
    await page.waitForTimeout(60000);
    // Real receiver consume + response observed; dashboards are outside this focused fixture schema.
    for(const [key,port] of [['ecf',9441],['pos',9442],['bys',9443],['cumplimiento',9444]]){
        const launch=await page.request.get('https://127.0.0.1:9440/business/identity/companies/1/services/'+key+'/launch',{maxRedirects:0});
        if(launch.status()!==302) throw new Error(key+' launcher '+launch.status());
        const token=new URLSearchParams(new URL(launch.headers().location).hash.slice(1)).get('token');
        await page.goto('https://127.0.0.1:'+port+'/business/identity');
        const outcome=await page.evaluate(async({token})=>{
            const xsrf=document.cookie.split('; ').find(c=>c.startsWith('XSRF-TOKEN='))?.slice(11);
            const response=await fetch('/business/launch',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-XSRF-TOKEN':decodeURIComponent(xsrf??'')},body:JSON.stringify({token})});
            return {status:response.status,body:await response.json()};
        },{token});
        if(outcome.status!==200||!outcome.body.destination.startsWith('/dashboard')) throw new Error(key+' receiver launch '+outcome.status);
        results.push(key+' authorized launch delivered native destination');
    }
    await page.goto('https://127.0.0.1:9440/business/identity');
    const bys=page.locator('div.flex.flex-col').filter({has:page.getByRole('heading',{name:'BYS',exact:true})});
    await bys.getByRole('button',{name:'Revocar mi acceso'}).click();
    await page.getByRole('button',{name:'Confirmar revocación'}).click();
    await page.getByText('Acceso desde LAUDA revocado en esta aplicación.').waitFor();
    await bys.getByRole('button',{name:'Conectar',exact:true}).waitFor();
    results.push('central revocation updates the UI');
    await check('revoked');
    // Visit a pending request as a guest and use the existing BYS login form to recover it.
    let pending;
    await page.route('**/business/identity/companies/1/bys/requests',async route=>{ const response=await route.fetch(); pending=await response.json(); await route.fulfill({response}); },{times:1});
    await bys.getByRole('button',{name:'Conectar',exact:true}).click();
    await page.getByRole('heading',{name:'Elige la empresa en BYS'}).waitFor();
    const guest=await browser.newContext({ignoreHTTPSErrors:true,viewport:{width:390,height:844},...(proxy?{proxy}:{})});
    const login=await guest.newPage();
    await login.goto(pending.continue_url);
    await login.getByRole('heading',{name:'Inicia sesión en BYS'}).waitFor();
    await login.screenshot({path:base+'/guest-pending-mobile.png',fullPage:true});
    await login.getByRole('link',{name:'Iniciar sesión',exact:true}).click();
    await login.locator('input[type="email"]').fill('ana@identity.invalid');
    await login.locator('input[type="password"]').fill('business-synthetic-only');
    await login.locator('button[type="submit"]').click();
    await login.getByRole('heading',{name:'Elige la empresa en BYS'}).waitFor({timeout:20000});
    if(new URL(login.url()).hash) throw new Error('Login restored the fragment');
    await login.screenshot({path:base+'/login-recovered-mobile.png',fullPage:true});
    results.push('guest request survives the real BYS login and returns to authorized selection');
    await guest.close();
    if(errors.length) throw new Error(errors.join('\n'));
    fs.writeFileSync(base+'/browser-results.json',JSON.stringify({passed:true,results,errors,fonts},null,2),{mode:0o600});
    console.log(JSON.stringify({passed:true,checks:results.length,artifacts:base}));
}catch(error){
    fs.writeFileSync(base+'/browser-results.json',JSON.stringify({passed:false,results,errors,error:error.message,url:new URL(page.url()).origin+new URL(page.url()).pathname},null,2),{mode:0o600});
    await page.screenshot({path:base+'/failure.png',fullPage:true});
    console.log(JSON.stringify({passed:false,checks:results.length,error:error.message}));process.exitCode=1;
}finally{await context.close();await browser.close();}
