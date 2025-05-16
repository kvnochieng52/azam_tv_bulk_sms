<template>
  <div v-if="links.length > 3">
    <nav aria-label="Page navigation">
      <ul class="pagination justify-content-center">
        <li 
          v-for="(link, key) in links" 
          :key="key"
          class="page-item"
          :class="{ 'active': link.active, 'disabled': !link.url }"
        >
          <a 
            class="page-link" 
            :href="link.url"
            v-html="link.label"
            @click.prevent="visitPage(link)"
          ></a>
        </li>
      </ul>
    </nav>
  </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3'

const props = defineProps({
  links: Array
})

function visitPage(link) {
  if (link.url && !link.active) {
    router.visit(link.url, { preserveScroll: true })
  }
}
</script>
