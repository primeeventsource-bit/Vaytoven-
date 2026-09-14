import { _android } from 'playwright';
import { expect } from '@playwright/test';
import { readFileSync, mkdirSync } from 'node:fs';
const root = new URL('../../', import.meta.url);
const credentials = JSON.parse(readFileSync(new URL('.local/demo-credentials.json', root), 'utf8'));
const [device] = await _android.devices({ omitDriverInstall: true });
if (!device) throw new Error('Start the local Android emulator and install the debug APK first.');
try {
  const webview = await device.webView({ pkg: 'com.vaytoven.app' });
  const page = await webview.page();
  page.setDefaultTimeout(20000);
  await expect(page.locator('.property-card')).toHaveCount(10);
  async function signIn(role) {
    await page.getByRole('link', { name: 'Account', exact: true }).click();
    await page.getByLabel('Email address').fill(credentials[role]);
    await page.getByLabel('Password', { exact: true }).fill(credentials.password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page.getByText('Hello, Local.').or(page.getByText('A moment to'))).toBeVisible();
    if (await page.getByRole('button', { name: 'Accept and continue' }).isVisible()) {
      await page.getByRole('checkbox').check();
      await page.getByRole('button', { name: 'Accept and continue' }).click();
      await expect(page.getByRole('heading', { name: 'Places to fall for' })).toBeVisible();
    }
  }
  async function signOut() {
    await page.getByRole('link', { name: 'Account', exact: true }).click();
    await page.getByRole('button', { name: 'Sign out', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Sign in', exact: true })).toBeVisible();
  }
  await signIn('traveler');
  await page.getByRole('link', { name: 'Explore', exact: true }).click();
  await expect(page.locator('.property-card')).toHaveCount(10);
  const save = page.locator('.property-card').first().locator('button');
  if (await save.getAttribute('aria-pressed') !== 'true') await save.click();
  await expect(save).toHaveAttribute('aria-pressed', 'true');
  await page.getByRole('link', { name: 'Saved', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Ubud Jungle Villa with Plunge Pool' })).toBeVisible();
  await page.locator('.property-card a').first().click();
  await page.getByRole('button', { name: 'Send an offer' }).click();
  await page.getByLabel('Your offer (USD)').fill('137.25');
  await page.getByLabel('A note to the owner').fill('Installed Android emulator verification');
  await page.getByRole('button', { name: 'Send offer', exact: true }).click();
  await expect(page.locator('.offer-card').first()).toContainText('$137.25');
  await signOut();
  await signIn('host');
  await page.getByRole('link', { name: 'Offers', exact: true }).click();
  await expect(page.locator('.offer-card').first()).toContainText('$137.25');
  await page.getByRole('button', { name: 'Accept', exact: true }).first().click();
  await page.getByLabel('A note to the buyer').fill('Verified in installed Android app');
  await page.getByRole('button', { name: 'Confirm acceptance' }).click();
  await expect(page.locator('.offer-card').first()).toContainText('accepted');
  mkdirSync(new URL('.local/', root), { recursive: true });
  await page.screenshot({ path: new URL('.local/android-offer-verified.png', root).pathname });
  await signOut();
  await page.getByRole('link', { name: 'Explore', exact: true }).click();
  await expect(page.locator('.property-card')).toHaveCount(10);
  await page.screenshot({ path: new URL('.local/android-home-verified.png', root).pathname });
  console.log('PASS: installed Android API catalogue, traveler login, saved properties, offer submission, host login/acceptance, and logout.');
} finally {
  await device.close();
}
