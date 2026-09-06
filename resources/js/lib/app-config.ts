import type { Component } from 'vue'

import type { ColorMode } from '~/composables/useColorMode'
import type { AppRouteName } from '~/lib/access-control'

/**
 * The one file a derived app edits to rebrand and re-shape the shell.
 *
 * Nothing here decides *what* a user may see: navigation items and their
 * access rules stay in `lib/access-control.ts`, so a generated feature keeps
 * appearing without anyone touching this file. This decides how the shell
 * looks, how the items are grouped and which shell affordances exist.
 *
 * See `docs/app-shell.md`.
 */

export interface BrandConfig {
  /** Shown in the sidebar, the document title suffix and the auth pages. */
  name: string
  /** Used where the full name does not fit, e.g. the collapsed rail. */
  shortName?: string
  /** Iconify name, used when `logo` is not set. */
  icon: string
  /** A component (an imported SVG, a wordmark) that replaces the icon. */
  logo?: Component
  /** Where the brand button navigates. */
  homeRouteName: AppRouteName
}

export interface SidebarSection {
  /** i18n key for the group heading. Omit for an unlabelled group. */
  labelKey?: string
  /** Route names, in the order they should appear. */
  items: AppRouteName[]
}

export interface SidebarConfig {
  /**
   * Groups the nav items. A route name that appears in no section falls into
   * `overflowSection`, so a feature added by `make:feature` shows up without
   * an edit here.
   */
  sections: SidebarSection[]
  /**
   * Index of the section unlisted items land in. Clamped to a real section.
   * Account pages never land here: they live in `accountNavigationItems` and
   * hang off the account menu instead.
   */
  overflowSection: number
  /** Collapse to an icon rail. `false` pins the sidebar open and hides the toggle. */
  collapsible: boolean
  /** Start collapsed on first visit; after that the stored preference wins. */
  defaultCollapsed: boolean
  /** Drag the trailing edge to resize. Ignored while collapsed. */
  resizable: boolean
  /** Expanded width bounds and default, in pixels. */
  width: { default: number; min: number; max: number }
}

export interface MobileConfig {
  /**
   * How many nav items the bottom tab bar shows before the trailing "Menu"
   * tab, which opens the full drawer. Four is what fits a phone in one row.
   */
  tabBarItems: number
}

export interface ShellFeatures {
  /** ⌘K palette, its header trigger and the palette itself. */
  commandPalette: boolean
  /** `?` opens the generated shortcut reference. */
  shortcutsHelp: boolean
  /** Theme switch in the user menu. */
  colorModeToggle: boolean
  /** Language switch in the user menu. Off when only one locale ships. */
  localeSwitch: boolean
  /** Breadcrumb trail in the header. */
  breadcrumbs: boolean
  /** `/` focuses the page's first search field. */
  pageSearchShortcut: boolean
}

export interface AppConfig {
  brand: BrandConfig
  sidebar: SidebarConfig
  mobile: MobileConfig
  features: ShellFeatures
  /** Row heights and shell padding. `compact` trims roughly 20%. */
  density: 'comfortable' | 'compact'
  /** Applied on a first visit, before the user picks a mode. */
  defaultColorMode: ColorMode
  /** `%s` is the page title. Pages without a title get the brand name alone. */
  titleTemplate: (title: string) => string
}

/** Identity with a type annotation, so a typo in a derived app is a build error. */
export const defineAppConfig = (config: AppConfig): AppConfig => config

export const appConfig = defineAppConfig({
  brand: {
    name: 'Kit',
    shortName: 'K',
    icon: 'lucide:box',
    homeRouteName: 'dashboard',
  },
  sidebar: {
    sections: [
      { items: ['dashboard'] },
      { labelKey: 'nav.sections.manage', items: ['users', 'assistant'] },
    ],
    overflowSection: 1,
    collapsible: true,
    defaultCollapsed: false,
    resizable: true,
    width: { default: 248, min: 208, max: 360 },
  },
  mobile: {
    tabBarItems: 4,
  },
  features: {
    commandPalette: true,
    shortcutsHelp: true,
    colorModeToggle: true,
    localeSwitch: true,
    breadcrumbs: true,
    pageSearchShortcut: true,
  },
  density: 'comfortable',
  defaultColorMode: 'system',
  titleTemplate: (title) => `${title} · Kit`,
})
