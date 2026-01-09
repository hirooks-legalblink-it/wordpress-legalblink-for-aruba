<template>
  <!--  <v-card variant="flat">-->
  <!-- Tab delle lingue al posto della select -->
  <!--  <v-card-text class="pb-0">-->
  <v-tabs
    v-if="languages.length > 0"
    v-model="selectedLang"
    color="primary"
    density="comfortable"
    grow
    show-arrows
  >
    <v-tab
      v-for="lang in languages"
      :key="lang.code"
      :value="lang.code"
    >
      {{ lang.name }}
    </v-tab>
  </v-tabs>
  <v-progress-circular v-else-if="loading" color="primary" indeterminate size="32" />
  <!--  </v-card-text>-->

  <v-divider class="mb-4" />

  <!-- Loading state -->
  <v-card-text v-if="loading" class="text-center py-8">
    <v-progress-circular color="primary" indeterminate size="32" />
    <v-card-text class="text-body-2 text-medium-emphasis">
      {{ i18n.loading }}
    </v-card-text>
  </v-card-text>

  <!-- Contenuto documenti -->
  <v-card-text v-else>
    <template v-if="filteredDocuments.length > 0">
      <div v-for="doc in filteredDocuments" :key="doc.language" class="d-flex flex-column ga-4">
        <!-- Sezioni iframe e link ottimizzate -->
        <v-row v-for="section in documentSections" :key="section.type">
          <v-col class="text-body-2 pb-0" cols="12">
            <span v-html="section.label" />
          </v-col>
          <v-col class="d-flex align-center justify-center" cols="12" md="1">
            <v-btn
              :color="section.buttonColor"
              icon="mdi-content-copy"
              size="small"
              :variant="section.buttonVariant"
              @click="copyToClipboard(section.content, section.type)"
            />
          </v-col>
          <v-col cols="12" md="11">
            <v-sheet class="pa-3 text-caption word-break-all bg-outline" rounded>
              {{ section.content }}
            </v-sheet>
          </v-col>
        </v-row>
      </div>
    </template>

    <!-- Stato vuoto -->
    <template v-else>
      <v-alert
        border="start"
        border-color="primary"
        color="warning"
        type="warning"
        variant="tonal"
      >
        {{ i18n.no_doc }}
      </v-alert>
    </template>
  </v-card-text>

  <!-- Snackbar per feedback copia -->
  <v-snackbar
    v-model="copySnackbar.show"
    color="success"
    location="top"
    :timeout="2000"
  >
    <v-icon start>mdi-check</v-icon>
    {{ copySnackbar.message }}
  </v-snackbar>
<!--  </v-card>-->
</template>

<script lang="ts" setup>
  import type { PolicyDocument, PolicyKey } from '@/services'
  import { useAppStore } from '@/stores/app'

  const store = useAppStore()

  // Computed per accedere alle lingue dallo store
  const languages = computed(() => store.getLanguages)
  const selectedLang = computed({
    get: () => store.getSelectedLanguage,
    set: value => store.setSelectedLanguage(value),
  })

  // Carica le stringhe localizzate da WordPress (iniettate via wp_localize_script)
  const i18n = (window as any).LB_I18N || {
    lang_label: 'Lingua:',
    loading: 'Caricamento documento...',
    no_doc: 'Nessun documento disponibile per questo tipo.',
    iframe_label: '<b>Per integrare l\'iframe nel tuo sito</b>, copia e incolla questo codice nella pagina desiderata:',
    link_label: '<b>Per inserire il link</b>, inserisci il seguente codice nella versione HTML del sito:',
    save_button: 'Salva Codici Cookie',
    save_cgv_button: 'Salva Codici CGV',
  }

  const loading = ref(false)
  const currentDocument = computed<PolicyDocument | null>(() => store.getDocument(type.value))
  const copySnackbar = ref({
    show: false,
    message: '',
  })

  // Accetta la prop type per il tipo documento
  const props = defineProps<{ type: PolicyKey }>()
  // Usa la prop type se fornita, altrimenti fallback a 'cookie_policy'
  const type = ref(props.type || 'cookie_policy')

  const filteredDocuments = computed(() => {
    if (!currentDocument.value) return []

    const doc = currentDocument.value
    const lang = selectedLang.value

    const iframe = doc.languages?.[lang].url.html ? `<iframe src="${doc.languages?.[lang].url.html}" width="100%" height="600" style="border: 0;" title="${getDocumentTypeLabel(type.value)} ${lang.toUpperCase()}"></iframe>` : ''
    const linkHtml = doc.languages?.[lang].url.html ? `<a href="${doc.languages?.[lang].url.html}" target="_blank" rel="noopener noreferrer">Visualizza ${getDocumentTypeLabel(type.value)}</a>` : ''

    return [{
      language: lang,
      iframe: iframe,
      url: linkHtml,
    }]
  })

  // Computed per le sezioni del documento ottimizzate
  const documentSections = computed(() => {
    if (filteredDocuments.value.length === 0) return []

    const doc = filteredDocuments.value[0]

    return [
      {
        type: 'iframe' as const,
        label: i18n.iframe_label,
        icon: 'mdi-code-tags',
        content: doc.iframe,
        variant: 'tonal' as const,
        buttonColor: 'primary',
        buttonVariant: 'flat' as const,
      },
      {
        type: 'link' as const,
        label: i18n.link_label,
        icon: 'mdi-link',
        content: doc.url,
        variant: 'tonal' as const,
        buttonColor: 'primary',
        buttonVariant: 'flat' as const,
      },
    ].filter(section => section.content) // Rimuovi sezioni vuote
  })

  function getDocumentTypeLabel (docType: PolicyKey): string {
    switch (docType) {
      case 'cookie_policy': {
        return 'Cookie Policy'
      }
      case 'privacy_policy': {
        return 'Privacy Policy'
      }
      case 'terms_of_service': {
        return 'Condizioni generali di vendita'
      }
      default: {
        return 'Documento'
      }
    }
  }

  async function copyToClipboard (text: string, type: 'iframe' | 'link') {
    try {
      await navigator.clipboard.writeText(text)
      copySnackbar.value = {
        show: true,
        message: type === 'iframe' ? 'Codice iframe copiato!' : 'Codice link copiato!',
      }
    } catch {
      copySnackbar.value = {
        show: true,
        message: 'Errore nella copia negli appunti',
      }
    }
  }
</script>

<style scoped>
  .word-break-all {
    word-break: break-all;
    word-wrap: break-word;
  }

  .user-select-all {
    user-select: all;
  }

  code {
    font-family: 'Roboto Mono', 'Courier New', monospace;
    font-size: 0.875rem;
    line-height: 1.5;
  }
</style>
