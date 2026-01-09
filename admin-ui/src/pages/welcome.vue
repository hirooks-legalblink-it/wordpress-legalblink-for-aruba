<template>
  <v-container class="fill-height my-auto" fluid>
    <v-row align="center" class="fill-height" justify="center">
      <v-col
        cols="12"
        lg="6"
        md="6"
        sm="8"
        xl="5"
      >
        <v-card class="pa-2 pa-md-6 pa-lg-8" flat rounded="md">
          <!-- Logo Section -->
          <v-card-title class="text-center pb-4">
            <v-img
              v-if="branding?.logo"
              alt="Brand Logo"
              class="mx-auto"
              max-width="400"
              :src="branding.logo"
            />
            <v-img
              v-else
              alt="Brand Logo"
              class="mx-auto"
              max-width="400"
              src="@/assets/logo.png"
            />
          </v-card-title>

          <!-- Login Form -->
          <v-card-text>
            <!--            <v-card-subtitle class="mb-6 pa-0">
              Accedi con il tuo External ID
            </v-card-subtitle>-->
            <p class="text-h5 font-weight-bold mb-2">
              Come funziona
            </p>
            <p class="pa-0 text-body-1 mt-2 mb-8">
              Con <b>LegalBlink per Aruba</b> puoi integrare i servizi LegalBlink di Aruba sul tuo attuale sito WordPress. <br>
              Per eseguire la procedura è necessario inserire un token, che potrai generare accedendo alla piattaforma LegalBlink, dalla sezione Impostazioni. <br>
              In questo modo potrai aggiungere sul tuo sito web i documenti legali creati.
            </p>

            <v-form @submit.prevent="handleLogin">
              <v-text-field
                v-model="externalId"
                class="mb-4"
                hide-details="auto"
                label="Token"
                placeholder="Inserisci token"
                prepend-inner-icon="mdi-account-key"
                variant="outlined"
                @keyup.enter="handleLogin"
              />

              <v-btn
                block
                class="mb-4"
                color="primary"
                :disabled="!externalId || loading"
                :loading="loading"
                size="large"
                type="submit"
              >
                <v-icon start>mdi-login</v-icon>
                Accedi
              </v-btn>
            </v-form>

            <!-- Error Alert -->
            <v-alert
              v-if="errorMessage"
              closable
              :text="errorMessage"
              type="error"
              variant="tonal"
              @click:close="errorMessage = ''"
            />
          </v-card-text>

          <!-- Footer with branding if available -->
          <!--          <v-card-actions v-if="branding?.footer_text" class="justify-center">
            <v-card-text class="text-center text-caption text-medium-emphasis pa-2">
              {{ branding.footer_text }}
            </v-card-text>
          </v-card-actions>-->
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script lang="ts" setup>
  import { ref } from 'vue'
  import { authService } from '@/services'
  import { useAppStore } from '@/stores/app'

  const appStore = useAppStore()

  // Reactive data
  const externalId = ref('')
  const loading = ref(false)
  const errorMessage = ref('')

  // Computed per accedere al branding dallo store
  const branding = computed(() => appStore.getBranding)

  const handleLogin = async () => {
    if (!externalId.value.trim()) {
      errorMessage.value = 'Inserisci un External ID valido'
      return
    }

    loading.value = true
    errorMessage.value = ''

    try {
      const response = await authService.login(externalId.value.trim())
      if (response.success) {
        appStore.setAuthenticated(true)
      } else {
        errorMessage.value = 'Autenticazione fallita. Verifica l\'External ID e riprova.'
      }
    } catch {
      errorMessage.value = 'Errore durante l\'autenticazione. Verifica l\'External ID e riprova.'
    } finally {
      loading.value = false
    }
  }
</script>
