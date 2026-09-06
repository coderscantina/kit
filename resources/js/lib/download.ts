import type { DownloadedFile } from '~/api/client'

/**
 * Hand a fetched file to the browser's own download machinery.
 *
 * The object URL is revoked on the next frame rather than immediately:
 * Safari has not started the download yet when the click handler returns, and
 * a revoked URL there yields an empty file.
 */
export const saveFile = ({ blob, filename }: DownloadedFile): void => {
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')

  anchor.href = url
  anchor.download = filename
  anchor.rel = 'noopener'
  document.body.append(anchor)
  anchor.click()
  anchor.remove()

  requestAnimationFrame(() => URL.revokeObjectURL(url))
}
