<template>
  <v-card border="sm" flat>
    <v-card-title class="d-flex align-center">
      Informazioni generali
    </v-card-title>

    <v-card-text>
      <v-row align="center" class="mb-4">
        <v-col cols="12" md="6">
          <div class="d-flex align-center">
            <label class="font-weight-bold" for="cookie-banner-switch">Mostra il cookie banner in tutte le pagine del sito</label>
            <v-tooltip contained max-width="400" role="tooltip">
              <template #activator="{ props: tooltipProps }">
                <v-icon
                  v-bind="tooltipProps"
                  class="ml-2"
                  color="info"
                  icon="mdi-information-outline"
                  size="16"
                />
              </template>
              <template #default>
                Consente di visualizzare il cookie banner su tutte le pagine del sito. <br>
                In questo modo gli utenti vedranno sempre l'avviso e potranno gestire le loro preferenze sui cookie, in conformità con il GDPR.
              </template>
            </v-tooltip>
          </div>
        </v-col>
        <v-col cols="12" md="6">
          <div class="d-flex justify-end">
            <v-switch
              id="cookie-banner-switch"
              v-model="showBanner"
              color="primary"
              hide-details
              inset
            />
          </div>
        </v-col>
      </v-row>

      <v-row class="mb-4">
        <v-col cols="12">
          <div class="d-flex align-center">
            <label class="font-weight-bold" for="cookie-banner-snippet">Codice del banner HTML</label>
            <v-tooltip contained max-width="400" role="tooltip">
              <template #activator="{ props: tooltipProps }">
                <v-icon
                  v-bind="tooltipProps"
                  class="ml-2"
                  color="info"
                  icon="mdi-information-outline"
                  size="16"
                />
              </template>
              <template #default>
                È il codice HTML del cookie banner generato automaticamente. <br>
                Può essere copiato e utilizzato per integrare manualmente il banner nelle pagine del sito.
              </template>
            </v-tooltip>
          </div>
        </v-col>
        <v-col cols="12">
          <v-row>
            <v-col class="d-flex align-center justify-center" cols="12" md="1">
              <v-btn
                color="primary"
                icon="mdi-content-copy"
                size="small"
                variant="flat"
                @click="copyBannerCode"
              />
            </v-col>
            <v-col cols="12" md="11">
              <v-sheet class="pa-3 text-caption word-break-all bg-outline" rounded>
                {{ cookieBannerCode }}
              </v-sheet>
            </v-col>
          </v-row>
        </v-col>
      </v-row>
    </v-card-text>

    <v-divider class="mb-4 bg-outline mx-auto border-opacity-100" thickness="1" />

    <v-card-actions class="px-4 pb-4">
      <v-btn
        color="primary"
        :loading="saving"
        variant="flat"
        @click="saveSettings"
      >
        Salva
      </v-btn>
      <v-spacer />
    </v-card-actions>

    <!-- Success Snackbars -->
    <v-snackbar
      v-model="showSuccess"
      color="success"
      location="top"
      timeout="2000"
    >
      <v-icon class="mr-2" icon="mdi-check-circle" />
      Impostazioni salvate con successo
    </v-snackbar>

    <v-snackbar
      v-model="showCopySuccess"
      color="info"
      location="top"
      timeout="2000"
    >
      <v-icon class="mr-2" icon="mdi-content-copy" />
      Codice copiato negli appunti
    </v-snackbar>
  </v-card>
</template>

<script setup lang="ts">
  import { computed, ref } from 'vue'
  import { useAppStore } from '@/stores/app'

  const store = useAppStore()
  const showSuccess = ref(false)
  const showCopySuccess = ref(false)
  const showBanner = computed({
    get: () => store.cookieBannerData.enabled,
    set: value => store.cookieBannerData.enabled = value,
  })

  const cookieBannerCode = computed(() => store.getCookieBannerData.html)
  const saving = computed(() => store.getIsSavingBanner)

  async function saveSettings () {
    try {
      await store.saveCookieBannerData(showBanner.value, cookieBannerCode.value)
      showSuccess.value = true
    } catch {
      showSuccess.value = false
    }
  }

  async function copyBannerCode () {
    if (cookieBannerCode.value) {
      await navigator.clipboard.writeText(cookieBannerCode.value)
    }
    showCopySuccess.value = true
  }
</script>
