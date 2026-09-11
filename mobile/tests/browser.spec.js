import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
const credentials=JSON.parse(readFileSync(new URL('../../.local/demo-credentials.json',import.meta.url),'utf8'));
async function signIn(page,role='traveler'){
  await page.goto('/app/#account');
  await page.getByLabel('Email address').fill(credentials[role]);
  await page.getByLabel('Password',{exact:true}).fill(credentials.password);
  await page.getByRole('button',{name:'Sign in',exact:true}).click();
  await expect(page.getByText('Hello, Local.').or(page.getByText('A moment to'))).toBeVisible();
  if(await page.getByRole('button',{name:'Accept and continue'}).isVisible()){
    await page.getByRole('checkbox').check();await page.getByRole('button',{name:'Accept and continue'}).click();
    await expect(page.getByRole('heading',{name:'Places to fall for'})).toBeVisible();
  }
}

test('mobile browsing, search, empty state, filters and detail photos',async({page})=>{
  const errors=[];page.on('pageerror',error=>errors.push(error.message));
  await page.goto('/app/');
  await expect(page.locator('.property-card')).toHaveCount(10);
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
  await page.getByRole('button',{name:'Bali',exact:true}).click();
  await expect(page.locator('.property-card')).toHaveCount(2);
  await page.locator('.property-card a').first().click();
  await expect(page.getByRole('heading',{name:'Ubud Jungle Villa with Plunge Pool'})).toBeVisible();
  const first=await page.locator('#detail-photo').getAttribute('src');
  await page.getByRole('button',{name:'Next photo'}).click();
  await expect(page.locator('#detail-photo')).not.toHaveAttribute('src',first);
  await page.getByRole('link',{name:'Back to explore'}).click();
  await page.getByRole('searchbox').fill('NoSuchDestination987');await page.getByRole('button',{name:'Search properties'}).click();
  await expect(page.getByRole('heading',{name:'A new destination awaits'})).toBeVisible();
  await page.getByRole('button',{name:'Explore all properties'}).click();
  await expect(page.locator('.property-card')).toHaveCount(10);
  await page.getByRole('button',{name:'Filters',exact:true}).click();
  await page.getByLabel('Space for').selectOption('8');await page.getByRole('button',{name:'Find my places'}).click();
  await expect(page.locator('.property-card')).toHaveCount(1);
  expect(errors).toEqual([]);
});

test('sign in, account-backed save, inquiry submission and logout',async({page})=>{
  await signIn(page);
  await page.getByRole('link',{name:'Explore',exact:true}).click();
  await expect(page.locator('.property-card')).toHaveCount(10);
  const save=page.locator('.property-card').first().locator('button');
  if(await save.getAttribute('aria-pressed')==='true')await save.click();
  await save.click();await expect(save).toHaveAttribute('aria-pressed','true');
  await page.getByRole('link',{name:'Saved',exact:true}).click();
  await expect(page.getByRole('heading',{name:'Ubud Jungle Villa with Plunge Pool'})).toBeVisible();
  await page.locator('.property-card a').first().click();
  await page.getByRole('button',{name:'Send an offer'}).click();
  await page.getByLabel('Your offer (USD)').fill('123.45');
  await page.getByLabel('A note to the owner').fill('Local mobile verification');
  await page.getByRole('button',{name:'Send offer',exact:true}).click();
  await expect(page.getByRole('heading',{name:'Your offers & inquiries'})).toBeVisible();
  await expect(page.locator('.offer-card').first()).toContainText('$123.45');
  await page.getByRole('link',{name:'Account',exact:true}).click();
  await page.getByRole('button',{name:'Sign out',exact:true}).click();
  await expect(page.getByRole('button',{name:'Sign in',exact:true})).toBeVisible();
});

test('listing owner can respond through the app',async({page})=>{
  await signIn(page,'host');
  await page.getByRole('link',{name:'Offers',exact:true}).click();
  await expect(page.locator('.offer-card').first()).toContainText('Received');
  await page.getByRole('button',{name:'Accept',exact:true}).first().click();
  await page.getByLabel('A note to the buyer').fill('Local verification response');
  await page.getByRole('button',{name:'Confirm acceptance'}).click();
  await expect(page.locator('.offer-card').first()).toContainText('accepted');
});

test('support renders a useful fallback without provider credentials',async({page})=>{
  await page.goto('/app/#support');
  await page.getByLabel('Message to Vaytoven support').fill('Hello');
  await page.getByRole('button',{name:'Send message',exact:true}).click();
  await expect(page.locator('.chat-message').last()).toContainText(/unavailable|unable|email/i);
});

test('invalid login stays on the form and shows a readable error',async({page})=>{
  await page.goto('/app/#account');await page.getByLabel('Email address').fill('unknown@demo.vaytoven.local');
  await page.getByLabel('Password',{exact:true}).fill('WrongPassword456');await page.getByRole('button',{name:'Sign in',exact:true}).click();
  await expect(page.locator('.form-error')).toContainText('incorrect');
});

test('wide and narrow layouts fit the viewport and offline state is visible',async({page,context})=>{
  await page.setViewportSize({width:1440,height:1000});await page.goto('/app/');
  await expect(page.locator('.property-card')).toHaveCount(10);
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
  await page.screenshot({path:'.local/mobile-desktop.png',fullPage:true});
  await page.setViewportSize({width:360,height:800});
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
  await page.screenshot({path:'.local/mobile-phone.png',fullPage:true});
  await context.setOffline(true);await expect(page.locator('#offline')).toBeVisible();await context.setOffline(false);
});
