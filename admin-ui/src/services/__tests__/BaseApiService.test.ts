import { afterEach, describe, expect, it, vi } from 'vitest'
import { BaseApiService } from '../BaseApiService'

/**
 * Concrete subclass to expose the protected http verbs.
 */
class TestApi extends BaseApiService {
  publicGet<T>(endpoint: string, params?: Record<string, string>) { return this.get<T>(endpoint, params) }
  publicPost<T>(endpoint: string, data?: any) { return this.post<T>(endpoint, data) }
  publicPut<T>(endpoint: string, data?: any) { return this.put<T>(endpoint, data) }
  publicDelete<T>(endpoint: string) { return this.delete<T>(endpoint) }
}

describe('BaseApiService', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  const okResponse = (body: any) => new Response(JSON.stringify(body), {
    status: 200,
    headers: { 'Content-Type': 'application/json' },
  })

  it('get builds the URL with the WP root + querystring + nonce header', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValueOnce(okResponse({ success: true, data: 'x' }))

    await new TestApi().publicGet('endpoint', { language: 'it', refresh: 'true' })

    const fetchMock = global.fetch as unknown as ReturnType<typeof vi.fn>
    const [url, options] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(url).toContain('/lbfa/v1/endpoint?')
    expect(url).toContain('language=it')
    expect(url).toContain('refresh=true')
    expect((options.headers as Record<string, string>)['X-WP-Nonce']).toBe('test-nonce')
    expect(options.method).toBe('GET')
    expect(options.body).toBeUndefined()
  })

  it('post serializes the body as JSON', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValueOnce(okResponse({ success: true }))

    await new TestApi().publicPost('write', { a: 1, b: 'two' })

    const fetchMock = global.fetch as unknown as ReturnType<typeof vi.fn>
    const [, options] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(options.method).toBe('POST')
    expect((options.headers as Record<string, string>)['Content-Type']).toBe('application/json')
    expect(JSON.parse(String(options.body))).toEqual({ a: 1, b: 'two' })
  })

  it('put serializes the body as JSON', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValueOnce(okResponse({ success: true }))

    await new TestApi().publicPut('update', { enabled: true })

    const fetchMock = global.fetch as unknown as ReturnType<typeof vi.fn>
    const [, options] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(options.method).toBe('PUT')
    expect(JSON.parse(String(options.body))).toEqual({ enabled: true })
  })

  it('delete sends no body', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValueOnce(okResponse({ success: true }))

    await new TestApi().publicDelete('drop')

    const fetchMock = global.fetch as unknown as ReturnType<typeof vi.fn>
    const [, options] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(options.method).toBe('DELETE')
    expect(options.body).toBeUndefined()
  })

  it('translates AbortError to a Request timeout error', async () => {
    vi.spyOn(global, 'fetch').mockImplementationOnce(() => {
      const err = new Error('aborted')
      err.name = 'AbortError'
      return Promise.reject(err)
    })

    await expect(new TestApi().publicGet('slow')).rejects.toThrow('Request timeout')
  })

  it('rejects when the response is not valid JSON', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValueOnce(new Response('not-json', {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    }))

    await expect(new TestApi().publicGet('bad')).rejects.toThrow()
  })

  it('warns when window.lbfa root or nonce are missing', async () => {
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {})
    ;(globalThis as any).__resetLbfaGlobals({ root: '', nonce: '' })

    new TestApi()

    expect(warnSpy).toHaveBeenCalled()
  })
})
