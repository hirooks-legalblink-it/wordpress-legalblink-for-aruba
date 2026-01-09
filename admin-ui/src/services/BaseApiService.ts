/**
 * Base API Service Class
 * Provides common functionality for WordPress REST API communication
 */

export interface ApiResponse<T = any> {
  success: boolean
  data?: T
  errors?: string[]
  message?: string
}

export interface ApiConfig {
  root: string
  nonce: string
  timeout?: number
}

export class BaseApiService {
  protected config: ApiConfig

  constructor (config?: Partial<ApiConfig>) {
    this.config = {
      root: window.lbfa.root,
      nonce: window.lbfa.nonce,
      timeout: 30_000,
      ...config,
    }

    if (!this.config.root || !this.config.nonce) {
      console.warn('[BaseApiService] API configuration incomplete. Some features may not work.')
    }
  }

  /**
   * Make a GET request to the WordPress REST API
   */
  protected async get<T = any>(endpoint: string, params?: Record<string, string>): Promise<ApiResponse<T>> {
    return this.request<T>('GET', endpoint, undefined, params)
  }

  /**
   * Make a POST request to the WordPress REST API
   */
  protected async post<T = any>(endpoint: string, data?: any): Promise<ApiResponse<T>> {
    return this.request<T>('POST', endpoint, data)
  }

  /**
   * Make a PUT request to the WordPress REST API
   */
  protected async put<T = any>(endpoint: string, data?: any): Promise<ApiResponse<T>> {
    return this.request<T>('PUT', endpoint, data)
  }

  /**
   * Make a DELETE request to the WordPress REST API
   */
  protected async delete<T = any>(endpoint: string): Promise<ApiResponse<T>> {
    return this.request<T>('DELETE', endpoint)
  }

  /**
   * Generic request method
   */
  private async request<T = any>(
    method: string,
    endpoint: string,
    data?: any,
    params?: Record<string, string>,
  ): Promise<ApiResponse<T>> {
    // Build URL with query parameters
    let url = `${this.config.root}/${endpoint.replace(/^\//, '')}`
    if (params) {
      const searchParams = new URLSearchParams(params)
      url += `?${searchParams.toString()}`
    }

    // Prepare request options
    const options: RequestInit = {
      method,
      headers: {
        'X-WP-Nonce': this.config.nonce,
        'Content-Type': 'application/json',
      },
    }

    // Add body for POST/PUT requests
    if (data && (method === 'POST' || method === 'PUT')) {
      options.body = JSON.stringify(data)
    }

    // Add timeout
    const controller = new AbortController()
    const timeoutId = setTimeout(() => controller.abort(), this.config.timeout)
    options.signal = controller.signal

    try {
      const response = await fetch(url, options)
      clearTimeout(timeoutId)

      const responseData = await response.json()

      console.log('[BaseApiService] Response data:', responseData)

      return responseData
    } catch (fetchError) {
      clearTimeout(timeoutId)
      if (fetchError instanceof Error && fetchError.name === 'AbortError') {
        throw new Error('Request timeout')
      }
      throw fetchError
    }
  }
}
