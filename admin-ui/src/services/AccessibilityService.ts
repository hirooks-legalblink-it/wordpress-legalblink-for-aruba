import { type ApiResponse, BaseApiService } from './BaseApiService'
import type { PolicyLanguageUrl } from './DocumentService'

/**
 * Per-language declaration entry. Mirrors the GDPR `PolicyLanguageData` shape
 * so PolicySettingsCard / DocumentEmbed can render it with minimal branching.
 */
export interface AccessibilityDeclarationLanguageData {
  url: PolicyLanguageUrl
  pageId: string | null
  useHtmlSnippet: boolean
}

export interface AccessibilityDeclarationDocument {
  id: string
  slug: string
  createdAt: string
  updatedAt: string
  languages: Record<string, AccessibilityDeclarationLanguageData>
}

export interface AccessibilityDeclaration {
  available: boolean
  source: 'canonical' | 'legacy' | string | null
  document: AccessibilityDeclarationDocument | null
}

export interface UpdateDeclarationPageRequest {
  page_id: number
  use_html_snippet: boolean
  language: string
}

/**
 * S#7701 mixed-mode accessibility surface (Phase 3+).
 *
 * Strictly separate from `DocumentService` to keep the GDPR-legacy surface
 * untouched (S#7701 "DocumentService non si gonfia" rule). Only the canonical
 * `accessibility_declaration` type is exposed here in Phase 3; the widget
 * (Phase 4) extends this same service.
 */
export class AccessibilityService extends BaseApiService {
  async getDeclaration (): Promise<ApiResponse<AccessibilityDeclaration>> {
    return this.get<AccessibilityDeclaration>('accessibility/declaration')
  }

  async updateDeclarationPage (request: UpdateDeclarationPageRequest): Promise<ApiResponse<unknown>> {
    return this.post('accessibility/declaration/update-page', request)
  }
}

export const accessibilityService = new AccessibilityService()
