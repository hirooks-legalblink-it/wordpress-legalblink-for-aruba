import { afterEach, describe, expect, it, vi } from 'vitest'
import { CacheService } from '../CacheService'

describe('CacheService', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('getSettings GETs /cache/settings', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValueOnce(
      new Response(JSON.stringify({ success: true, data: { cache_duration: 30 } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const response = await new CacheService().getSettings()
    expect(response.success).toBe(true)
    expect(response.data?.cache_duration).toBe(30)

    const fetchMock = global.fetch as unknown as ReturnType<typeof vi.fn>
    const [url, options] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(url).toContain('/cache/settings')
    expect(options.method).toBe('GET')
  })

  it('updateSettings POSTs /cache/settings with cache_duration body', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValueOnce(
      new Response(JSON.stringify({ success: true, data: {} }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    await new CacheService().updateSettings(90)

    const fetchMock = global.fetch as unknown as ReturnType<typeof vi.fn>
    const [url, options] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(url).toContain('/cache/settings')
    expect(options.method).toBe('POST')
    expect(JSON.parse(String(options.body))).toEqual({ cache_duration: 90 })
  })

  it('clearCache POSTs /cache/clear with no body data', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValueOnce(
      new Response(JSON.stringify({ success: true, data: {} }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    await new CacheService().clearCache()

    const fetchMock = global.fetch as unknown as ReturnType<typeof vi.fn>
    const [url, options] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(url).toContain('/cache/clear')
    expect(options.method).toBe('POST')
    expect(options.body).toBeUndefined()
  })
})
