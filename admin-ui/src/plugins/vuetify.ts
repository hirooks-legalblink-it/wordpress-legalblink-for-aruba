/**
 * plugins/vuetify.ts
 *
 * Framework documentation: https://vuetifyjs.com`
 */

// Composables
import { createVuetify } from 'vuetify'

// Styles
import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'

export const themeConf = {
  theme: {
    defaultTheme: 'arubaLight',
    themes: {
      arubaLight: {
        dark: false,
        colors: {
          // brand
          'primary': '#1474BD',
          'primary-variant': '#0E5184',
          'secondary': '#3D4D59', // testo/ui secondario (non interattivo)
          'accent': '#ACCFEB', // focus ring / outline soffice

          // feedback
          'info': '#1183A7',
          'success': '#09800F',
          'warning': '#F5A623',
          'error': '#D0021B',

          // surfaces
          'background': '#F9F9F9',
          'surface': '#FFFFFF',
          'surface-variant': '#DDDDDD', // selezione soft (es. voce attiva)
          'outline': '#DDDDDD', // bordi e divisori

          // testo
          'on-surface': '#222222',
          'on-surface-variant': '#3D4D59',
          'placeholder': '#9B9B9B',
          'disabled': '#717171',
        },
        variables: {
          // utili per stati / componenti
          'border-radius': '4px',
          'border-radius-sm': '2px',
          'opacity-disabled': 0.6,
          // ombre toasts/modali suggerite nel documento
          'shadow-elevation-2dp': '0 0 8px rgba(34,34,34,0.16)',
        },
      },
    },
  },
}

// https://vuetifyjs.com/en/introduction/why-vuetify/#feature-guides
export default createVuetify(themeConf)
