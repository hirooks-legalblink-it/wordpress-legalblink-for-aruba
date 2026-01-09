import { type ApiResponse, BaseApiService } from './BaseApiService'

export interface BrandingData {
  logo: string
  colors: {
    primary: string
    error: string
    success: string
    background: string
    textOnPrimary: string
    warn: string
  }
}

/**
 * Service for handling branding-related API calls
 */
export class BrandingService extends BaseApiService {
  /**
   * Get branding configuration
   */
  async getBranding (refresh = false): Promise<ApiResponse<BrandingData>> {
    const params = refresh ? { refresh: 'true' } : undefined
    return this.get<BrandingData>('branding', params)
  }
}

// Create and export a singleton instance
export const brandingService = new BrandingService()
