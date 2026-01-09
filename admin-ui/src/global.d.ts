export {}

declare global {
  interface Window {
    lbfa: {
      baseUrl: string
      root: string
      nonce: string
      editPagesUrl: string
    }
  }
}
