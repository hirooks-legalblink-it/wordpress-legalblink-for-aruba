import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

// vi.hoisted lifts the mock fns above the vi.mock factory's own hoisting,
// matching the pattern used by the existing S#7701 store tests.
const {
  authIsLoggedInMock,
  brandingGetBrandingMock,
  documentGetAllMock,
  documentGetLanguagesMock,
  bannerGetMock,
  bannerSetMock,
  cacheGetMock,
  settingsGetPagesMock,
  settingsUpdatePageMock,
} = vi.hoisted(() => ({
  authIsLoggedInMock: vi.fn(),
  brandingGetBrandingMock: vi.fn(),
  documentGetAllMock: vi.fn(),
  documentGetLanguagesMock: vi.fn(),
  bannerGetMock: vi.fn(),
  bannerSetMock: vi.fn(),
  cacheGetMock: vi.fn(),
  settingsGetPagesMock: vi.fn(),
  settingsUpdatePageMock: vi.fn(),
}))

vi.mock('@/services', async () => {
  const actual = await vi.importActual<typeof import('@/services')>('@/services')
  return {
    ...actual,
    authService: { isLoggedIn: authIsLoggedInMock, login: vi.fn(), logout: vi.fn() },
    brandingService: { getBranding: brandingGetBrandingMock },
    documentService: {
      getAllDocuments: documentGetAllMock,
      getLanguages: documentGetLanguagesMock,
    },
    bannerService: { getBannerData: bannerGetMock, setBannerData: bannerSetMock },
    cacheService: { getSettings: cacheGetMock, updateSettings: vi.fn(), clearCache: vi.fn() },
    settingsService: {
      getWordPressPages: settingsGetPagesMock,
      updatePageContent: settingsUpdatePageMock,
    },
    capabilityService: { getCapabilities: vi.fn().mockResolvedValue({ success: false }) },
    accessibilityService: {
      getDeclaration: vi.fn(),
      updateDeclarationPage: vi.fn(),
      getWidget: vi.fn(),
      setWidgetEnabled: vi.fn(),
    },
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

describe('app store — legacy actions', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    authIsLoggedInMock.mockReset()
    brandingGetBrandingMock.mockReset()
    documentGetAllMock.mockReset()
    documentGetLanguagesMock.mockReset()
    bannerGetMock.mockReset()
    bannerSetMock.mockReset()
    cacheGetMock.mockReset()
    settingsGetPagesMock.mockReset()
    settingsUpdatePageMock.mockReset()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  /* ---------- loadBranding ---------- */

  it('loadBranding success populates store.branding', async () => {
    const branding = { logo: 'https://x/logo.png', colors: {} as any }
    brandingGetBrandingMock.mockResolvedValueOnce({ success: true, data: branding })

    const store = useAppStore()
    await store.loadBranding()

    expect(store.branding).toEqual(branding)
    expect(store.brandingError).toBeNull()
  })

  it('loadBranding failure falls back to default branding + sets error', async () => {
    brandingGetBrandingMock.mockRejectedValueOnce(new Error('boom'))

    const store = useAppStore()
    await store.loadBranding()

    expect(store.brandingError).toBe('boom')
    expect(store.branding.colors.primary).toBeDefined()
  })

  /* ---------- loadLanguages ---------- */

  it('loadLanguages success populates store.languages', async () => {
    const languages = [
      { id: '1', code: 'it', name: 'Italiano' },
      { id: '2', code: 'en', name: 'English' },
    ]
    documentGetLanguagesMock.mockResolvedValueOnce({
      success: true,
      data: { count: 2, data: languages },
    })

    const store = useAppStore()
    await store.loadLanguages()

    expect(store.languages).toEqual(languages)
  })

  it('loadLanguages failure surfaces error and keeps languages empty', async () => {
    documentGetLanguagesMock.mockRejectedValueOnce(new Error('languages down'))

    const store = useAppStore()
    await store.loadLanguages()

    expect(store.languagesError).toBe('languages down')
    expect(store.languages).toEqual([])
  })

  /* ---------- loadDocuments ---------- */

  it('loadDocuments success populates store.documents', async () => {
    const docs = {
      cookie_policy: {
        id: 'cp', slug: 'cookie-policy', createdAt: '', updatedAt: '',
        languages: { it: { url: { html: 'h', pdf: 'p' }, pageId: null, useHtmlSnippet: false } },
      },
    } as any
    documentGetAllMock.mockResolvedValueOnce({ success: true, data: { count: 1, data: docs } })

    const store = useAppStore()
    await store.loadDocuments()

    expect(store.documents).toEqual(docs)
  })

  it('loadDocuments failure surfaces error', async () => {
    documentGetAllMock.mockRejectedValueOnce(new Error('boom'))

    const store = useAppStore()
    await store.loadDocuments()

    expect(store.documentsError).toBe('boom')
  })

  /* ---------- loadWordPressPages ---------- */

  it('loadWordPressPages success populates store.wordpressPages', async () => {
    settingsGetPagesMock.mockResolvedValueOnce({
      success: true,
      data: { pages: [{ id: 1, title: 'A', slug: 'a', url: '', modified: '' }] },
    })

    const store = useAppStore()
    await store.loadWordPressPages()

    expect(store.wordpressPages).toHaveLength(1)
    expect(store.wordpressPages[0].title).toBe('A')
  })

  it('loadWordPressPages failure surfaces error', async () => {
    settingsGetPagesMock.mockRejectedValueOnce(new Error('boom'))

    const store = useAppStore()
    await store.loadWordPressPages()

    expect(store.wordPressPagesError).toBe('boom')
  })

  /* ---------- loadCacheSettings ---------- */

  it('loadCacheSettings success populates store.cacheSettings', async () => {
    cacheGetMock.mockResolvedValueOnce({ success: true, data: { cache_duration: 90 } })

    const store = useAppStore()
    await store.loadCacheSettings()

    expect(store.cacheSettings.cache_duration).toBe(90)
  })

  it('loadCacheSettings failure falls back to default 30', async () => {
    cacheGetMock.mockRejectedValueOnce(new Error('boom'))

    const store = useAppStore()
    await store.loadCacheSettings()

    expect(store.cacheSettings.cache_duration).toBe(30)
    expect(store.cacheSettingsError).toBe('boom')
  })

  /* ---------- loadCookieBannerData / saveCookieBannerData ---------- */

  it('loadCookieBannerData success populates store.cookieBannerData', async () => {
    bannerGetMock.mockResolvedValueOnce({
      success: true,
      data: { enabled: true, html: '<script></script>' },
    })

    const store = useAppStore()
    await store.loadCookieBannerData()

    expect(store.cookieBannerData.enabled).toBe(true)
  })

  it('loadCookieBannerData failure surfaces error', async () => {
    bannerGetMock.mockRejectedValueOnce(new Error('boom'))

    const store = useAppStore()
    await store.loadCookieBannerData()

    expect(store.bannerError).toBe('boom')
  })

  it('saveCookieBannerData success updates store + returns true', async () => {
    bannerSetMock.mockResolvedValueOnce({ success: true })

    const store = useAppStore()
    const result = await store.saveCookieBannerData(true, '<script></script>')

    expect(result).toBe(true)
    expect(store.cookieBannerData.enabled).toBe(true)
    expect(store.cookieBannerData.html).toBe('<script></script>')
  })

  it('saveCookieBannerData failure throws + sets saveBannerError', async () => {
    bannerSetMock.mockResolvedValueOnce({ success: false, message: 'API down' })

    const store = useAppStore()
    await expect(store.saveCookieBannerData(true, '')).rejects.toThrow('API down')
    expect(store.saveBannerError).toBe('API down')
  })

  /* ---------- updatePageContent ---------- */

  it('updatePageContent success returns response data', async () => {
    settingsUpdatePageMock.mockResolvedValueOnce({
      success: true,
      data: {
        page_id: 7, policy_type: 'cookie_policy', language: 'it',
        shortcode: '[LBFA_COOKIE_POLICY]', use_html_snippet: false, message: 'ok',
      },
    })

    const store = useAppStore()
    const data = await store.updatePageContent({
      policy_type: 'cookie_policy',
      page_id: 7,
      use_html_snippet: false,
      language: 'it',
    })

    expect(data?.page_id).toBe(7)
  })

  it('updatePageContent failure throws + sets updatePageError', async () => {
    settingsUpdatePageMock.mockResolvedValueOnce({ success: false, message: 'page not found' })

    const store = useAppStore()
    await expect(
      store.updatePageContent({
        policy_type: 'cookie_policy',
        page_id: 9999,
        use_html_snippet: false,
        language: 'it',
      }),
    ).rejects.toThrow('page not found')
    expect(store.updatePageError).toBe('page not found')
  })

  /* ---------- checkAuthStatus ---------- */

  it('checkAuthStatus success sets isAuthenticated true', async () => {
    authIsLoggedInMock.mockResolvedValueOnce({ success: true, data: { authenticated: true } })

    const store = useAppStore()
    const result = await store.checkAuthStatus()

    expect(result).toBe(true)
    expect(store.isAuthenticated).toBe(true)
  })

  it('checkAuthStatus failure sets isAuthenticated false', async () => {
    authIsLoggedInMock.mockRejectedValueOnce(new Error('boom'))

    const store = useAppStore()
    const result = await store.checkAuthStatus()

    expect(result).toBe(false)
    expect(store.isAuthenticated).toBe(false)
    expect(store.authError).toBe('boom')
  })

  /* ---------- setSelectedLanguage ---------- */

  it('setSelectedLanguage updates state', () => {
    const store = useAppStore()
    store.setSelectedLanguage('en')
    expect(store.selectedLanguage).toBe('en')
  })

  /* ---------- getter helpers ---------- */

  it('getDocument and hasDocumentContent inspect store.documents', () => {
    const store = useAppStore()
    store.documents = {
      cookie_policy: { id: 'cp', slug: 'cookie-policy', createdAt: '', updatedAt: '', languages: {} },
    } as any

    expect(store.getDocument('cookie_policy')).not.toBeNull()
    expect(store.getDocument('privacy_policy')).toBeNull()
    expect(store.hasDocumentContent('cookie_policy')).toBe(true)
    expect(store.hasDocumentContent('privacy_policy')).toBe(false)
  })

  /* ---------- clear* helpers ---------- */

  it('clearBranding resets branding slice', () => {
    const store = useAppStore()
    store.branding = { logo: 'x', colors: {} as any }
    store.brandingError = 'oops'

    store.clearBranding()

    expect(store.brandingError).toBeNull()
    expect(store.isLoadingBranding).toBe(false)
  })

  it('clearDocuments resets documents slice', () => {
    const store = useAppStore()
    store.documents = { cookie_policy: { id: 'x' } as any } as any
    store.documentsError = 'oops'

    store.clearDocuments()

    expect(Object.keys(store.documents)).toHaveLength(0)
    expect(store.documentsError).toBeNull()
  })

  it('clearLanguages resets languages slice and selectedLanguage', () => {
    const store = useAppStore()
    store.languages = [{ id: '1', code: 'en', name: 'English' }] as any
    store.selectedLanguage = 'en'

    store.clearLanguages()

    expect(store.languages).toEqual([])
    expect(store.selectedLanguage).toBe('it')
  })

  it('clearBanner resets banner slice', () => {
    const store = useAppStore()
    store.cookieBannerData = { enabled: true, html: '<x>' }
    store.bannerError = 'x'
    store.saveBannerError = 'y'

    store.clearBanner()

    expect(store.cookieBannerData.enabled).toBe(false)
    expect(store.cookieBannerData.html).toBe('')
    expect(store.bannerError).toBeNull()
    expect(store.saveBannerError).toBeNull()
  })

  it('clearAll resets every slice including capability and accessibility', () => {
    const store = useAppStore()
    store.branding = { logo: 'x', colors: {} as any }
    store.documents = { cookie_policy: { id: 'x' } as any } as any
    store.languages = [{ id: '1', code: 'en', name: 'English' }] as any
    store.cookieBannerData = { enabled: true, html: '<x>' }
    store.isAuthenticated = true
    store.capabilities = { mode: 'hybrid' } as any
    store.accessibilityDeclaration = { available: true } as any
    store.accessibilityWidget = { available: true } as any

    store.clearAll()

    expect(store.isAuthenticated).toBe(false)
    expect(Object.keys(store.documents)).toHaveLength(0)
    expect(store.languages).toEqual([])
    expect(store.cookieBannerData.enabled).toBe(false)
    expect(store.capabilities).toBeNull()
    expect(store.accessibilityDeclaration).toBeNull()
    expect(store.accessibilityWidget).toBeNull()
  })
})
