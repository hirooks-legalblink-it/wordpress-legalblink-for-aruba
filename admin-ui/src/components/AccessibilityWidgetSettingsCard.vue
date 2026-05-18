<template>
  <v-card border="sm" flat>
    <v-card-title class="d-flex align-center">
      Widget di accessibilità
    </v-card-title>

    <v-card-text>
      <v-alert
        v-if="!widget?.available"
        border="start"
        class="mb-4"
        type="info"
        variant="outlined"
      >
        Il widget di accessibilità non è disponibile per questo account.
      </v-alert>

      <v-alert
        v-else-if="widget.warnings.length > 0"
        border="start"
        class="mb-4"
        type="warning"
        variant="outlined"
      >
        <strong>{{ warningTitle }}</strong>
        <div class="text-body-2 mt-1">{{ warningMessage }}</div>
      </v-alert>

      <v-alert
        v-else-if="widget.configured"
        border="start"
        class="mb-4"
        type="success"
        variant="outlined"
      >
        Widget configurato per il dominio <strong>{{ widget.domain || '—' }}</strong>.
      </v-alert>

      <v-row align="center" class="mb-4">
        <v-col cols="12" md="6">
          <div class="d-flex align-center">
            <label class="font-weight-bold" for="accessibility-widget-switch">
              Inietta lo snippet del widget nel sito
            </label>
            <v-tooltip contained max-width="400" role="tooltip">
              <template #activator="{ props: tooltipProps }">
                <v-icon
                  v-bind="tooltipProps"
                  class="ml-2"
                  color="info"
                  icon="mdi-information-outline"
                  size="16"
                />
              </template>
              <template #default>
                Quando attivo, il plugin inserisce automaticamente lo snippet del widget di accessibilità nel footer di tutte le pagine del sito.<br>
                La configurazione visiva del widget (colori, posizione, dominio) è gestita esclusivamente dal pannello di LegalBlink: il plugin si limita a iniettare lo snippet pronto.
              </template>
            </v-tooltip>
          </div>
        </v-col>
        <v-col cols="12" md="6">
          <div class="d-flex justify-end">
            <v-switch
              id="accessibility-widget-switch"
              color="primary"
              :disabled="!widget?.configured"
              hide-details
              inset
              :model-value="localEnabled"
              @update:model-value="(value: boolean | null) => emit('update:enabled', !!value)"
            />
          </div>
        </v-col>
      </v-row>

      <v-row v-if="widget?.html" class="mb-4">
        <v-col cols="12">
          <div class="d-flex align-center">
            <label class="font-weight-bold" for="accessibility-widget-snippet">Snippet HTML</label>
            <v-tooltip contained max-width="400" role="tooltip">
              <template #activator="{ props: tooltipProps }">
                <v-icon
                  v-bind="tooltipProps"
                  class="ml-2"
                  color="info"
                  icon="mdi-information-outline"
                  size="16"
                />
              </template>
              <template #default>
                È il codice del widget generato da LegalBlink. Può essere copiato per integrazioni manuali; in alternativa, attiva l'iniezione automatica con il toggle qui sopra.
              </template>
            </v-tooltip>
          </div>
        </v-col>
        <v-col cols="12">
          <v-row>
            <v-col class="d-flex align-center justify-center" cols="12" md="1">
              <v-btn
                color="primary"
                icon="mdi-content-copy"
                size="small"
                variant="flat"
                @click="copyWidgetSnippet"
              />
            </v-col>
            <v-col cols="12" md="11">
              <v-sheet class="pa-3 text-caption word-break-all bg-outline" rounded>
                {{ widget.html }}
              </v-sheet>
            </v-col>
          </v-row>
        </v-col>
      </v-row>
    </v-card-text>

    <v-divider class="mb-4 bg-outline mx-auto border-opacity-100" thickness="1" />

    <v-card-actions class="px-4 pb-4">
      <v-btn
        color="primary"
        :disabled="!widget?.configured"
        :loading="saving"
        variant="flat"
        @click="emit('save')"
      >
        Salva
      </v-btn>
      <v-spacer />
    </v-card-actions>

    <v-snackbar
      v-model="showCopySuccess"
      color="info"
      location="top"
      timeout="2000"
    >
      <v-icon class="mr-2" icon="mdi-content-copy" />
      Snippet copiato negli appunti
    </v-snackbar>
  </v-card>
</template>

<script lang="ts" setup>
  import type { AccessibilityWidget } from '@/services/AccessibilityService'
  import { computed, ref } from 'vue'

  interface Props {
    widget: AccessibilityWidget | null
    localEnabled: boolean
    saving?: boolean
  }

  const props = withDefaults(defineProps<Props>(), { saving: false })

  const emit = defineEmits<{
    'update:enabled': [value: boolean]
    'save': []
  }>()

  const showCopySuccess = ref(false)

  // Surface a single warning (the first) — the three states
  // (configuration_missing / domain_mismatch / configuration_expired) are
  // mutually distinct on the backend.
  const firstWarning = computed(() => props.widget?.warnings?.[0] ?? null)

  const warningTitle = computed(() => {
    switch (firstWarning.value) {
      case 'configuration_missing': return 'Widget non configurato'
      case 'domain_mismatch': return 'Dominio non coerente'
      case 'configuration_expired': return 'Configurazione scaduta'
      default: return 'Widget non disponibile'
    }
  })

  const warningMessage = computed(() => {
    switch (firstWarning.value) {
      case 'configuration_missing':
        return 'Completa la configurazione del widget nel pannello di LegalBlink prima di poterlo integrare nel sito.'
      case 'domain_mismatch':
        return `Il widget è configurato per un dominio diverso${props.widget?.domain ? ` (${props.widget.domain})` : ''}. Aggiorna la configurazione nel pannello di LegalBlink.`
      case 'configuration_expired':
        return 'La configurazione del widget è scaduta. Rinnova l\'abbonamento o aggiorna la configurazione dal pannello di LegalBlink.'
      default:
        return 'Il widget non è attualmente disponibile per l\'iniezione automatica.'
    }
  })

  async function copyWidgetSnippet () {
    if (!props.widget?.html) return
    try {
      await navigator.clipboard.writeText(props.widget.html)
      showCopySuccess.value = true
    } catch {
      const textArea = document.createElement('textarea')
      textArea.value = props.widget.html
      document.body.append(textArea)
      textArea.select()
      document.execCommand('copy')
      textArea.remove()
      showCopySuccess.value = true
    }
  }
</script>

<style scoped>
  .word-break-all {
    word-break: break-all;
    word-wrap: break-word;
  }
</style>
