<script setup lang="ts">
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '~/components/ui/breadcrumb'
import { useCurrentPageMeta } from '~/composables/usePageMeta'

const { breadcrumbs } = useCurrentPageMeta()
</script>

<template>
  <Breadcrumb v-if="breadcrumbs.length > 0">
    <BreadcrumbList class="flex-nowrap">
      <template
        v-for="(crumb, index) in breadcrumbs"
        :key="`${crumb.label}-${index}`"
      >
        <BreadcrumbSeparator v-if="index > 0" />
        <BreadcrumbItem class="min-w-0">
          <BreadcrumbLink
            v-if="crumb.to"
            as-child
          >
            <RouterLink
              :to="crumb.to"
              class="truncate"
            >
              {{ crumb.label }}
            </RouterLink>
          </BreadcrumbLink>
          <BreadcrumbPage
            v-else
            class="truncate font-medium text-primary"
          >
            {{ crumb.label }}
          </BreadcrumbPage>
        </BreadcrumbItem>
      </template>
    </BreadcrumbList>
  </Breadcrumb>
</template>
