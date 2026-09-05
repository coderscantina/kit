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
      { text: 'Release', link: '/release' },
    ],
    sidebar: [
      {
        text: 'Kit',
        items: [
          { text: 'Overview', link: '/' },
          { text: 'Architecture', link: '/architecture' },
          { text: 'Adding a feature', link: '/adding-a-feature' },
          { text: 'Runtime contract', link: '/runtime-contract' },
          { text: 'Release procedure', link: '/release' },
          { text: 'Known limitations', link: '/limitations' },
        ],
      },
    ],
    outline: [2, 3],
  },
})
