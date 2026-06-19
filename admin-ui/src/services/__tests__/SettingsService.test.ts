import { afterEach, describe, expect, it, vi } from 'vitest'
import { SettingsService } from '../SettingsService'

describe('SettingsService', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('getWordPressPages GETs /pages', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValueOnce(
      new Response(JSON.stringify({
        success: true,
        data: { pages: [{ id: 1, title: 'A', slug: 'a', url: 'https://x/a', modified: '' }] },
      }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const response = await new SettingsService().getWordPressPages()
    expect(response.success).toBe(true)
    expect(response.data?.pages?.[0].title).toBe('A')

    const fetchMock = global.fetch as unknown as ReturnType<typeof vi.fn>
    const [url, options] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(url).toMatch(/\/pages$/)
    expect(options.method).toBe('GET')
  })

  it('updatePageContent POSTs /documents/update-page with the request body', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValueOnce(
      new Response(JSON.stringify({
        success: true,
        data: {
          page_id: 7, policy_type: 'cookie_policy', language: 'it',
          shortcode: '[LBFA_COOKIE_POLICY]', use_html_snippet: false, message: 'ok',
        },
      }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const response = await new SettingsService().updatePageContent({
      policy_type: 'cookie_policy',
      page_id: 7,
      use_html_snippet: false,
      language: 'it',
    })

    expect(response.success).toBe(true)

    const fetchMock = global.fetch as unknown as ReturnType<typeof vi.fn>
    const [url, options] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(url).toContain('/documents/update-page')
    expect(options.method).toBe('POST')
    expect(JSON.parse(String(options.body))).toEqual({
      policy_type: 'cookie_policy',
      page_id: 7,
      use_html_snippet: false,
      language: 'it',
    })
  })
})
