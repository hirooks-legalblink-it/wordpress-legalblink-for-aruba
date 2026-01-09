import { type ApiResponse, BaseApiService } from './BaseApiService'

export interface CacheSettings {
  cache_duration: number
}

/**
 * Service for handling cache-related API calls
 */
export class CacheService extends BaseApiService {
  /**
   * Get current cache settings and statistics
   */
  async getSettings (): Promise<ApiResponse<CacheSettings>> {
    return this.get<CacheSettings>('cache/settings')
  }

  /**
   * Update cache settings
   */
  async updateSettings (cacheDuration: number): Promise<ApiResponse> {
    return this.post<ApiResponse>('cache/settings', {
      cache_duration: cacheDuration,
    })
  }

  /**
   * Clear cache
   */
  async clearCache (): Promise<ApiResponse> {
    return this.post<ApiResponse>('cache/clear')
  }
}

// Create and export a singleton instance
export const cacheService = new CacheService()
