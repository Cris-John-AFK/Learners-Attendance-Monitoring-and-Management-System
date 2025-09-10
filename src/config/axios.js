import axios from 'axios';

// Define API base URL and export it
export const API_BASE_URL = 'http://localhost:8000/api';

// Create axios instance with the correct backend URL
const api = axios.create({
    baseURL: 'http://localhost:8000',
    withCredentials: true,
    timeout: 15000, // Increased to 15 seconds for better reliability
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'Cache-Control': 'no-cache',
        Pragma: 'no-cache'
    }
});

// Memory cache for responses
const responseCache = new Map();
const CACHE_TTL = 300000; // 5 minutes
const CRITICAL_PATHS = ['/api/sections', '/api/teachers', '/api/subjects']; // Paths that need special handling
const NO_CACHE_PATHS = ['/api/teachers', '/api/attendance-sessions', '/teachers/', '/attendance-sessions']; // Paths that should never be cached

// Add request interceptor
api.interceptors.request.use(
    async (config) => {
        // Check if this is a critical path that needs special handling
        const isCriticalPath = CRITICAL_PATHS.some((path) => config.url.includes(path));

        // For critical paths, use a more reasonable timeout
        if (isCriticalPath) {
            config.timeout = 10000; // Increased to 10 seconds for critical paths

            // Set a flag to identify critical paths
            config.critical = true;
        }

        // Check if this path should never be cached
        const shouldNotCache = NO_CACHE_PATHS.some((path) => config.url.includes(path));

        // Add cache-busting headers for no-cache paths
        if (shouldNotCache) {
            config.headers['Cache-Control'] = 'no-cache, no-store, must-revalidate';
            config.headers['Pragma'] = 'no-cache';
            config.headers['Expires'] = '0';
            config.params = config.params || {};
            config.params._t = Date.now(); // Cache buster
        }

        // For GET requests, try to use cached data while fetching fresh data (unless it's a no-cache path)
        if (config.method === 'get' && !config.background && !shouldNotCache) {
            const cacheKey = `${config.url}${JSON.stringify(config.params || {})}`;
            const localStorageKey = `api_cache_${cacheKey}`;

            // Try memory cache first
            const cachedResponse = responseCache.get(cacheKey);
            if (cachedResponse && Date.now() - cachedResponse.timestamp < CACHE_TTL) {
                // Start background refresh if data is getting stale
                if (Date.now() - cachedResponse.timestamp > CACHE_TTL / 2) {
                    setTimeout(() => {
                        api.get(config.url, {
                            ...config,
                            background: true,
                            params: config.params
                        }).catch(() => {});
                    }, 0);
                }
                return Promise.resolve({
                    ...config,
                    cached: true,
                    data: cachedResponse.data
                });
            }

            // Then try localStorage
            try {
                const localData = localStorage.getItem(localStorageKey);
                if (localData) {
                    const { data, timestamp } = JSON.parse(localData);
                    if (Date.now() - timestamp < CACHE_TTL) {
                        // Update memory cache
                        responseCache.set(cacheKey, { data, timestamp });
                        // Start background refresh if data is getting stale
                        if (Date.now() - timestamp > CACHE_TTL / 2) {
                            setTimeout(() => {
                                api.get(config.url, {
                                    ...config,
                                    background: true,
                                    params: config.params
                                }).catch(() => {});
                            }, 0);
                        }
                        return Promise.resolve({
                            ...config,
                            cached: true,
                            data: data
                        });
                    }
                }
            } catch (error) {
                console.warn('Error reading from localStorage:', error);
            }
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Add response interceptor
api.interceptors.response.use(
    (response) => {
        // Don't cache already cached responses
        if (response.cached) {
            return response;
        }

        // Check if this path should never be cached
        const shouldNotCache = NO_CACHE_PATHS.some((path) => response.config.url.includes(path));

        // Cache successful GET responses (unless it's a no-cache path)
        if (response.config.method === 'get' && !response.config.background && !shouldNotCache) {
            const cacheKey = `${response.config.url}${JSON.stringify(response.config.params || {})}`;
            const localStorageKey = `api_cache_${cacheKey}`;

            // Only cache non-empty responses
            if (response.data && ((Array.isArray(response.data) && response.data.length > 0) || (!Array.isArray(response.data) && Object.keys(response.data).length > 0))) {
                // Update memory cache
                responseCache.set(cacheKey, {
                    data: response.data,
                    timestamp: Date.now()
                });

                // Update localStorage cache
                try {
                    localStorage.setItem(
                        localStorageKey,
                        JSON.stringify({
                            data: response.data,
                            timestamp: Date.now()
                        })
                    );
                } catch (error) {
                    console.warn('Error writing to localStorage:', error);
                }
            }
        }
        return response;
    },
    async (error) => {
        if (error.response?.status === 419) {
            // CSRF token mismatch, try to refresh
            window.location.reload();
            return Promise.reject(error);
        }

        // Handle timeout errors
        if (error.code === 'ECONNABORTED' || !error.response) {
            const config = error.config;

            // Don't retry background refreshes
            if (config.background) {
                return Promise.reject(error);
            }

            // For critical paths that timeout, we should still try to get cached data but never use fallbacks
            if (config.critical && config.method === 'get') {
                console.log('Critical path timed out:', config.url);

                // Try to get cached data
                const cacheKey = `${config.url}${JSON.stringify(config.params || {})}`;
                const localStorageKey = `api_cache_${cacheKey}`;

                // Try memory cache first
                const cachedResponse = responseCache.get(cacheKey);
                if (cachedResponse && cachedResponse.data) {
                    console.log('Using memory cache after timeout for critical path:', config.url);
                    return Promise.resolve({ data: cachedResponse.data });
                }

                // Then try localStorage
                try {
                    const localData = localStorage.getItem(localStorageKey);
                    if (localData) {
                        const { data } = JSON.parse(localData);
                        if (data) {
                            console.log('Using localStorage cache after timeout for critical path:', config.url);
                            return Promise.resolve({ data });
                        }
                    }
                } catch (storageError) {
                    console.warn('Error reading from localStorage after timeout:', storageError);
                }

                // DO NOT USE FALLBACK DATA - ALWAYS RETURN EMPTY ARRAY OR ERROR
                console.error('REMOVED FALLBACK DATA: No longer providing static fallback data');

                // Return empty arrays instead of fallbacks
                if (config.url.includes('/api/sections') || config.url.includes('/api/subjects')) {
                    console.log('Returning empty array instead of fallback data');
                    return Promise.resolve({ data: [] });
                }

                // Return the original error to be handled by the application
                return Promise.reject(error);
            }

            // Try to get cached data for non-critical paths
            if (config.method === 'get') {
                const cacheKey = `${config.url}${JSON.stringify(config.params || {})}`;
                const localStorageKey = `api_cache_${cacheKey}`;

                // Try memory cache first
                const cachedResponse = responseCache.get(cacheKey);
                if (cachedResponse && cachedResponse.data) {
                    console.log('Using memory cache after timeout for:', config.url);
                    return Promise.resolve({ data: cachedResponse.data });
                }

                // Then try localStorage
                try {
                    const localData = localStorage.getItem(localStorageKey);
                    if (localData) {
                        const { data } = JSON.parse(localData);
                        if (data) {
                            console.log('Using localStorage cache after timeout for:', config.url);
                            return Promise.resolve({ data });
                        }
                    }
                } catch (error) {
                    console.warn('Error reading from localStorage after timeout:', error);
                }
            }
        }
        return Promise.reject(error);
    }
);

// Clean up expired cache entries periodically
setInterval(() => {
    const now = Date.now();
    // Clean memory cache
    for (const [key, value] of responseCache.entries()) {
        if (now - value.timestamp > CACHE_TTL) {
            responseCache.delete(key);
        }
    }

    // Clean localStorage cache
    try {
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key.startsWith('api_cache_')) {
                const value = JSON.parse(localStorage.getItem(key));
                if (now - value.timestamp > CACHE_TTL) {
                    localStorage.removeItem(key);
                }
            }
        }
    } catch (error) {
        console.warn('Error cleaning localStorage cache:', error);
    }
}, CACHE_TTL);

export default api;

// Clear API cache on load to get fresh data
document.addEventListener('DOMContentLoaded', () => {
    console.log('Clearing API cache on page load');
    try {
        // Clean localStorage cache completely for fresh start
        for (let i = localStorage.length - 1; i >= 0; i--) {
            const key = localStorage.key(i);
            if (key && (key.startsWith('api_cache_') || key.startsWith('section_subjects_') || key.includes('teacherData') || key.includes('grades_') || key.includes('sections_') || key.includes('curriculumData') || key.includes('teacher'))) {
                console.log('Removing cached item:', key);
                localStorage.removeItem(key);
            }
        }
        // Also clear memory cache
        responseCache.clear();
        console.log('Cache cleared successfully');
    } catch (error) {
        console.warn('Error cleaning cache on load:', error);
    }
});

// Also check if we're on the curriculum page and force cache clear
if (window.location.href.includes('curriculum')) {
    console.log('On curriculum page - forcing complete cache clear');
    try {
        // Force clear all cache
        for (let i = localStorage.length - 1; i >= 0; i--) {
            const key = localStorage.key(i);
            localStorage.removeItem(key);
        }
        responseCache.clear();

        // Reload the page if it's not a fresh load
        if (document.readyState === 'complete' && !window.performance.getEntriesByType('navigation')[0].type.includes('reload')) {
            console.log('Reloading page to refresh data completely');
            setTimeout(() => window.location.reload(), 100);
        }
    } catch (error) {
        console.warn('Error forcing cache clear:', error);
    }
}
