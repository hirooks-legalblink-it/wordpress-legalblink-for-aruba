import { type ApiResponse, BaseApiService } from './BaseApiService'

export interface UserData {
  [key: string]: any
  id: string | number
  name?: string
  email?: string
}

export interface AuthResponse {
  success: boolean
  data?: UserData
}

/**
 * Service for handling authentication-related API calls
 */
export class AuthService extends BaseApiService {
  /**
   * Check if user is currently authenticated
   */
  async isLoggedIn (): Promise<ApiResponse<{ authenticated: boolean, user_data?: UserData }>> {
    try {
      return await this.get<{ authenticated: boolean, user_data?: UserData }>('auth/verify')
    } catch {
      // If authentication fails, user is not authenticated
      return {
        data: { authenticated: false },
        success: true,
      }
    }
  }

  /**
   * Authenticate user with external ID
   */
  async login (externalId: string): Promise<ApiResponse<{ success: boolean, message: string, user_data?: UserData, external_id?: string }>> {
    return this.post<{ success: boolean, message: string, user_data?: UserData, external_id?: string }>('auth/login', {
      external_id: externalId,
    })
  }

  /**
   * Logout user and clear authentication data
   */
  async logout (): Promise<ApiResponse<{ success: boolean, message: string, cleared_data?: { jwt_token: boolean, user_data: boolean } }>> {
    return this.post<{ success: boolean, message: string, cleared_data?: { jwt_token: boolean, user_data: boolean } }>('auth/logout', {})
  }
}

// Create and export a singleton instance
export const authService = new AuthService()
