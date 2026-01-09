import { type ApiResponse, BaseApiService } from './BaseApiService'

export type PolicyKey = 'privacy_policy' | 'cookie_policy' | 'terms_of_service'

export interface PolicyLanguageUrl {
  html: string
  pdf: string
}

export type PolicyLanguageData = {
  url: PolicyLanguageUrl
  pageId: string | null
  useHtmlSnippet: boolean
}

export interface PolicyDocument {
  id: string
  slug: string
  createdAt: string
  updatedAt: string
  languages: Record<string, PolicyLanguageData>
}

export interface Language {
  id: string
  code: string
  name: string
}

export interface LanguagesData {
  count: number
  data: Language[]
}

export interface DocumentsData {
  data: Record<PolicyKey, PolicyDocument>
  count: number
}

/**
 * Service for handling document-related API calls
 */
export class DocumentService extends BaseApiService {
  /**
   * Get all documents
   */
  async getAllDocuments (): Promise<ApiResponse<DocumentsData>> {
    return this.get<DocumentsData>('documents')
  }

  // Legacy methods for backward compatibility
  async getLanguages (): Promise<ApiResponse<LanguagesData>> {
    return this.get('languages')
  }
}

// Create and export a singleton instance
export const documentService = new DocumentService()
