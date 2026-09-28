import type { DownloadedFile } from '~/api/client'
import { BaseResource } from '~/api/resources/base-resource'
import type { ExportFormat } from '~/components/ui/data-table'

/** All, active accounts only, or outstanding invitations only. */
export type PeopleSegment = 'all' | 'active' | 'pending'

/**
 * The query bag `useTableQueryState` produces: `page`, `per_page`, `sort`
 * (`+column` / `-column`), the free-text `q`, and whatever filter chips are
 * on. The list itself is the reactive `people.list`; this resource keeps the
 * export, which is a download and so stays REST.
 */
export type PeopleListQuery = Record<string, string | number>

export class PeopleResource extends BaseResource {
  protected basePath = '/api/people'

  /**
   * The whole filtered list as a file. `page` and `per_page` are dropped: an
   * export is of the result set, not of the page someone happens to be on.
   */
  export(query: PeopleListQuery, format: ExportFormat): Promise<DownloadedFile> {
    const { page: _page, per_page: _perPage, ...rest } = query

    return this.client.download(`${this.basePath}/export`, { ...rest, format }, `people.${format}`)
  }
}
