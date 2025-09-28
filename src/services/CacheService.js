class CacheService {
    constructor() {
        this.cache = new Map();
        this.cacheExpiry = new Map();
        this.defaultTTL = 5 * 60 * 1000; // 5 minutes default TTL
    }

    /**
     * Generate cache key from parameters
     */
    generateKey(prefix, params = {}) {
        const sortedParams = Object.keys(params)
            .sort()
            .map(key => `${key}:${params[key]}`)
            .join('|');
        return `${prefix}:${sortedParams}`;
    }

    /**
     * Set cache with TTL
     */
    set(key, data, ttl = this.defaultTTL) {
        this.cache.set(key, data);
        this.cacheExpiry.set(key, Date.now() + ttl);
        
        // Auto cleanup expired entries
        setTimeout(() => this.delete(key), ttl);
        
        console.log(`📦 Cached: ${key} (TTL: ${ttl}ms)`);
    }

    /**
     * Get from cache if not expired
     */
    get(key) {
        const expiry = this.cacheExpiry.get(key);
        
        if (!expiry || Date.now() > expiry) {
            this.delete(key);
            return null;
        }
        
        const data = this.cache.get(key);
        if (data) {
            console.log(`🎯 Cache hit: ${key}`);
        }
        return data;
    }

    /**
     * Delete cache entry
     */
    delete(key) {
        this.cache.delete(key);
        this.cacheExpiry.delete(key);
    }

    /**
     * Clear all cache
     */
    clear() {
        this.cache.clear();
        this.cacheExpiry.clear();
        console.log('🗑️ Cache cleared');
    }

    /**
     * Get cache stats
     */
    getStats() {
        return {
            size: this.cache.size,
            keys: Array.from(this.cache.keys())
        };
    }

    /**
     * Cached API call wrapper
     */
    async cachedCall(key, apiCall, ttl = this.defaultTTL) {
        // Try cache first
        const cached = this.get(key);
        if (cached) {
            return cached;
        }

        // Make API call
        console.log(`🌐 API call: ${key}`);
        const result = await apiCall();
        
        // Cache result
        this.set(key, result, ttl);
        
        return result;
    }
}

export default new CacheService();
