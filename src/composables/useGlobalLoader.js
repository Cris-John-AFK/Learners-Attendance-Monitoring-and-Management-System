import { ref } from 'vue'

// Global loading state management
const globalLoadingState = ref(false)
const globalLoadingText = ref('Loading...')
const globalLoadingSize = ref('medium')

export function useGlobalLoader() {
    const showGlobalLoader = (text = 'Loading...', size = 'medium') => {
        globalLoadingText.value = text
        globalLoadingSize.value = size
        globalLoadingState.value = true
    }

    const hideGlobalLoader = () => {
        globalLoadingState.value = false
    }

    const setLoadingText = (text) => {
        globalLoadingText.value = text
    }

    const setLoadingSize = (size) => {
        globalLoadingSize.value = size
    }

    return {
        // State
        isGlobalLoading: globalLoadingState,
        globalLoadingText,
        globalLoadingSize,
        
        // Actions
        showGlobalLoader,
        hideGlobalLoader,
        setLoadingText,
        setLoadingSize
    }
}

// Export for direct usage without composable
export const globalLoader = {
    show: (text = 'Loading...', size = 'medium') => {
        globalLoadingText.value = text
        globalLoadingSize.value = size
        globalLoadingState.value = true
    },
    hide: () => {
        globalLoadingState.value = false
    },
    setText: (text) => {
        globalLoadingText.value = text
    },
    setSize: (size) => {
        globalLoadingSize.value = size
    },
    get isLoading() {
        return globalLoadingState.value
    },
    get text() {
        return globalLoadingText.value
    },
    get size() {
        return globalLoadingSize.value
    }
}
