import { icons as lucideIcons } from '@iconify-json/lucide'
import { addCollection } from '@iconify/vue'
import { createApp } from 'vue'

import { api } from '~/api'
import App from '~/app.vue'
import { useVersionCheck } from '~/composables/useVersionCheck'
import { appConfig } from '~/lib/app-config'
import { installAuthHandler } from '~/plugins/auth'
import { installI18n } from '~/plugins/i18n'
import { installVueQuery } from '~/plugins/vue-query'
import { router } from '~/router'

import '~/assets/css/app.css'

/**
 * Without this the Iconify runtime fetches every icon from api.iconify.design
 * on first render. Registering the bundled set keeps rendering local, offline
 * and free of a third-party request.
 *
 * The whole lucide set costs ~97 kB gzipped and rides in its own `icons` chunk,
 * so it is cached across deploys. It is registered eagerly on purpose: the icon
 * picker takes an arbitrary name, and a lazy collection makes every icon in the
 * app shell pop in a tick late. Swap it for a curated subset if that trade stops
 * being worth it.
 */
addCollection(lucideIcons)

/**
 * Density is a shell-wide switch rather than a per-component prop, so it is
 * one attribute on <html> that the CSS reads. See `docs/app-shell.md`.
 */
document.documentElement.dataset.density = appConfig.density

const app = createApp(App)

installI18n(app)
installVueQuery(app)

// Before the router mounts: the first navigation guard already hits the
// API, and a 401 there needs a handler to redirect instead of failing silently.
installAuthHandler()

const versionCheck = useVersionCheck()
api.client.observe((response) => versionCheck.check(response.headers.get('x-app-version')))

app.use(router)
app.mount('#app')
