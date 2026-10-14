import { test, expect } from '@playwright/test';

test.describe('Norwich RFC Website - Menus and Headings Check', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to the homepage before each test
    await page.goto('https://www.norwichrugby.com');

    // Handle cookie banner if it appears (optional, but helps keep tests clean)
    const cookieButton = page.locator('text=OK');
    if (await cookieButton.isVisible()) {
      await cookieButton.click();
    }
  });

  test('should display main navigation menu items', async ({ page }) => {
    // Define the expected main menu/navigation items based on the site structure
    const expectedMenuItems = [
      'HOME',
      'TEAMS',
      'MATCH REPORTS',
      'NEWS',
      'ACADEMY',
      'SPONSORSHIP',
      'CLUB INFORMATION',
      'CONTACTS'
    ];

    // Check that each main navigation item is present and visible
    for (const item of expectedMenuItems) {
      const menuItem = page.locator(`nav, header`).locator(`text=${item}`).first();
      await expect(menuItem).toBeVisible();
    }
  });

  test('should display key section headings on the homepage', async ({ page }) => {
    // Define the key section headings expected on the homepage
    const expectedHeadings = [
      'Latest news from around the club',
      'Latest Match Reports',
      'MENS & WOMENS',
      'BOYS YOUTH',
      'GIRLS YOUTH',
      'Mini Rugby',
      "MIDWEEK",
      'FOLLOW',
      'FIND US',
      'JOIN US',
      'CONTACT US'
    ];

    // Check that each heading text is visible on the page
    for (const headingText of expectedHeadings) {
      const heading = page.locator(`text=${headingText}`).first();
      await expect(heading).toBeVisible();
    }
  });
});