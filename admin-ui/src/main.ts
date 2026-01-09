/**
 * main.ts
 *
 * Bootstraps Vuetify and other plugins then mounts the App`
 */

// Composables
import { createApp } from 'vue'
import { createI18n } from 'vue-i18n'

// Plugins
import { registerPlugins } from '@/plugins'

// Components
import App from './App.vue'

// Styles
/* import 'unfonts.css' */

const app = createApp(App)

const i18n = createI18n({
  // something vue-i18n options here ...
})

registerPlugins(app)

app.use(i18n)
app.mount('#lbfa_app')
