import type { AccessibilityDeclaration, AccessibilityWidget, UpdateDeclarationPageRequest } from '@/services/AccessibilityService'
import type { BannerData } from '@/services/BannerService'
import type { Capabilities } from '@/services/CapabilityService'
import type { PolicyDocument, PolicyKey } from '@/services/DocumentService'
import type { UpdatePageRequest, WordPressPage } from '@/services/SettingsService'
// Utilities
import { defineStore } from 'pinia'
import { themeConf } from '@/plugins/vuetify.ts'
import {
  accessibilityService,
  authService,
  bannerService,
  type BrandingData,
  brandingService,
  cacheService, type CacheSettings,
  capabilityService,
  documentService,
  type Language,
  settingsService,
} from '@/services'

const defaultBrandingData: BrandingData = {
  logo: '',
  colors: {
    primary: themeConf.theme.themes.arubaLight.colors.primary,
    error: themeConf.theme.themes.arubaLight.colors.error,
    success: themeConf.theme.themes.arubaLight.colors.success,
    background: themeConf.theme.themes.arubaLight.colors.background,
    textOnPrimary: themeConf.theme.themes.arubaLight.colors['on-surface'],
    warn: themeConf.theme.themes.arubaLight.colors.warning,
  },
}

const defaultCacheSettings: CacheSettings = {
  cache_duration: 30,
}

const defaultBannerData: BannerData = {
  enabled: false,
  html: '',
}

export const useAppStore = defineStore('app', {
  state: () => ({
    branding: defaultBrandingData,
    isLoadingBranding: false,
    brandingError: null as string | null,
    isAuthenticated: false,
    isCheckingAuth: false,
    authError: null as string | null,
    languages: [] as Language[],
    isLoadingLanguages: false,
    languagesError: null as string | null,
    selectedLanguage: 'it',
    capabilities: null as Capabilities | null,
    isLoadingCapabilities: false,
    capabilitiesError: null as string | null,
    accessibilityDeclaration: null as AccessibilityDeclaration | null,
    isLoadingAccessibilityDeclaration: false,
    accessibilityDeclarationError: null as string | null,
    isUpdatingAccessibilityDeclarationPage: false,
    updateAccessibilityDeclarationPageError: null as string | null,
    accessibilityWidget: null as AccessibilityWidget | null,
    isLoadingAccessibilityWidget: false,
    accessibilityWidgetError: null as string | null,
    isSavingAccessibilityWidgetToggle: false,
    saveAccessibilityWidgetToggleError: null as string | null,
    documents: {} as Record<PolicyKey, PolicyDocument>,
    isLoadingDocuments: false,
    documentsError: null as string | null,
    wordpressPages: [] as WordPressPage[],
    isLoadingWordPressPages: false,
    wordPressPagesError: null as string | null,
    cacheSettings: defaultCacheSettings,
    isLoadingCacheSettings: false,
    cacheSettingsError: null as string | null,
    isUpdatingPage: false,
    updatePageError: null as string | null,
    cookieBannerData: defaultBannerData,
    isLoadingBanner: false,
    bannerError: null as string | null,
    isSavingBanner: false,
    saveBannerError: null as string | null,
  }),

  getters: {
    getBranding: state => state.branding,
    isLoading: state => state.isLoadingBranding,
    hasError: state => !!state.brandingError,
    getError: state => state.brandingError,
    getIsAuthenticated: state => state.isAuthenticated,
    getIsCheckingAuth: state => state.isCheckingAuth,
    getAuthError: state => state.authError,
    getLanguages: state => state.languages,
    getSelectedLanguage: state => state.selectedLanguage,
    getIsLoadingLanguages: state => state.isLoadingLanguages,
    getLanguagesError: state => state.languagesError,
    getDocuments: state => state.documents,
    getDocument: state => (type: PolicyKey) => state.documents[type] || null,
    getIsLoadingDocuments: state => state.isLoadingDocuments,
    getDocumentsError: state => state.documentsError,
    hasDocumentContent: state => (type: PolicyKey) => !!state.documents[type],
    getWordPressPages: state => state.wordpressPages,
    getCacheSettings: state => state.cacheSettings,
    getIsLoadingWordPressPages: state => state.isLoadingWordPressPages,
    getWordPressPagesError: state => state.wordPressPagesError,
    getIsUpdatingPage: state => state.isUpdatingPage,
    getUpdatePageError: state => state.updatePageError,
    getCookieBannerData: state => state.cookieBannerData,
    getIsLoadingBanner: state => state.isLoadingBanner,
    getBannerError: state => state.bannerError,
    getIsSavingBanner: state => state.isSavingBanner,
    getSaveBannerError: state => state.saveBannerError,
    getCapabilities: state => state.capabilities,
    getIsLoadingCapabilities: state => state.isLoadingCapabilities,
    getCapabilitiesError: state => state.capabilitiesError,
    isGdprEnabled: state => !!state.capabilities?.features.gdpr,
    isAccessibilityEnabled: state => !!state.capabilities?.features.accessibility,
    isAccessibilityDeclarationEnabled: state => !!state.capabilities?.features.accessibilityDeclaration,
    isAccessibilityWidgetEnabled: state => !!state.capabilities?.features.accessibilityWidget,
    isCookieBannerV2Enabled: state => !!state.capabilities?.features.cookieBannerV2,
    getAccessibilityDeclaration: state => state.accessibilityDeclaration,
    getIsLoadingAccessibilityDeclaration: state => state.isLoadingAccessibilityDeclaration,
    getAccessibilityDeclarationError: state => state.accessibilityDeclarationError,
    getAccessibilityWidget: state => state.accessibilityWidget,
    getIsLoadingAccessibilityWidget: state => state.isLoadingAccessibilityWidget,
    getAccessibilityWidgetError: state => state.accessibilityWidgetError,
    getIsSavingAccessibilityWidgetToggle: state => state.isSavingAccessibilityWidgetToggle,
  },

  actions: {
    async loadAccessibilityDeclaration () {
      this.isLoadingAccessibilityDeclaration = true
      this.accessibilityDeclarationError = null

      try {
        const response = await accessibilityService.getDeclaration()
        if (response.success && response.data) {
          this.accessibilityDeclaration = response.data
        } else {
          this.accessibilityDeclaration = null
          this.accessibilityDeclarationError = response.errors?.[0]
            || response.message
            || 'Errore nel caricamento della dichiarazione di accessibilità'
        }
      } catch (error) {
        this.accessibilityDeclaration = null
        this.accessibilityDeclarationError = error instanceof Error
          ? error.message
          : 'Errore nel caricamento della dichiarazione di accessibilità'
      } finally {
        this.isLoadingAccessibilityDeclaration = false
      }
    },

    async updateAccessibilityDeclarationPage (request: UpdateDeclarationPageRequest) {
      this.isUpdatingAccessibilityDeclarationPage = true
      this.updateAccessibilityDeclarationPageError = null

      try {
        const response = await accessibilityService.updateDeclarationPage(request)
        if (!response.success) {
          throw new Error(response.errors?.[0] || response.message || 'Errore nell\'aggiornamento della pagina')
        }
        // Refresh so pageId/useHtmlSnippet reflect the new option state.
        await this.loadAccessibilityDeclaration()
        return response.data
      } catch (error) {
        this.updateAccessibilityDeclarationPageError = error instanceof Error
          ? error.message
          : 'Errore nell\'aggiornamento della pagina'
        throw error
      } finally {
        this.isUpdatingAccessibilityDeclarationPage = false
      }
    },

    async loadAccessibilityWidget () {
      this.isLoadingAccessibilityWidget = true
      this.accessibilityWidgetError = null

      try {
        const response = await accessibilityService.getWidget()
        if (response.success && response.data) {
          this.accessibilityWidget = response.data
        } else {
          this.accessibilityWidget = null
          this.accessibilityWidgetError = response.errors?.[0]
            || response.message
            || 'Errore nel caricamento del widget di accessibilità'
        }
      } catch (error) {
        this.accessibilityWidget = null
        this.accessibilityWidgetError = error instanceof Error
          ? error.message
          : 'Errore nel caricamento del widget di accessibilità'
      } finally {
        this.isLoadingAccessibilityWidget = false
      }
    },

    async saveAccessibilityWidgetToggle (enabled: boolean) {
      this.isSavingAccessibilityWidgetToggle = true
      this.saveAccessibilityWidgetToggleError = null

      try {
        const response = await accessibilityService.setWidgetEnabled(enabled)
        if (!response.success) {
          throw new Error(response.errors?.[0] || response.message || 'Errore nel salvataggio del toggle widget')
        }
        if (this.accessibilityWidget) {
          this.accessibilityWidget = { ...this.accessibilityWidget, localEnabled: enabled }
        }
        return response.data
      } catch (error) {
        this.saveAccessibilityWidgetToggleError = error instanceof Error
          ? error.message
          : 'Errore nel salvataggio del toggle widget'
        throw error
      } finally {
        this.isSavingAccessibilityWidgetToggle = false
      }
    },

    async loadCapabilities () {
      this.isLoadingCapabilities = true
      this.capabilitiesError = null

      try {
        const response = await capabilityService.getCapabilities()
        if (response.success && response.data) {
          this.capabilities = response.data
        } else {
          this.capabilities = null
          this.capabilitiesError = response.errors?.[0] || response.message || 'Errore nel caricamento delle capability'
        }
      } catch (error) {
        this.capabilities = null
        this.capabilitiesError = error instanceof Error ? error.message : 'Errore nel caricamento delle capability'
      } finally {
        this.isLoadingCapabilities = false
      }
    },

    async loadBranding () {
      this.isLoadingBranding = true
      this.brandingError = null

      try {
        const response = await brandingService.getBranding()
        this.branding = response.success && response.data ? response.data : defaultBrandingData
      } catch (error) {
        this.brandingError = error instanceof Error ? error.message : 'Errore nel caricamento del branding'
        this.branding = defaultBrandingData
      } finally {
        this.isLoadingBranding = false
      }
    },

    async loadLanguages () {
      this.isLoadingLanguages = true
      this.languagesError = null

      try {
        const response = await documentService.getLanguages()
        const languages = response.success && response.data ? response.data.data || [] : []

        if (Array.isArray(languages) && languages.length > 0) {
          this.languages = languages as Language[]
        }

        // Imposta la lingua predefinita se non è già impostata
        if (this.languages.length > 0 && !this.selectedLanguage) {
          this.selectedLanguage = this.languages[0].code
        }
      } catch (error) {
        this.languagesError = error instanceof Error ? error.message : 'Errore nel caricamento delle lingue'
        this.languages = []
      } finally {
        this.isLoadingLanguages = false
      }
    },

    async loadDocuments () {
      this.isLoadingDocuments = true
      this.documentsError = null

      try {
        const response = await documentService.getAllDocuments()

        this.documents = response.success && response.data?.data ? response.data.data : {} as Record<PolicyKey, PolicyDocument>
      } catch (error) {
        this.documentsError = error instanceof Error ? error.message : 'Errore nel caricamento dei documenti'
        this.documents = {} as Record<PolicyKey, PolicyDocument>
      } finally {
        this.isLoadingDocuments = false
      }
    },

    async loadWordPressPages () {
      this.isLoadingWordPressPages = true
      this.wordPressPagesError = null

      try {
        const response = await settingsService.getWordPressPages()
        this.wordpressPages = response.success ? response.data?.pages || [] : []
      } catch (error) {
        this.wordPressPagesError = error instanceof Error ? error.message : 'Errore nel caricamento delle pagine WordPress'
        this.wordpressPages = []
      } finally {
        this.isLoadingWordPressPages = false
      }
    },

    async loadCacheSettings () {
      this.isLoadingCacheSettings = true
      this.cacheSettingsError = null

      try {
        const response = await cacheService.getSettings()
        this.cacheSettings = response.success && response.data ? response.data : defaultCacheSettings
      } catch (error) {
        this.cacheSettingsError = error instanceof Error ? error.message : 'Errore nel caricamento delle impostazioni della cache'
        this.cacheSettings = defaultCacheSettings
      } finally {
        this.isLoadingCacheSettings = false
      }
    },

    async loadCookieBannerData () {
      this.isLoadingBanner = true
      this.bannerError = null

      try {
        const response = await bannerService.getBannerData()
        this.cookieBannerData = response.success && response.data ? response.data : defaultBannerData
      } catch (error) {
        this.bannerError = error instanceof Error ? error.message : 'Errore nel caricamento dei dati del banner'
        this.cookieBannerData = defaultBannerData
      } finally {
        this.isLoadingBanner = false
      }
    },

    async saveCookieBannerData (enabled: boolean, html: string) {
      this.isSavingBanner = true
      this.saveBannerError = null

      try {
        const response = await bannerService.setBannerData(enabled)

        if (response.success) {
          this.cookieBannerData = { enabled, html }
          return true
        } else {
          throw new Error(response.message || 'Errore nel salvataggio del banner')
        }
      } catch (error) {
        this.saveBannerError = error instanceof Error ? error.message : 'Errore nel salvataggio del banner'
        throw error
      } finally {
        this.isSavingBanner = false
      }
    },

    async updatePageContent (request: UpdatePageRequest) {
      this.isUpdatingPage = true
      this.updatePageError = null

      try {
        const response = await settingsService.updatePageContent(request)

        if (response.success && response.data) {
          return response.data
        } else {
          throw new Error(response.message || 'Errore nell\'aggiornamento della pagina')
        }
      } catch (error) {
        this.updatePageError = error instanceof Error ? error.message : 'Errore nell\'aggiornamento della pagina'
        throw error
      } finally {
        this.isUpdatingPage = false
      }
    },

    async checkAuthStatus () {
      this.isCheckingAuth = true
      this.authError = null

      try {
        const response = await authService.isLoggedIn()
        this.isAuthenticated = response.success
        return this.isAuthenticated
      } catch (error) {
        this.authError = error instanceof Error ? error.message : 'Errore nella verifica dell\'autenticazione'
        this.isAuthenticated = false
        return false
      } finally {
        this.isCheckingAuth = false
      }
    },

    async setAuthenticated (status: boolean) {
      this.isAuthenticated = status

      if (!status) {
        this.clearAll()
        return
      }

      // Always load branding/languages/cache/capabilities/pages — these are needed
      // regardless of the GDPR/accessibility feature mix.
      this.loadBranding().then()
      this.loadLanguages().then()
      this.loadCacheSettings().then()
      this.loadWordPressPages().then()

      // Capabilities must be resolved before any feature-specific loader so we
      // never fetch GDPR documents for an accessibility-only account or vice
      // versa (S#7701: capability-driven gating, no inference from documents).
      await this.loadCapabilities()

      if (this.isGdprEnabled) {
        this.loadDocuments().then()
        this.loadCookieBannerData().then()
      } else {
        this.clearDocuments()
        this.clearBanner()
      }

      if (this.isAccessibilityDeclarationEnabled) {
        this.loadAccessibilityDeclaration().then()
      } else {
        this.clearAccessibilityDeclaration()
      }

      if (this.isAccessibilityWidgetEnabled) {
        this.loadAccessibilityWidget().then()
      } else {
        this.clearAccessibilityWidget()
      }
    },

    setSelectedLanguage (language: string) {
      this.selectedLanguage = language
    },

    clearBranding () {
      this.branding = defaultBrandingData
      this.brandingError = null
      this.isLoadingBranding = false
    },

    clearAuth () {
      this.isAuthenticated = false
      this.authError = null
      this.isCheckingAuth = false
    },

    clearLanguages () {
      this.languages = []
      this.languagesError = null
      this.isLoadingLanguages = false
      this.selectedLanguage = 'it'
    },

    clearDocuments () {
      this.documents = {} as Record<PolicyKey, PolicyDocument>
      this.documentsError = null
      this.isLoadingDocuments = false
    },

    clearWordPressPages () {
      this.wordpressPages = []
      this.wordPressPagesError = null
      this.isLoadingWordPressPages = false
    },

    clearCacheSettings () {
      this.cacheSettings = defaultCacheSettings
      this.cacheSettingsError = null
      this.isLoadingCacheSettings = false
    },

    clearBanner () {
      this.cookieBannerData = defaultBannerData
      this.bannerError = null
      this.isLoadingBanner = false
      this.isSavingBanner = false
      this.saveBannerError = null
    },

    clearCapabilities () {
      this.capabilities = null
      this.capabilitiesError = null
      this.isLoadingCapabilities = false
    },

    clearAccessibilityDeclaration () {
      this.accessibilityDeclaration = null
      this.accessibilityDeclarationError = null
      this.isLoadingAccessibilityDeclaration = false
      this.isUpdatingAccessibilityDeclarationPage = false
      this.updateAccessibilityDeclarationPageError = null
    },

    clearAccessibilityWidget () {
      this.accessibilityWidget = null
      this.accessibilityWidgetError = null
      this.isLoadingAccessibilityWidget = false
      this.isSavingAccessibilityWidgetToggle = false
      this.saveAccessibilityWidgetToggleError = null
    },

    clearAll () {
      this.clearAuth()
      this.clearBranding()
      this.clearLanguages()
      this.clearDocuments()
      this.clearWordPressPages()
      this.clearCacheSettings()
      this.clearBanner()
      this.clearCapabilities()
      this.clearAccessibilityDeclaration()
      this.clearAccessibilityWidget()
    },
  },
})
