import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { CapabilityService, type Capabilities } from '../CapabilityService'

const fixture: Capabilities = {
  mode: 'hybrid',
  features: {
    gdpr: true,
    accessibility: true,
    cookieBannerV2: true,
    accessibilityDeclaration: true,
    accessibilityWidget: true,
  },
  documents: {
    privacyPolicy: true,
    cookiePolicy: true,
    termsOfService: true,
    accessibilityDeclaration: true,
  },
  resources: {
    accessibilityWidgetConfigured: true,
  },
  warnings: {
    accessibilityWidget: null,
  },
}

describe('CapabilityService', () => {
  beforeEach(() => {
    vi.spyOn(global, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({ success: true, data: fixture }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('GETs the capabilities endpoint with the WP nonce header', async () => {
    const service = new CapabilityService()
    const response = await service.getCapabilities()

    expect(response.success).toBe(true)
    expect(response.data).toEqual(fixture)

    const fetchMock = global.fetch as unknown as ReturnType<typeof vi.fn>
    expect(fetchMock).toHaveBeenCalledOnce()

    const [url, options] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(url).toContain('/capabilities')
    expect((options.headers as Record<string, string>)['X-WP-Nonce']).toBe('test-nonce')
    expect(options.method).toBe('GET')
  })

  it('preserves the four mixed-mode values for the mode field', async () => {
    const modes: Capabilities['mode'][] = ['none', 'gdpr-only', 'accessibility-only', 'hybrid']

    for (const mode of modes) {
      vi.spyOn(global, 'fetch').mockResolvedValueOnce(
        new Response(JSON.stringify({ success: true, data: { ...fixture, mode } }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        }),
      )

      const response = await new CapabilityService().getCapabilities()
      expect(response.data?.mode).toBe(mode)
    }
  })

  it('surfaces backend error responses without throwing', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValueOnce(
      new Response(
        JSON.stringify({ success: false, errors: ['Capabilities request failed'], data: {} }),
        { status: 200, headers: { 'Content-Type': 'application/json' } },
      ),
    )

    const response = await new CapabilityService().getCapabilities()
    expect(response.success).toBe(false)
    expect(response.errors?.[0]).toContain('Capabilities request failed')
  })
})
