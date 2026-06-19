<template>
  <v-app>
    <v-main>
      <!-- Loading screen durante il controllo di autenticazione iniziale -->
      <div v-if="isInitialLoading" class="loading-screen">
        <v-container class="fill-height">
          <v-row align="center" justify="center">
            <v-col class="text-center" cols="12">
              <v-progress-circular
                color="primary"
                indeterminate
                :size="70"
                :width="7"
              />
              <v-card-title class="mt-4 justify-center">
                Caricamento in corso...
              </v-card-title>
              <!-- <v-card-subtitle class="justify-center">
                Verifica dell'autenticazione e caricamento dei dati
              </v-card-subtitle>-->
            </v-col>
          </v-row>
        </v-container>
      </div>
      <!-- Router view solo dopo il controllo iniziale -->
      <router-view v-else />
    </v-main>
  </v-app>
</template>

<script lang="ts" setup>
  import { useTheme } from 'vuetify'
  import { useAppStore } from '@/stores/app'

  const theme = useTheme()
  const router = useRouter()
  const route = useRoute()
  const store = useAppStore()

  // Stato per il caricamento iniziale
  const isInitialLoading = ref(true)

  // Computed per accedere al branding dallo store
  const branding = computed(() => store.getBranding)
  const isAuthenticated = computed(() => store.getIsAuthenticated)

  // Applica i colori del branding al tema quando il branding cambia
  watchEffect(() => {
    if (!branding.value) return

    theme.themes.value.arubaLight.colors.primary = branding.value.colors.primary
    theme.themes.value.arubaLight.colors.success = branding.value.colors.success
    theme.themes.value.arubaLight.colors.error = branding.value.colors.error
    theme.themes.value.arubaLight.colors.warning = branding.value.colors.warn
    theme.themes.value.arubaLight.colors['on-surface'] = branding.value.colors.textOnPrimary
    theme.themes.value.arubaLight.colors.background = branding.value.colors.background
  })

  // Gestione del routing basato sull'autenticazione (solo dopo il caricamento iniziale)
  watchEffect(() => {
    if (isInitialLoading.value) return // Non fare routing durante il caricamento iniziale

    const currentPath = route.path
    const isWelcomePage = currentPath === '/welcome'

    if (isAuthenticated.value && isWelcomePage) {
      // Utente autenticato sulla pagina welcome -> reindirizza alla dashboard
      router.push('/')
    } else if (!isAuthenticated.value && !isWelcomePage) {
      // Utente non autenticato su pagine protette -> reindirizza al login
      router.push('/welcome')
    }
  })

  // Carica il branding, lingue, documenti e verifica l'autenticazione all'avvio dell'app
  onMounted(async () => {
    try {
      // Prima verifica l'autenticazione
      await store.checkAuthStatus()

      if (isAuthenticated.value) {
        // S#7701 capability-driven bootstrap: setAuthenticated(true) loads
        // capabilities first and only then triggers the feature-specific
        // loaders (GDPR docs/banner only if features.gdpr is true,
        // accessibility declaration/widget only if their flags are true).
        // Calling the individual loaders here would skip loadCapabilities
        // and leave every tab hidden except `cache` after a refresh.
        await store.setAuthenticated(true)
      } else {
        // Unauthenticated: only the welcome page needs branding + languages.
        await Promise.all([
          store.loadBranding(),
          store.loadLanguages(),
        ])
      }

      // Dopo aver caricato tutto, naviga alla pagina appropriata
      const currentPath = route.path
      const isWelcomePage = currentPath === '/welcome'

      if (isAuthenticated.value && isWelcomePage) {
        // Utente autenticato sulla pagina welcome -> reindirizza alla dashboard
        router.push('/')
      } else if (!isAuthenticated.value && !isWelcomePage) {
        // Utente non autenticato su pagine protette -> reindirizza al login
        router.push('/welcome')
      }
    } catch (error) {
      console.error('Errore durante il caricamento iniziale:', error)
      // In caso di errore, vai alla pagina welcome per sicurezza
      router.push('/welcome')
    } finally {
      isInitialLoading.value = false
    }
  })
</script>

<style scoped>
.loading-screen {
  width: 100%;
  height: 100%;
  background-color: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(2px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
}

.loading-screen .v-container {
  max-width: 400px;
}

.loading-screen .v-progress-circular {
  margin-bottom: 1rem;
}
</style>
