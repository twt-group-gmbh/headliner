import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

const mock = (name) => fileURLToPath(new URL(`./Tests/JavaScript/Mocks/${name}.js`, import.meta.url));

// The page module script imports four modules of the TYPO3 backend. They are not here, so the tests put small mocks in
// their place (Tests/JavaScript/Mocks).
export default defineConfig({
  resolve: {
    alias: {
      '@typo3/backend/ajax-data-handler.js': mock('ajax-data-handler'),
      '@typo3/core/ajax/ajax-request.js': mock('ajax-request'),
      '@typo3/backend/notification.js': mock('notification'),
      '@typo3/backend/storage/persistent.js': mock('persistent'),
    },
  },
  test: {
    environment: 'jsdom',
    include: ['Tests/JavaScript/**/*.test.js'],
    setupFiles: ['Tests/JavaScript/setup.js'],
  },
});
