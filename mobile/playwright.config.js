import { defineConfig } from '@playwright/test';
export default defineConfig({
  testDir: './tests', testMatch: '*.spec.js', timeout: 45000, workers: 1, retries: 0,
  reporter: [['list']], outputDir: '../.local/mobile-test-results',
  use: {baseURL:'http://127.0.0.1:8000', viewport:{width:390,height:844}, launchOptions:{executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'}, screenshot:'only-on-failure'},
});
