import { createApp } from 'vue'

import { api } from '~/api'
import App from '~/app.vue'
import { useVersionCheck } from '~/composables/useVersionCheck'
import { installAuthHandler } from '~/plugins/auth'
import { installI18n } from '~/plugins/i18n'
import { installVueQuery } from '~/plugins/vue-query'
import { router } from '~/router'

import '~/assets/css/app.css'

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
