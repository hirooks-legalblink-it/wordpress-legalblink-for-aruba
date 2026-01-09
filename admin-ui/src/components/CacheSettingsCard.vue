<template>
  <v-card border="sm" flat>
    <v-card-title class="d-flex align-center">
      Impostazioni cache
    </v-card-title>

    <v-card-text>
      <v-alert
        class="mb-4"
        type="info"
        variant="tonal"
      >
        Per aggiornare i testi delle policy puoi cancellare la cache
      </v-alert>

      <v-row align="center" class="mb-4">
        <v-col class="pb-0" cols="12">
          <div class="d-flex align-center">
            <label class="font-weight-bold" for="cache-duration-input">Durata della cache (giorni)</label>
            <v-tooltip
              aria-label="Informazioni sulla durata cache"
              contained
              max-width="400"
              role="tooltip"
            >
              <template #activator="{ props }">
                <v-icon
                  v-bind="props"
                  aria-label="Informazioni sulla durata della cache"
                  class="ml-2"
                  color="info"
                  icon="mdi-information-outline"
                  size="16"
                />
              </template>
              <template #default>
                Indica la durata di tempo in cui i contenuti delle policy restano memorizzate nella cache. <br>
                Al termine di questo periodo, i contenuti vengono aggiornati automaticamente.
              </template>
            </v-tooltip>
          </div>
        </v-col>
        <v-col cols="12">
          <v-number-input
            id="cache-duration-input"
            density="compact"
            hide-details
            :min="1"
            :model-value="cacheDuration"
            :step="1"
            variant="outlined"
            @update:model-value="$emit('update:cache-duration', Number($event) || 1)"
          />
        </v-col>
      </v-row>
    </v-card-text>

    <v-divider class="mb-4 bg-outline mx-auto border-opacity-100" thickness="1" />

    <v-card-actions class="px-4 pb-4">
      <v-btn
        color="primary"
        variant="flat"
        @click="$emit('save')"
      >
        Salva
      </v-btn>
      <v-btn
        color="secondary"
        variant="outlined"
        @click="$emit('clear-cache')"
      >
        Cancella cache
      </v-btn>
      <v-spacer />
    </v-card-actions>
  </v-card>
</template>

<script lang="ts" setup>
  interface Props {
    cacheDuration: number
  }

  defineProps<Props>()

  defineEmits<{
    'update:cache-duration': [value: number]
    'save': []
    'clear-cache': []
  }>()
</script>
