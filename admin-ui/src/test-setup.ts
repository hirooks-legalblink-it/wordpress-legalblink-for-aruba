/**
 * Vitest setup: provides the `window.lbfa` and `window.LB_I18N` globals that
 * BaseApiService and several components rely on when running outside of the
 * WordPress admin shell.
 */

declare global {
  interface Window {
    lbfa: {
      root: string
      nonce: string
      editPagesUrl?: string
    }
    LB_I18N?: Record<string, string>
  }
}

beforeEach(() => {
  Object.defineProperty(window, 'lbfa', {
    value: {
      root: 'http://localhost/wp-json/lbfa/v1',
      nonce: 'test-nonce',
      editPagesUrl: 'http://localhost/wp-admin/edit.php?post_type=page',
    },
    writable: true,
    configurable: true,
  })

  Object.defineProperty(window, 'LB_I18N', {
    value: {},
    writable: true,
    configurable: true,
  })
})

export {}
