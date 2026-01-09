import { type ApiResponse, BaseApiService } from './BaseApiService'

export interface PolicySettings {
  use_html_snippet?: boolean
  policy_enabled?: boolean
  policy_page_id?: number
  policy_url?: string
}

export interface WordPressPage {
  id: number
  title: string
  slug: string
  url: string
  modified: string
}

export interface GetWordPressPagesResponse {
  pages: WordPressPage[]
}

export interface UpdatePageRequest {
  policy_type: 'privacy_policy' | 'cookie_policy' | 'terms_of_service'
  page_id: number
  use_html_snippet: boolean
  language: string
}

export interface UpdatePageResponse {
  page_id: number
  policy_type: string
  language: string
  shortcode: string
  use_html_snippet: boolean
  message: string
}

/**
 * Service for handling settings-related API calls
 */
export class SettingsService extends BaseApiService {
  /**
   * Get WordPress pages
   */
  async getWordPressPages (): Promise<ApiResponse<GetWordPressPagesResponse>> {
    return this.get<GetWordPressPagesResponse>('pages')
  }

  /**
   * Update page content with policy shortcode
   */
  async updatePageContent (data: UpdatePageRequest): Promise<ApiResponse<UpdatePageResponse>> {
    return this.post<UpdatePageResponse>('documents/update-page', data)
  }
}

// Create and export a singleton instance
export const settingsService = new SettingsService()
