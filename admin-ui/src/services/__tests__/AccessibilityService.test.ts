import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { AccessibilityService, type AccessibilityDeclaration } from '../AccessibilityService'

const fixture: AccessibilityDeclaration = {
  available: true,
  source: 'canonical',
  document: {
    id: 'doc-1',
    slug: 'declarationaccessibility',
    createdAt: '2026-01-01T00:00:00Z',
    updatedAt: '2026-04-01T00:00:00Z',
    languages: {
      it: { url: { html: 'https://x/it.html', pdf: 'https://x/it.pdf' }, pageId: '42', useHtmlSnippet: false },
      en: { url: { html: 'https://x/en.html', pdf: 'https://x/en.pdf' }, pageId: null, useHtmlSnippet: true },
    },
  },
}

describe('AccessibilityService', () => {
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

  it('GETs the declaration endpoint with the WP nonce header', async () => {
    const response = await new AccessibilityService().getDeclaration()

    expect(response.success).toBe(true)
    expect(response.data?.available).toBe(true)
    expect(response.data?.document?.languages.it.pageId).toBe('42')
    expect(response.data?.document?.languages.en.useHtmlSnippet).toBe(true)

    const fetchMock = global.fetch as unknown as ReturnType<typeof vi.fn>
    const [url, options] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(url).toContain('/accessibility/declaration')
    expect((options.headers as Record<string, string>)['X-WP-Nonce']).toBe('test-nonce')
  })

  it('POSTs the update-page endpoint with the request body', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValueOnce(
      new Response(JSON.stringify({ success: true, data: {} }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const service = new AccessibilityService()
    const response = await service.updateDeclarationPage({
      page_id: 7,
      use_html_snippet: true,
      language: 'it',
    })

    expect(response.success).toBe(true)

    const fetchMock = global.fetch as unknown as ReturnType<typeof vi.fn>
    const [url, options] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(url).toContain('/accessibility/declaration/update-page')
    expect(options.method).toBe('POST')
    expect(JSON.parse(String(options.body))).toEqual({
      page_id: 7,
      use_html_snippet: true,
      language: 'it',
    })
  })

  it('reports unavailable declarations without throwing', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValueOnce(
      new Response(
        JSON.stringify({ success: true, data: { available: false, source: null, document: null } }),
        { status: 200, headers: { 'Content-Type': 'application/json' } },
      ),
    )

    const response = await new AccessibilityService().getDeclaration()
    expect(response.success).toBe(true)
    expect(response.data?.available).toBe(false)
    expect(response.data?.document).toBeNull()
  })
})
