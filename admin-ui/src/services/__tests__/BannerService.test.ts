import { afterEach, describe, expect, it, vi } from 'vitest'
import { BannerService } from '../BannerService'

describe('BannerService', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('getBannerData GETs /banner with default language=it', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValueOnce(
      new Response(JSON.stringify({
        success: true,
        data: { enabled: true, html: '<script>banner</script>' },
      }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const response = await new BannerService().getBannerData()
    expect(response.success).toBe(true)
    expect(response.data?.enabled).toBe(true)

    const fetchMock = global.fetch as unknown as ReturnType<typeof vi.fn>
    const [url, options] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(url).toContain('/banner?')
    expect(url).toContain('language=it')
    expect(options.method).toBe('GET')
  })

  it('getBannerData accepts an explicit language', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValueOnce(
      new Response(JSON.stringify({ success: true, data: { enabled: false, html: '' } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    await new BannerService().getBannerData('en')

    const fetchMock = global.fetch as unknown as ReturnType<typeof vi.fn>
    const [url] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(url).toContain('language=en')
  })

  it('setBannerData PUTs /banner with enabled+language body', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValueOnce(
      new Response(JSON.stringify({ success: true }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    await new BannerService().setBannerData(true, 'en')

    const fetchMock = global.fetch as unknown as ReturnType<typeof vi.fn>
    const [url, options] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(url).toMatch(/\/banner$/)
    expect(options.method).toBe('PUT')
    expect(JSON.parse(String(options.body))).toEqual({ enabled: true, language: 'en' })
  })

  it('setBannerData defaults language to it when omitted', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValueOnce(
      new Response(JSON.stringify({ success: true }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    await new BannerService().setBannerData(false)

    const fetchMock = global.fetch as unknown as ReturnType<typeof vi.fn>
    const [, options] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(JSON.parse(String(options.body))).toEqual({ enabled: false, language: 'it' })
  })
})
