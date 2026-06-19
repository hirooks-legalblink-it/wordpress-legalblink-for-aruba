import { type ApiResponse, BaseApiService } from './BaseApiService'

export type CapabilityMode = 'none' | 'gdpr-only' | 'accessibility-only' | 'hybrid'

export type AccessibilityWidgetWarning =
  | 'configuration_missing'
  | 'domain_mismatch'
  | 'configuration_expired'

export interface CapabilityFeatures {
  gdpr: boolean
  accessibility: boolean
  cookieBannerV2: boolean
  accessibilityDeclaration: boolean
  accessibilityWidget: boolean
}

export interface CapabilityDocuments {
  privacyPolicy: boolean
  cookiePolicy: boolean
  termsOfService: boolean
  accessibilityDeclaration: boolean
}

export interface CapabilityResources {
  accessibilityWidgetConfigured: boolean
}

export interface CapabilityWarnings {
  accessibilityWidget: AccessibilityWidgetWarning | null
}

export interface Capabilities {
  mode: CapabilityMode
  features: CapabilityFeatures
  documents: CapabilityDocuments
  resources: CapabilityResources
  warnings: CapabilityWarnings
}

/**
 * S#7701 mixed-mode capabilities (read-only).
 *
 * The plugin must call this before any feature gating decision: the backend
 * payload (`mode`, `features`, `documents`, `resources`, `warnings`) is the
 * single source of truth for what tabs/cards the admin UI should render.
 * Never infer capabilities from `/users/me` or from the GDPR documents list.
 */
export class CapabilityService extends BaseApiService {
  async getCapabilities (): Promise<ApiResponse<Capabilities>> {
    return this.get<Capabilities>('capabilities')
  }
}

export const capabilityService = new CapabilityService()
