const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');

const routes = [
  { id: 'home', path: '/', reference: 'docs/new project image/home page 1.png' },
  { id: 'shop', path: '/shop', reference: 'docs/new project image/shop page.png' },
  { id: 'collections', path: '/collections', reference: 'docs/new project image/our collection page.png' },
  { id: 'new-arrivals', path: '/new-arrivals', reference: 'docs/new project image/new arrival page.png' },
];

for (const route of routes) {
  test(`${route.id} renders and has an approved reference`, async ({ page }) => {
    const referencePath = path.resolve(route.reference);
    if (!fs.existsSync(referencePath)) throw new Error(`Approved visual reference is missing: ${referencePath}`);
    await page.goto(route.path, { waitUntil: 'networkidle' });
    await expect(page).toHaveTitle(/Emerald Rozalia/i);
    await expect(page.locator('body')).toHaveScreenshot(`${route.id}.png`, {
      fullPage: true,
      animations: 'disabled',
      caret: 'hide',
      maxDiffPixels: 0,
    });
  });
}
