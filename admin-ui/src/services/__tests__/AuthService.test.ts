import { afterEach, describe, expect, it, vi } from 'vitest'
import { AuthService } from '../AuthService'

describe('AuthService', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('isLoggedIn GETs /auth/verify with the WP nonce header', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValueOnce(
      new Response(JSON.stringify({ success: true, data: { authenticated: true, user_data: { id: 'u1' } } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const response = await new AuthService().isLoggedIn()
    expect(response.success).toBe(true)
    expect(response.data?.authenticated).toBe(true)

    const fetchMock = global.fetch as unknown as ReturnType<typeof vi.fn>
    const [url, options] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(url).toContain('/auth/verify')
    expect((options.headers as Record<string, string>)['X-WP-Nonce']).toBe('test-nonce')
    expect(options.method).toBe('GET')
  })

  it('isLoggedIn falls back to {authenticated:false} on network failure', async () => {
    vi.spyOn(global, 'fetch').mockRejectedValueOnce(new Error('boom'))

    const response = await new AuthService().isLoggedIn()
    expect(response.success).toBe(true)
    expect(response.data?.authenticated).toBe(false)
  })

  it('login POSTs /auth/login with the external_id body', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValueOnce(
      new Response(JSON.stringify({ success: true, data: { success: true, message: 'ok' } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const response = await new AuthService().login('extX')
    expect(response.success).toBe(true)

    const fetchMock = global.fetch as unknown as ReturnType<typeof vi.fn>
    const [url, options] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(url).toContain('/auth/login')
    expect(options.method).toBe('POST')
    expect(JSON.parse(String(options.body))).toEqual({ external_id: 'extX' })
  })

  it('logout POSTs /auth/logout with empty body', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValueOnce(
      new Response(JSON.stringify({ success: true, data: { success: true, message: 'logged out' } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const response = await new AuthService().logout()
    expect(response.success).toBe(true)

    const fetchMock = global.fetch as unknown as ReturnType<typeof vi.fn>
    const [url, options] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(url).toContain('/auth/logout')
    expect(options.method).toBe('POST')
    expect(JSON.parse(String(options.body))).toEqual({})
  })
})
