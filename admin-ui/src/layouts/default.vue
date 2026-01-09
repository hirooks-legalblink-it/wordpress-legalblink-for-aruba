<template>
  <v-app>
    <v-app-bar
      v-if="route.name === '/'"
      absolute
      class="px-4"
      color="transparent"
      flat
    >
      <v-spacer />
      <v-btn
        color="error"
        prepend-icon="mdi-logout"
        size="small"
        variant="outlined"
        @click="logout"
      >
        Esci
      </v-btn>
    </v-app-bar>

    <v-main>
      <router-view />
    </v-main>
  </v-app>
</template>

<script lang="ts" setup>
  import { useRoute } from 'vue-router'
  import { authService } from '@/services'
  import { useAppStore } from '@/stores/app'

  const route = useRoute()

  const appStore = useAppStore()

  // Funzione per logout
  async function logout () {
    try {
      // Chiamata al servizio per effettuare il logout server-side
      const response = await authService.logout()

      if (response.success) {
        // Aggiorna lo stato nello store
        appStore.setAuthenticated(false)
      } else {
        // Aggiorna comunque lo stato e forza il redirect
        appStore.setAuthenticated(false)
      }
    } catch (error) {
      console.error('Errore inatteso durante il logout:', error)
      // Aggiorna comunque lo stato e forza il redirect
      appStore.setAuthenticated(false)
    }
  }
</script>
