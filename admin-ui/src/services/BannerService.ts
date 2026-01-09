import { type ApiResponse, BaseApiService } from './BaseApiService'

export interface BannerData {
  enabled: boolean
  html: string
}

export interface UpsellConfig {
  cta_text: string
  cta_link: string
  info_text?: string
}

/**
 * Service for handling cookie banner and upsell-related API calls
 */
export class BannerService extends BaseApiService {
  /**
   * Get cookie banner data
   */
  async getBannerData (language = 'it'): Promise<ApiResponse<BannerData>> {
    return this.get<BannerData>('banner', { language })
  }

  /**
   * Set banner show status
   */
  async setBannerData (enabled: boolean, language = 'it'): Promise<ApiResponse> {
    return this.put<ApiResponse>('banner', { enabled, language })
  }
}

// Create and export a singleton instance
export const bannerService = new BannerService()
