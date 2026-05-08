import { afterEach, describe, expect, it, vi } from 'vitest'
import { DocumentService } from '../DocumentService'

const docsFixture = {
  count: 1,
  data: {
    cookie_policy: {
      id: 'cp', slug: 'cookie-policy', createdAt: '', updatedAt: '',
      languages: {
        it: { url: { html: 'https://x/cp.html', pdf: 'https://x/cp.pdf' }, pageId: '7', useHtmlSnippet: false },
      },
    },
  },
}

describe('DocumentService', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('getAllDocuments GETs /documents', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValueOnce(
      new Response(JSON.stringify({ success: true, data: docsFixture }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const response = await new DocumentService().getAllDocuments()
    expect(response.success).toBe(true)
    expect(response.data?.count).toBe(1)
    expect(response.data?.data.cookie_policy.languages.it.pageId).toBe('7')

    const fetchMock = global.fetch as unknown as ReturnType<typeof vi.fn>
    const [url, options] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(url).toMatch(/\/documents$/)
    expect(options.method).toBe('GET')
  })

  it('getLanguages GETs /languages', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValueOnce(
      new Response(JSON.stringify({
        success: true,
        data: { count: 2, data: [{ id: '1', code: 'it', name: 'Italiano' }, { id: '2', code: 'en', name: 'English' }] },
      }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const response = await new DocumentService().getLanguages()
    expect(response.success).toBe(true)

    const fetchMock = global.fetch as unknown as ReturnType<typeof vi.fn>
    const [url] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(url).toMatch(/\/languages$/)
  })
})
