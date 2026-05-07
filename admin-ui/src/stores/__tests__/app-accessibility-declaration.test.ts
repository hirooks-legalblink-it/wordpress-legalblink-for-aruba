import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const loadCapabilitiesMock = vi.fn()
const getDeclarationMock = vi.fn()
const updateDeclarationPageMock = vi.fn()

vi.mock('@/services', async () => {
  const actual = await vi.importActual<typeof import('@/services')>('@/services')
  return {
    ...actual,
    capabilityService: { getCapabilities: loadCapabilitiesMock },
    accessibilityService: {
      getDeclaration: getDeclarationMock,
      updateDeclarationPage: updateDeclarationPageMock,
    },
    brandingService: { getBranding: vi.fn().mockResolvedValue({ success: false }) },
    documentService: { getAllDocuments: vi.fn(), getLanguages: vi.fn().mockResolvedValue({ success: false }) },
    bannerService: { getBannerData: vi.fn(), setBannerData: vi.fn() },
    cacheService: { getSettings: vi.fn().mockResolvedValue({ success: false }) },
    settingsService: { getWordPressPages: vi.fn().mockResolvedValue({ success: false }), updatePageContent: vi.fn() },
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

const declarationFixture = {
  available: true,
  source: 'canonical',
  document: {
    id: 'd', slug: 'declarationaccessibility', createdAt: '', updatedAt: '',
    languages: {
      it: { url: { html: 'h', pdf: 'p' }, pageId: '7', useHtmlSnippet: true },
    },
  },
}

const capabilitiesFixture = (accessibilityDeclaration: boolean) => ({
  success: true,
  data: {
    mode: accessibilityDeclaration ? 'hybrid' : 'gdpr-only',
    features: {
      gdpr: true,
      accessibility: accessibilityDeclaration,
      cookieBannerV2: true,
      accessibilityDeclaration,
      accessibilityWidget: false,
    },
    documents: {
      privacyPolicy: true, cookiePolicy: true, termsOfService: true, accessibilityDeclaration,
    },
    resources: { accessibilityWidgetConfigured: false },
    warnings: { accessibilityWidget: null },
  },
})

describe('app store — accessibility declaration', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    loadCapabilitiesMock.mockReset()
    getDeclarationMock.mockReset()
    updateDeclarationPageMock.mockReset()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('loads the declaration when capabilities advertise accessibilityDeclaration', async () => {
    loadCapabilitiesMock.mockResolvedValue(capabilitiesFixture(true))
    getDeclarationMock.mockResolvedValue({ success: true, data: declarationFixture })

    const store = useAppStore()
    await store.setAuthenticated(true)
    // Allow microtasks for the fire-and-forget loaders triggered after capabilities.
    await Promise.resolve()
    await Promise.resolve()

    expect(getDeclarationMock).toHaveBeenCalledOnce()
    expect(store.accessibilityDeclaration?.available).toBe(true)
    expect(store.accessibilityDeclaration?.document?.languages.it.pageId).toBe('7')
  })

  it('skips the declaration when accessibilityDeclaration capability is false', async () => {
    loadCapabilitiesMock.mockResolvedValue(capabilitiesFixture(false))
    getDeclarationMock.mockResolvedValue({ success: true, data: declarationFixture })

    const store = useAppStore()
    await store.setAuthenticated(true)
    await Promise.resolve()

    expect(getDeclarationMock).not.toHaveBeenCalled()
    expect(store.accessibilityDeclaration).toBeNull()
  })

  it('records error when declaration fetch fails', async () => {
    getDeclarationMock.mockResolvedValue({
      success: false,
      errors: ['Accessibility declaration request failed'],
    })

    const store = useAppStore()
    await store.loadAccessibilityDeclaration()

    expect(store.accessibilityDeclaration).toBeNull()
    expect(store.accessibilityDeclarationError).toBe('Accessibility declaration request failed')
  })

  it('updateAccessibilityDeclarationPage refreshes the declaration after success', async () => {
    updateDeclarationPageMock.mockResolvedValue({ success: true, data: {} })
    getDeclarationMock.mockResolvedValue({ success: true, data: declarationFixture })

    const store = useAppStore()
    await store.updateAccessibilityDeclarationPage({
      page_id: 7,
      use_html_snippet: true,
      language: 'it',
    })

    expect(updateDeclarationPageMock).toHaveBeenCalledOnce()
    expect(getDeclarationMock).toHaveBeenCalledOnce()
    expect(store.accessibilityDeclaration?.document?.languages.it.pageId).toBe('7')
  })

  it('updateAccessibilityDeclarationPage surfaces backend errors', async () => {
    updateDeclarationPageMock.mockResolvedValue({
      success: false,
      errors: ['Page not found'],
    })

    const store = useAppStore()
    await expect(
      store.updateAccessibilityDeclarationPage({
        page_id: 9999,
        use_html_snippet: false,
        language: 'it',
      }),
    ).rejects.toThrow('Page not found')

    expect(store.updateAccessibilityDeclarationPageError).toBe('Page not found')
  })
})
