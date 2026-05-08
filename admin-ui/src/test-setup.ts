/**
 * Vitest setup: provides the `window.lbfa` and `window.LB_I18N` globals that
 * BaseApiService and several components rely on when running outside of the
 * WordPress admin shell.
 *
 * Globals are installed at module top-level so they are present BEFORE the
 * service singletons are constructed at module import time. A `beforeEach`
 * resets the values so cross-test mutations cannot leak.
 *
 * The canonical `Window.lbfa` interface is declared in `src/global.d.ts`;
 * we only extend it here with the optional `LB_I18N` global the components
 * read for inline translations.
 */

import { beforeEach } from 'vitest'

declare global {
  interface Window {
    LB_I18N?: Record<string, string>
  }

  // eslint-disable-next-line no-var
  var __resetLbfaGlobals: (overrides?: Partial<Window['lbfa']>, i18n?: Record<string, string>) => void
}

const installLbfaGlobals = (
  overrides: Partial<Window['lbfa']> = {},
  i18n: Record<string, string> = {},
) => {
  Object.defineProperty(window, 'lbfa', {
    value: {
      baseUrl: 'http://localhost',
      root: 'http://localhost/wp-json/lbfa/v1',
      nonce: 'test-nonce',
      editPagesUrl: 'http://localhost/wp-admin/edit.php?post_type=page',
      ...overrides,
    },
    writable: true,
    configurable: true,
  })

  Object.defineProperty(window, 'LB_I18N', {
    value: i18n,
    writable: true,
    configurable: true,
  })
}

// Top-level install so service singletons see `window.lbfa` at import time.
installLbfaGlobals()

// Exposed so tests that need to flip editPagesUrl or LB_I18N can do so without
// duplicating the install logic.
;(globalThis as any).__resetLbfaGlobals = installLbfaGlobals

beforeEach(() => {
  installLbfaGlobals()
})

export {}
