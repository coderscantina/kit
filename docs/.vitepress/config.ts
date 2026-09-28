import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Kit',
  description: 'Laravel + Vue with a reactive data layer.',
  cleanUrls: true,
  // localhost links are instructions, not links to check.
  ignoreDeadLinks: [/^https?:\/\/localhost/],
  // docs/generated is written by `php artisan docs:commands` before every
  // dev and build run, and is not a page of its own.
  srcExclude: ['generated/**'],
  themeConfig: {
    nav: [
      { text: 'Architecture', link: '/architecture' },
      { text: 'Adding a feature', link: '/adding-a-feature' },
      { text: 'App shell', link: '/app-shell' },
      { text: 'AI', link: '/ai' },
      { text: 'Release', link: '/release' },
    ],
    sidebar: [
      {
        text: 'Kit',
        items: [
          { text: 'Overview', link: '/' },
          { text: 'Architecture', link: '/architecture' },
          { text: 'Adding a feature', link: '/adding-a-feature' },
          { text: 'App shell', link: '/app-shell' },
          { text: 'AI', link: '/ai' },
          { text: 'People and account', link: '/account' },
          { text: 'Lists and filters', link: '/lists' },
          { text: 'Presence', link: '/presence' },
          { text: 'Notifications', link: '/notifications' },
          { text: 'History and files', link: '/records' },
          { text: 'Tokens, MCP and webhooks', link: '/machine-access' },
          { text: 'Social login', link: '/social-login' },
          { text: 'Push notifications', link: '/push-notifications' },
          { text: 'Runtime contract', link: '/runtime-contract' },
          { text: 'Release procedure', link: '/release' },
          { text: 'Known limitations', link: '/limitations' },
        ],
      },
    ],
    outline: [2, 3],
  },
})
