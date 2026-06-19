import { afterEach, describe, expect, it, vi } from 'vitest'
import { BrandingService } from '../BrandingService'

const fixture = {
  logo: 'https://x/logo.png',
  colors: {
    primary: '#000', error: '#f00', success: '#0f0',
    background: '#fff', textOnPrimary: '#000', warn: '#fa0',
  },
}

describe('BrandingService', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('getBranding GETs /branding without params by default', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValueOnce(
      new Response(JSON.stringify({ success: true, data: fixture }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const response = await new BrandingService().getBranding()
    expect(response.success).toBe(true)
    expect(response.data?.logo).toBe('https://x/logo.png')

    const fetchMock = global.fetch as unknown as ReturnType<typeof vi.fn>
    const [url, options] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(url).toMatch(/\/branding$/)
    expect(options.method).toBe('GET')
  })

  it('getBranding(true) appends ?refresh=true', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValueOnce(
      new Response(JSON.stringify({ success: true, data: fixture }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    await new BrandingService().getBranding(true)

    const fetchMock = global.fetch as unknown as ReturnType<typeof vi.fn>
    const [url] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(url).toContain('refresh=true')
  })

  it('surfaces unsuccessful responses without throwing', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValueOnce(
      new Response(JSON.stringify({ success: false, errors: ['Branding API error'] }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const response = await new BrandingService().getBranding()
    expect(response.success).toBe(false)
    expect(response.errors?.[0]).toBe('Branding API error')
  })
})
