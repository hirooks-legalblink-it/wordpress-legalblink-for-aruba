import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const loadCapabilitiesMock = vi.fn()
const getWidgetMock = vi.fn()
const setWidgetEnabledMock = vi.fn()

vi.mock('@/services', async () => {
  const actual = await vi.importActual<typeof import('@/services')>('@/services')
  return {
    ...actual,
    capabilityService: { getCapabilities: loadCapabilitiesMock },
    accessibilityService: {
      getDeclaration: vi.fn().mockResolvedValue({ success: false }),
      updateDeclarationPage: vi.fn(),
      getWidget: getWidgetMock,
      setWidgetEnabled: setWidgetEnabledMock,
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

const widgetFixture = (overrides: Partial<{
  available: boolean
  configured: boolean
  warnings: ('configuration_missing' | 'domain_mismatch' | 'configuration_expired')[]
  localEnabled: boolean
}> = {}) => ({
  success: true,
  data: {
    available: overrides.available ?? true,
    configured: overrides.configured ?? true,
    domain: 'example.com',
    html: '<script></script>',
    warnings: overrides.warnings ?? [],
    localEnabled: overrides.localEnabled ?? false,
  },
})

const capabilitiesFixture = (widget: boolean) => ({
  success: true,
  data: {
    mode: 'hybrid',
    features: {
      gdpr: false, accessibility: widget, cookieBannerV2: false,
      accessibilityDeclaration: false, accessibilityWidget: widget,
    },
    documents: { privacyPolicy: false, cookiePolicy: false, termsOfService: false, accessibilityDeclaration: false },
    resources: { accessibilityWidgetConfigured: widget },
    warnings: { accessibilityWidget: null },
  },
})

describe('app store — accessibility widget', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    loadCapabilitiesMock.mockReset()
    getWidgetMock.mockReset()
    setWidgetEnabledMock.mockReset()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('loads the widget when capabilities advertise accessibilityWidget', async () => {
    loadCapabilitiesMock.mockResolvedValue(capabilitiesFixture(true))
    getWidgetMock.mockResolvedValue(widgetFixture())

    const store = useAppStore()
    await store.setAuthenticated(true)
    await Promise.resolve()
    await Promise.resolve()

    expect(getWidgetMock).toHaveBeenCalledOnce()
    expect(store.accessibilityWidget?.configured).toBe(true)
    expect(store.accessibilityWidget?.localEnabled).toBe(false)
  })

  it('skips widget loading when capability is false', async () => {
    loadCapabilitiesMock.mockResolvedValue(capabilitiesFixture(false))
    getWidgetMock.mockResolvedValue(widgetFixture())

    const store = useAppStore()
    await store.setAuthenticated(true)
    await Promise.resolve()

    expect(getWidgetMock).not.toHaveBeenCalled()
    expect(store.accessibilityWidget).toBeNull()
  })

  it('records error when widget fetch fails', async () => {
    getWidgetMock.mockResolvedValue({
      success: false,
      errors: ['Accessibility widget request failed'],
    })

    const store = useAppStore()
    await store.loadAccessibilityWidget()

    expect(store.accessibilityWidget).toBeNull()
    expect(store.accessibilityWidgetError).toBe('Accessibility widget request failed')
  })

  it('saveAccessibilityWidgetToggle persists local toggle and updates state', async () => {
    setWidgetEnabledMock.mockResolvedValue({ success: true, data: { enabled: true } })

    const store = useAppStore()
    store.accessibilityWidget = widgetFixture({ localEnabled: false }).data

    await store.saveAccessibilityWidgetToggle(true)

    expect(setWidgetEnabledMock).toHaveBeenCalledWith(true)
    expect(store.accessibilityWidget?.localEnabled).toBe(true)
  })

  it('saveAccessibilityWidgetToggle surfaces backend errors', async () => {
    setWidgetEnabledMock.mockResolvedValue({
      success: false,
      errors: ['Widget toggle exception'],
    })

    const store = useAppStore()
    await expect(store.saveAccessibilityWidgetToggle(true)).rejects.toThrow('Widget toggle exception')
    expect(store.saveAccessibilityWidgetToggleError).toBe('Widget toggle exception')
  })
})
