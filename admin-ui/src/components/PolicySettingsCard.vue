<template>
  <v-card border="sm" flat>
    <!-- Sezione DocumentEmbed integrata -->
    <DocumentEmbed :type="policyType" />

    <v-divider class="my-6 bg-outline mx-auto border-opacity-100" style="width: 90%" thickness="1" />

    <!-- Sezione impostazioni esistente -->
    <v-card-title class="d-flex align-center">
      {{ title }}
    </v-card-title>

    <v-card-text>
      <v-row align="center" class="mb-4">
        <v-col cols="12" md="6">
          <div class="d-flex align-center">
            <label class="font-weight-bold" for="html-snippet-switch">Incorpora codice HTML in sostituzione del link</label>
            <v-tooltip contained max-width="400">
              <template #activator="{ props: tooltipProps }">
                <v-icon
                  v-bind="tooltipProps"
                  class="ml-2"
                  color="info"
                  icon="mdi-information-outline"
                  size="16"
                />
              </template>
              <template v-if="policyType === 'privacy_policy'" #default>
                Consente di inserire il contenuto della privacy policy direttamente nella pagina utilizzando il codice HTML, senza caricarlo come iframe tramite un link. <br>
                In questo modo il testo sarà parte integrante della pagina, potrà essere indicizzato dai motori di ricerca e si integrerà con lo stile grafico del sito.
              </template>
              <template v-else-if="policyType === 'cookie_policy'" #default>
                Consente di inserire il contenuto della cookie policy direttamente nella pagina utilizzando il codice HTML, senza caricarlo come iframe tramite un link. <br>
                In questo modo il testo sarà parte integrante della pagina, potrà essere indicizzato dai motori di ricerca e si integrerà con lo stile grafico del sito.
              </template>
              <template v-else-if="policyType === 'terms_of_service'" #default>
                Consente di inserire il contenuto dell'informativa sulle Condizioni Generali di Vendita direttamente nella pagina utilizzando il codice HTML, senza caricarlo come iframe tramite un link. <br>
                In questo modo il testo sarà parte integrante della pagina, potrà essere indicizzato dai motori di ricerca e si integrerà con lo stile grafico del sito.
              </template>
              <template v-else-if="policyType === 'accessibility_declaration'" #default>
                Consente di inserire il contenuto della dichiarazione di accessibilità direttamente nella pagina utilizzando il codice HTML, senza caricarlo come iframe tramite un link. <br>
                In questo modo il testo sarà parte integrante della pagina, potrà essere indicizzato dai motori di ricerca e si integrerà con lo stile grafico del sito.
              </template>
            </v-tooltip>
          </div>
        </v-col>
        <v-col cols="12" md="6">
          <div class="d-flex justify-end">
            <v-switch
              id="html-snippet-switch"
              color="primary"
              hide-details
              inset
              :model-value="useHtmlSnippet"
              @update:model-value="(value: boolean | null) => emit('update:use-html-snippet', !!value)"
            />
          </div>
        </v-col>
      </v-row>

      <v-row class="mb-4">
        <v-col class="pb-0" cols="12">
          <div class="d-flex align-center">
            <label v-if="policyType === 'privacy_policy'" class="font-weight-bold" for="policy-page-select">Pagina della privacy policy</label>
            <label v-else-if="policyType === 'cookie_policy'" class="font-weight-bold" for="policy-page-select">Pagina della cookie policy</label>
            <label v-else-if="policyType === 'terms_of_service'" class="font-weight-bold" for="policy-page-select">Pagina delle Condizioni Generali di Vendita</label>
            <label v-else-if="policyType === 'accessibility_declaration'" class="font-weight-bold" for="policy-page-select">Pagina della dichiarazione di accessibilità</label>
            <v-tooltip contained max-width="400">
              <template #activator="{ props: tooltipProps }">
                <v-icon
                  v-bind="tooltipProps"
                  class="ml-2"
                  color="info"
                  icon="mdi-information-outline"
                  size="16"
                />
              </template>
              <template v-if="policyType === 'privacy_policy'" #default>
                È la pagina in cui viene mostrato il contenuto della privacy policy. <br>
                Il testo viene inserito direttamente nella pagina selezionata.
              </template>
              <template v-else-if="policyType === 'cookie_policy'" #default>
                È la pagina in cui viene mostrato il contenuto della cookie policy. <br>
                Il testo viene inserito direttamente nella pagina selezionata.
              </template>
              <template v-else-if="policyType === 'terms_of_service'" #default>
                È la pagina in cui viene mostrato il contenuto dell'informativa Condizioni Generali di Vendita. <br>
                Il testo viene inserito direttamente nella pagina selezionata.
              </template>
              <template v-else-if="policyType === 'accessibility_declaration'" #default>
                È la pagina in cui viene mostrato il contenuto della dichiarazione di accessibilità. <br>
                Il testo viene inserito direttamente nella pagina selezionata.
              </template>
            </v-tooltip>
          </div>
        </v-col>
        <v-col cols="12">
          <v-select
            id="policy-page-select"
            aria-label="Pagina"
            clearable
            density="compact"
            :error="!isPageSelectionValid"
            :error-messages="pageSelectionError"
            hide-details="auto"
            item-title="title"
            item-value="value"
            :items="policyPages"
            :model-value="policyPage"
            placeholder="Seleziona una pagina"
            variant="outlined"
            @update:model-value="(value: string | null) => emit('update:policy-page', value)"
          />
          <div class="text-caption">
            <v-divider class="mt-1" />
            <template v-if="policyPage">
              <a class="text-primary text-decoration-none" :href="editPagesUrl" rel="noopener noreferrer" target="_blank">
                Verifica le pagine CMS del tuo sito
              </a><br>
              <span class="text-error">
                La pagina CMS selezionata verrà sovrascritta
              </span>
            </template>
          </div>
        </v-col>
      </v-row>

      <v-row class="mb-4">
        <v-col class="pb-0" cols="12">
          <div class="d-flex align-center">
            <label class="font-weight-bold" for="policy-url-input">Link al documento</label>
            <v-tooltip contained max-width="400">
              <template #activator="{ props: tooltipProps }">
                <v-icon
                  v-bind="tooltipProps"
                  class="ml-2"
                  color="info"
                  icon="mdi-information-outline"
                  size="16"
                />
              </template>
              <template v-if="policyType === 'privacy_policy'" #default>
                È il link al documento della privacy policy generato da LegalBlink per Aruba. <br>
                Il sistema utilizzerà automaticamente questo documento per mostrarne i contenuti nella pagina scelta.
              </template>
              <template v-else-if="policyType === 'cookie_policy'" #default>
                È il link al documento della cookie policy generato da LegalBlink per Aruba. <br>
                Il sistema utilizzerà automaticamente questo documento per mostrarne i contenuti nella pagina scelta.
              </template>
              <template v-else-if="policyType === 'terms_of_service'" #default>
                È il link al documento dell'informativa sulle Condizioni Generali di Vendita generato da LegalBlink per Aruba. <br>
                Il sistema utilizzerà automaticamente questo documento per mostrarne i contenuti nella pagina scelta.
              </template>
              <template v-else-if="policyType === 'accessibility_declaration'" #default>
                È il link al documento della dichiarazione di accessibilità generato da LegalBlink per Aruba. <br>
                Il sistema utilizzerà automaticamente questo documento per mostrarne i contenuti nella pagina scelta.
              </template>
            </v-tooltip>
          </div>
        </v-col>
        <v-col cols="12">
          <v-text-field
            id="policy-url-input"
            aria-label="URL"
            density="compact"
            hide-details
            :model-value="policyUrl"
            readonly
            variant="outlined"
          >
            <template #append-inner>
              <v-tooltip
                aria-label="Informazioni per copiare il link"
                max-width="400"
                role="tooltip"
                text="Copia link"
              >
                <template #activator="{ props: tooltipProps }">
                  <v-btn
                    v-bind="tooltipProps"
                    aria-label="Copia link negli appunti"
                    icon="mdi-content-copy"
                    size="small"
                    variant="text"
                    @click="copyPolicyUrl"
                  />
                </template>
              </v-tooltip>
            </template>
          </v-text-field>
        </v-col>
      </v-row>

      <v-row class="mb-4">
        <v-col class="pb-0" cols="12">
          <div class="d-flex align-center">
            <label class="font-weight-bold" for="shortcode-input">Shortcode</label>
            <v-tooltip contained max-width="400">
              <template #activator="{ props: tooltipProps }">
                <v-icon
                  v-bind="tooltipProps"
                  class="ml-2"
                  color="info"
                  icon="mdi-information-outline"
                  size="16"
                />
              </template>
              <template v-if="policyType === 'privacy_policy'" #default>
                Copiando e incollando questo codice in qualsiasi punto del tuo sito che supporti gli shortcode è possibile inserire il testo della privacy policy all'interno di una pagina o articolo.
              </template>
              <template v-else-if="policyType === 'cookie_policy'" #default>
                Copiando e incollando questo codice in qualsiasi punto del tuo sito che supporti gli shortcode è possibile inserire il testo della cookie policy all'interno di una pagina o articolo.
              </template>
              <template v-else-if="policyType === 'terms_of_service'" #default>
                Copiando e incollando questo codice in qualsiasi punto del tuo sito che supporti gli shortcode è possibile inserire il testo dell'informativa sulle Condizioni Generali di Vendita all'interno di una pagina o articolo.
              </template>
              <template v-else-if="policyType === 'accessibility_declaration'" #default>
                Copiando e incollando questo codice in qualsiasi punto del tuo sito che supporti gli shortcode è possibile inserire il testo della dichiarazione di accessibilità all'interno di una pagina o articolo.
              </template>
            </v-tooltip>
          </div>
        </v-col>
        <v-col cols="12">
          <v-text-field
            id="shortcode-input"
            density="compact"
            hide-details
            :model-value="shortcode"
            readonly
            variant="outlined"
          >
            <template #append-inner>
              <v-tooltip
                aria-label="Informazioni per copiare lo shortcode"
                max-width="400"
                role="tooltip"
                text="Copia shortcode"
              >
                <template #activator="{ props: tooltipProps }">
                  <v-btn
                    v-bind="tooltipProps"
                    aria-label="Copia shortcode negli appunti"
                    icon="mdi-content-copy"
                    size="small"
                    variant="text"
                    @click="copyShortcode"
                  />
                </template>
              </v-tooltip>
            </template>
          </v-text-field>
        </v-col>
      </v-row>
    </v-card-text>

    <v-divider class="mb-4 bg-outline mx-auto border-opacity-100" thickness="1" />

    <v-card-actions class="px-4 pb-4">
      <v-btn
        color="primary"
        :disabled="isSaveDisabled"
        :loading="isUpdating"
        variant="flat"
        @click="handleSave"
      >
        Salva
      </v-btn>
      <v-spacer />
    </v-card-actions>

    <!-- Snackbar per conferma copia -->
    <v-snackbar
      v-model="showCopySuccess"
      color="info"
      location="top"
      timeout="2000"
    >
      <v-icon class="mr-2" icon="mdi-content-copy" />
      {{ copySuccessMessage }}
    </v-snackbar>
  </v-card>
</template>

<script lang="ts" setup>
  import type { PolicyKey } from '@/services/DocumentService'
  import { computed, ref } from 'vue'
  import { useAppStore } from '@/stores/app'

  const editPagesUrl = window.lbfa.editPagesUrl || '#'
  const store = useAppStore()

  interface PolicyPageOption {
    title: string
    value: string | null
  }

  interface Props {
    title: string
    useHtmlSnippet: boolean
    policyPage: string | null
    policyUrl: string
    shortcode: string
    policyPages: PolicyPageOption[]
    policyType: PolicyKey
  }

  const props = withDefaults(defineProps<Props>(), {})

  const showCopySuccess = ref(false)
  const copySuccessMessage = ref('Shortcode copiato negli appunti')

  // Computed per validazione
  const isPageRequired = computed(() => props.useHtmlSnippet)
  const hasPageSelected = computed(() => props.policyPage !== null && props.policyPage !== '')
  const isPageSelectionValid = computed(() => !isPageRequired.value || hasPageSelected.value)
  const isSaveDisabled = computed(() => !isPageSelectionValid.value)
  const isUpdating = computed(() =>
    props.policyType === 'accessibility_declaration'
      ? store.isUpdatingAccessibilityDeclarationPage
      : store.getIsUpdatingPage,
  )

  // Messaggio di errore per la selezione della pagina
  const pageSelectionError = computed(() => {
    if (!isPageRequired.value) return ''
    if (hasPageSelected.value) return ''

    if (props.useHtmlSnippet) {
      return 'È necessario selezionare una pagina quando "Incorpora codice HTML in sostituzione del link" è attivo'
    }
    return ''
  })

  const emit = defineEmits<{
    'update:use-html-snippet': [value: boolean]
    'update:enable-policy-page': [value: boolean]
    'update:policy-page': [value: string | null]
    'save': []
    'page-updated': [result: any]
  }>()

  async function handleSave () {
    try {
      const pageId = props.policyPage ? Number.parseInt(props.policyPage, 10) : 0
      const language = store.getSelectedLanguage

      // Accessibility declaration uses a dedicated endpoint that never touches
      // GDPR options (S#7701 mixed-mode separation).
      const result = props.policyType === 'accessibility_declaration'
        ? await store.updateAccessibilityDeclarationPage({
            page_id: pageId,
            use_html_snippet: props.useHtmlSnippet,
            language,
          })
        : await store.updatePageContent({
            policy_type: props.policyType,
            page_id: pageId,
            use_html_snippet: props.useHtmlSnippet,
            language,
          })

      emit('page-updated', result)
      emit('save')
    } catch (error) {
      console.error('Errore durante l\'aggiornamento della pagina:', error)
    }
  }

  async function copyShortcode () {
    try {
      if (props.shortcode) {
        await navigator.clipboard.writeText(props.shortcode)
        copySuccessMessage.value = 'Shortcode copiato negli appunti'
        showCopySuccess.value = true
      }
    } catch (error) {
      console.error('Errore durante la copia dello shortcode:', error)
      // Fallback per browser che non supportano navigator.clipboard
      const textArea = document.createElement('textarea')
      textArea.value = props.shortcode
      document.body.append(textArea)
      textArea.select()
      document.execCommand('copy')
      textArea.remove()
      copySuccessMessage.value = 'Shortcode copiato negli appunti'
      showCopySuccess.value = true
    }
  }

  async function copyPolicyUrl () {
    try {
      if (props.policyUrl) {
        await navigator.clipboard.writeText(props.policyUrl)
        copySuccessMessage.value = 'Link copiato negli appunti'
        showCopySuccess.value = true
      }
    } catch (error) {
      console.error('Errore durante la copia del link:', error)
      // Fallback per browser che non supportano navigator.clipboard
      const textArea = document.createElement('textarea')
      textArea.value = props.policyUrl
      document.body.append(textArea)
      textArea.select()
      document.execCommand('copy')
      textArea.remove()
      copySuccessMessage.value = 'Link copiato negli appunti'
      showCopySuccess.value = true
    }
  }
</script>

<style scoped>
  .cursor-help {
    cursor: help;
  }
</style>
