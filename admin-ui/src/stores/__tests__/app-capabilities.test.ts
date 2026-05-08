import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

// vi.mock is hoisted above const declarations, so plain references would be
// undefined at mock-factory time. vi.hoisted lifts the fns up alongside it.
const {
  loadCapabilitiesMock,
  loadDocumentsMock,
  loadCookieBannerMock,
  loadBrandingMock,
  loadLanguagesMock,
  loadCacheMock,
  loadPagesMock,
} = vi.hoisted(() => ({
  loadCapabilitiesMock: vi.fn(),
  loadDocumentsMock: vi.fn().mockResolvedValue(undefined),
  loadCookieBannerMock: vi.fn().mockResolvedValue(undefined),
  loadBrandingMock: vi.fn().mockResolvedValue(undefined),
  loadLanguagesMock: vi.fn().mockResolvedValue(undefined),
  loadCacheMock: vi.fn().mockResolvedValue(undefined),
  loadPagesMock: vi.fn().mockResolvedValue(undefined),
}))

vi.mock('@/services', async () => {
  const actual = await vi.importActual<typeof import('@/services')>('@/services')
  return {
    ...actual,
    capabilityService: { getCapabilities: loadCapabilitiesMock },
    brandingService: { getBranding: loadBrandingMock },
    documentService: {
      getAllDocuments: loadDocumentsMock,
      getLanguages: loadLanguagesMock,
    },
    bannerService: { getBannerData: loadCookieBannerMock, setBannerData: vi.fn() },
    cacheService: { getSettings: loadCacheMock },
    settingsService: { getWordPressPages: loadPagesMock, updatePageContent: vi.fn() },
    authService: { isLoggedIn: vi.fn(), login: vi.fn(), logout: vi.fn() },
  }
})

vi.mock('@/plugins/vuetify.ts', () => ({
  themeConf: {
    theme: {
      themes: {
        arubaLight: {
          colors: {
            primary: '#000', error: '#f00', success: '#0f0', background: '#fff',
            'on-surface': '#000', warning: '#fa0',
          },
        },
      },
    },
  },
}))

import { useAppStore } from '../app'

const fixture = (overrides: Partial<{ gdpr: boolean, accessibility: boolean }> = {}) => ({
  success: true,
  data: {
    mode: overrides.gdpr === false && overrides.accessibility ? 'accessibility-only' : 'hybrid',
    features: {
      gdpr: overrides.gdpr ?? true,
      accessibility: overrides.accessibility ?? true,
      cookieBannerV2: true,
      accessibilityDeclaration: overrides.accessibility ?? true,
      accessibilityWidget: overrides.accessibility ?? true,
    },
    documents: {
      privacyPolicy: true, cookiePolicy: true, termsOfService: true, accessibilityDeclaration: true,
    },
    resources: { accessibilityWidgetConfigured: true },
    warnings: { accessibilityWidget: null },
  },
})

describe('app store — capability-driven bootstrap', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    loadCapabilitiesMock.mockReset()
    loadDocumentsMock.mockClear()
    loadCookieBannerMock.mockClear()
    loadBrandingMock.mockClear()
    loadLanguagesMock.mockClear()
    loadCacheMock.mockClear()
    loadPagesMock.mockClear()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('resolves capabilities before deciding which loaders to run', async () => {
    loadCapabilitiesMock.mockResolvedValue(fixture())

    const store = useAppStore()
    await store.setAuthenticated(true)

    expect(loadCapabilitiesMock).toHaveBeenCalledOnce()
    expect(store.capabilities?.mode).toBe('hybrid')
    expect(loadDocumentsMock).toHaveBeenCalledOnce()
    expect(loadCookieBannerMock).toHaveBeenCalledOnce()
  })

  it('skips GDPR loaders when features.gdpr is false', async () => {
    loadCapabilitiesMock.mockResolvedValue(fixture({ gdpr: false, accessibility: true }))

    const store = useAppStore()
    await store.setAuthenticated(true)

    expect(loadCapabilitiesMock).toHaveBeenCalledOnce()
    expect(store.isGdprEnabled).toBe(false)
    expect(store.isAccessibilityEnabled).toBe(true)
    expect(loadDocumentsMock).not.toHaveBeenCalled()
    expect(loadCookieBannerMock).not.toHaveBeenCalled()
  })

  it('records capability error when the backend payload is unsuccessful', async () => {
    loadCapabilitiesMock.mockResolvedValue({
      success: false,
      errors: ['Capabilities request failed'],
    })

    const store = useAppStore()
    await store.setAuthenticated(true)

    expect(store.capabilities).toBeNull()
    expect(store.capabilitiesError).toBe('Capabilities request failed')
    // No GDPR or accessibility loaders should kick in when capabilities failed.
    expect(loadDocumentsMock).not.toHaveBeenCalled()
    expect(loadCookieBannerMock).not.toHaveBeenCalled()
  })

  it('clearCapabilities resets capability state', async () => {
    loadCapabilitiesMock.mockResolvedValue(fixture())

    const store = useAppStore()
    await store.loadCapabilities()
    expect(store.capabilities).not.toBeNull()

    store.clearCapabilities()
    expect(store.capabilities).toBeNull()
    expect(store.capabilitiesError).toBeNull()
    expect(store.isLoadingCapabilities).toBe(false)
  })
})
