<template>
  <!-- Prima riga: Blocco superiore con card e tabs - Full width -->
  <v-row class="bg-surface ma-0">
    <v-col class="pa-0" cols="12">
      <v-container class="pa-0 justify-center w-100 px-4" fluid max-width="900">
        <v-card
          class="text-left mb-6"
          flat
        >
          <v-card-text class="pa-0">
            <a href="#" rel="noopener" target="_blank">
              <v-img
                v-if="branding.logo && branding.logo.length > 0"
                alt="Brand Logo"
                max-width="400"
                :src="branding.logo"
              />
              <v-img
                v-else
                alt="Brand Logo"
                max-width="400"
                src="@/assets/logo.png"
              />
            </a>
            <v-card-title class="pa-0 text-h5 text-wrap mb-4">
              Il primo generatore di documenti legali per il tuo sito
            </v-card-title>
            <p class="pa-0 text-body-1 mb-2">
              Con LegalBlink per Aruba non dovrai più preoccuparti delle questioni legali perché hai un team di professionisti esperti in diritto digitale ad affiancare la tua impresa.
            </p>
            <p class="pa-0 text-body-1">
              LegalBlink per Aruba genera condizioni generali di vendita, privacy policy e cookie policy per mettere a norma il tuo sito vetrina o e-commerce.
            </p>
          </v-card-text>
        </v-card>

        <v-tabs v-model="tab" color="primary" grow show-arrows>
          <v-tab v-for="item in tabs" :key="item.value" :value="item.value">
            {{ item.label }}
          </v-tab>
        </v-tabs>
      </v-container>
    </v-col>
  </v-row>

  <!-- Seconda riga: Contenuto principale - Full width -->
  <v-row>
    <v-col cols="12">
      <!-- Container per il resto del contenuto -->
      <v-container class="pa-0 justify-center w-100 px-4" fluid max-width="900">
        <v-alert
          v-if="serviceStatusError"
          class="ma-4"
          type="error"
          variant="tonal"
        >
          {{ serviceStatusError }}
        </v-alert>

        <v-alert
          v-else-if="capabilitiesError"
          class="ma-4"
          type="error"
          variant="tonal"
        >
          {{ capabilitiesError }}
        </v-alert>

        <template v-else>
          <!-- Seconda riga: Blocco contenuto sottostante -->

          <v-window v-model="tab">
            <v-window-item value="cookie_banner">
              <CookieBannerSettingsCard />
            </v-window-item>

            <v-window-item value="cookie_policy">
              <PolicySettingsCard
                :policy-page="cookiePolicyPage"
                :policy-pages="policyPages"
                policy-type="cookie_policy"
                :policy-url="cookiePolicyUrl"
                :shortcode="shortcode"
                title="Impostazioni sulla cookie policy"
                :use-html-snippet="useHtmlSnippet"
                @page-updated="handlePageUpdated"
                @save="saveCookiePolicySettings"
                @update:policy-page="cookiePolicyPage = $event"
                @update:use-html-snippet="useHtmlSnippet = $event"
              />
            </v-window-item>

            <v-window-item value="privacy_policy">
              <PolicySettingsCard
                :policy-page="privacyPolicyPage"
                :policy-pages="policyPages"
                policy-type="privacy_policy"
                :policy-url="privacyPolicyUrl"
                :set-as-default="setAsDefaultPrivacyPage"
                :shortcode="privacyShortcode"
                title="Impostazioni sulla privacy policy"
                :use-html-snippet="usePrivacyHtmlSnippet"
                @page-updated="handlePageUpdated"
                @save="savePrivacyPolicySettings"
                @update:policy-page="privacyPolicyPage = $event"
                @update:use-html-snippet="usePrivacyHtmlSnippet = $event"
              />
            </v-window-item>
            <v-window-item value="terms_of_service">
              <PolicySettingsCard
                :policy-page="cgvPolicyPage"
                :policy-pages="policyPages"
                policy-type="terms_of_service"
                :policy-url="cgvPolicyUrl"
                :shortcode="cgvShortcode"
                title="Impostazioni delle Condizioni Generali di Vendita"
                :use-html-snippet="useCgvHtmlSnippet"
                @page-updated="handlePageUpdated"
                @save="saveCgvPolicySettings"
                @update:policy-page="cgvPolicyPage = $event"
                @update:use-html-snippet="useCgvHtmlSnippet = $event"
              />
            </v-window-item>

            <v-window-item value="accessibility_declaration">
              <PolicySettingsCard
                :policy-page="accessibilityDeclarationPage"
                :policy-pages="policyPages"
                policy-type="accessibility_declaration"
                :policy-url="accessibilityDeclarationUrl"
                :shortcode="accessibilityDeclarationShortcode"
                title="Impostazioni della dichiarazione di accessibilità"
                :use-html-snippet="useAccessibilityDeclarationHtmlSnippet"
                @page-updated="handlePageUpdated"
                @save="saveAccessibilityDeclarationSettings"
                @update:policy-page="accessibilityDeclarationPage = $event"
                @update:use-html-snippet="useAccessibilityDeclarationHtmlSnippet = $event"
              />
            </v-window-item>

            <v-window-item value="accessibility_widget">
              <AccessibilityWidgetSettingsCard
                :local-enabled="store.accessibilityWidget?.localEnabled ?? false"
                :saving="store.getIsSavingAccessibilityWidgetToggle"
                :widget="store.accessibilityWidget"
                @save="saveAccessibilityWidgetSettings"
                @update:enabled="handleAccessibilityWidgetToggle"
              />
            </v-window-item>

            <v-window-item value="cache">
              <CacheSettingsCard
                :cache-duration="cacheDuration"
                @clear-cache="clearCache"
                @save="saveCacheSettings"
                @update:cache-duration="cacheDuration = $event"
              />
            </v-window-item>
          </v-window>
        </template>
      </v-container>
    </v-col>
  </v-row>

  <!-- Snackbar unificato per tutti i messaggi -->
  <v-snackbar
    v-model="snackbar.show"
    :color="snackbar.color"
    location="top"
    :timeout="snackbar.timeout"
  >
    <v-icon
      :icon="getSnackbarIcon(snackbar.color)"
      start
    />
    {{ snackbar.message }}

    <template #actions>
      <v-btn
        color="white"
        variant="text"
        @click="snackbar.show = false"
      >
        Chiudi
      </v-btn>
    </template>
  </v-snackbar>
</template>

<script lang="ts" setup>
  import { computed, ref, watch } from 'vue'
  import { cacheService, type WordPressPage } from '@/services'
  import { useAppStore } from '@/stores/app'

  const store = useAppStore()

  const branding = computed(() => store.getBranding)
  const serviceStatusError = computed(() => store.getError)
  const capabilitiesError = computed(() => store.getCapabilitiesError)
  const selectedLanguage = computed(() => store.getSelectedLanguage)
  const cookiePolicyUrl = computed(() => {
    const document = store.getDocument('cookie_policy')
    return document?.languages?.[selectedLanguage.value]?.url.html || ''
  })
  const privacyPolicyUrl = computed(() => {
    const document = store.getDocument('privacy_policy')
    return document?.languages?.[selectedLanguage.value]?.url.html || ''
  })
  const cgvPolicyUrl = computed(() => {
    const document = store.getDocument('terms_of_service')
    return document?.languages?.[selectedLanguage.value]?.url.html || ''
  })
  const accessibilityDeclarationUrl = computed(() => {
    const declaration = store.getAccessibilityDeclaration
    if (!declaration?.available || !declaration.document) return ''
    return declaration.document.languages?.[selectedLanguage.value]?.url.html || ''
  })

  const snackbar = ref({
    show: false,
    message: '',
    color: 'success',
    timeout: 3000,
  })

  function showMessage (message: string, color: 'success' | 'error' | 'info' = 'success', timeout = 3000) {
    snackbar.value = {
      show: true,
      message,
      color,
      timeout,
    }
  }

  function getSnackbarIcon (color: string): string {
    switch (color) {
      case 'success': {
        return 'mdi-check-circle'
      }
      case 'error': {
        return 'mdi-alert-circle'
      }
      case 'info': {
        return 'mdi-information'
      }
      default: {
        return 'mdi-check-circle'
      }
    }
  }

  const tab = ref('cookie_banner')

  const allTabs = [
    { label: 'Cookie banner', value: 'cookie_banner' },
    { label: 'Cookie policy', value: 'cookie_policy' },
    { label: 'Privacy policy', value: 'privacy_policy' },
    { label: 'Informativa CGV', value: 'terms_of_service' },
    { label: 'Dichiarazione accessibilità', value: 'accessibility_declaration' },
    { label: 'Widget accessibilità', value: 'accessibility_widget' },
    { label: 'Cache', value: 'cache' },
  ]

  // S#7701 capability-driven gating: tab visibility derives from
  // `store.capabilities` (resolved post-auth via `loadCapabilities`), not from
  // the GDPR document whitelist. Accessibility tabs land in follow-up PRs and
  // are gated on `features.accessibilityDeclaration` / `accessibilityWidget`.
  const tabs = computed(() => {
    return allTabs.filter(tab => {
      if (tab.value === 'cache') {
        return true
      }

      if (tab.value === 'cookie_banner') {
        return store.isGdprEnabled
      }

      if (tab.value === 'cookie_policy' || tab.value === 'privacy_policy' || tab.value === 'terms_of_service') {
        if (!store.isGdprEnabled) {
          return false
        }
        return store.getDocument(tab.value as any) !== null
      }

      if (tab.value === 'accessibility_declaration') {
        return store.isAccessibilityDeclarationEnabled
      }

      if (tab.value === 'accessibility_widget') {
        return store.isAccessibilityWidgetEnabled
      }

      return false
    })
  })

  watch(tabs, newTabs => {
    const currentTabExists = newTabs.some(t => t.value === tab.value)
    if (!currentTabExists && newTabs.length > 0) {
      tab.value = newTabs[0].value
    }
  }, { immediate: true })

  const useHtmlSnippet = ref(false)
  const cookiePolicyPage = ref<string | null>(null)
  const shortcode = '[LBFA_COOKIE_POLICY]'

  const usePrivacyHtmlSnippet = ref(false)
  const privacyPolicyPage = ref<string | null>(null)
  const setAsDefaultPrivacyPage = ref(false)
  const privacyShortcode = '[LBFA_PRIVACY_POLICY]'

  const useCgvHtmlSnippet = ref(false)
  const cgvPolicyPage = ref<string | null>(null)
  const cgvShortcode = '[LBFA_CGV_POLICY]'

  const useAccessibilityDeclarationHtmlSnippet = ref(false)
  const accessibilityDeclarationPage = ref<string | null>(null)
  const accessibilityDeclarationShortcode = '[LBFA_ACCESSIBILITY_DECLARATION]'

  const cacheDuration = computed({
    get: () => store.cacheSettings.cache_duration || 30,
    set: value => store.cacheSettings.cache_duration = value,
  })

  function saveCookiePolicySettings () {
    showMessage('Impostazioni cookie policy salvate!')
  }

  function savePrivacyPolicySettings () {
    showMessage('Impostazioni privacy policy salvate!')
  }

  function saveCgvPolicySettings () {
    showMessage('Impostazioni CGV salvate!')
  }

  function saveAccessibilityDeclarationSettings () {
    showMessage('Impostazioni dichiarazione di accessibilità salvate!')
  }

  async function handleAccessibilityWidgetToggle (enabled: boolean) {
    try {
      await store.saveAccessibilityWidgetToggle(enabled)
    } catch (error) {
      console.error('Errore salvataggio toggle widget accessibilità:', error)
      showMessage('Errore nel salvataggio del toggle widget di accessibilità', 'error')
    }
  }

  function saveAccessibilityWidgetSettings () {
    showMessage('Impostazioni widget di accessibilità salvate!')
  }

  async function saveCacheSettings () {
    try {
      const response = await cacheService.updateSettings(cacheDuration.value)
      if (response.success) {
        showMessage(response.message || 'Impostazioni cache salvate!')
      } else {
        showMessage(response.errors?.[0] || 'Errore nel salvataggio delle impostazioni cache', 'error')
      }
    } catch (error) {
      console.error('Errore nel salvataggio cache:', error)
      showMessage('Errore inatteso nel salvataggio delle impostazioni cache', 'error')
    } finally {
      await store.loadCacheSettings()
    }
  }

  async function clearCache () {
    try {
      const response = await cacheService.clearCache()
      if (response.success) {
        showMessage(response.message || 'Cache eliminata!', 'info')
      } else {
        showMessage(response.errors?.[0] || 'Errore nella pulizia della cache', 'error')
      }
    } catch (error) {
      console.error('Errore nella pulizia cache:', error)
      showMessage('Errore inatteso nella pulizia della cache', 'error')
    }
  }

  const policyPages = computed(() => {
    const pages = store.getWordPressPages
    return [
      { title: 'Nessuna pagina selezionata', value: null },
      ...pages.map((page: WordPressPage) => ({
        title: page.title,
        value: page.id.toString(),
      })),
    ]
  })

  function handlePageUpdated (result: any) {
    showMessage(result.message || 'Pagina aggiornata con successo!', 'success')
  }

  watchEffect(() => {
    const documents = store.getDocuments
    const currentLanguage = selectedLanguage.value

    // Cookie Policy
    const cookieDoc = documents.cookie_policy
    if (cookieDoc?.languages?.[currentLanguage]) {
      cookiePolicyPage.value = cookieDoc.languages[currentLanguage].pageId
      useHtmlSnippet.value = cookieDoc.languages[currentLanguage].useHtmlSnippet
    }

    // Privacy Policy
    const privacyDoc = documents.privacy_policy
    if (privacyDoc?.languages?.[currentLanguage]) {
      privacyPolicyPage.value = privacyDoc.languages[currentLanguage].pageId
      usePrivacyHtmlSnippet.value = privacyDoc.languages[currentLanguage].useHtmlSnippet
    }

    // Terms of Service (CGV)
    const cgvDoc = documents.terms_of_service
    if (cgvDoc?.languages?.[currentLanguage]) {
      cgvPolicyPage.value = cgvDoc.languages[currentLanguage].pageId
      useCgvHtmlSnippet.value = cgvDoc.languages[currentLanguage].useHtmlSnippet
    }

    // Accessibility declaration (S#7701 mixed-mode)
    const declaration = store.getAccessibilityDeclaration
    const declarationLang = declaration?.document?.languages?.[currentLanguage]
    if (declarationLang) {
      accessibilityDeclarationPage.value = declarationLang.pageId
      useAccessibilityDeclarationHtmlSnippet.value = declarationLang.useHtmlSnippet
    }
  })
</script>
