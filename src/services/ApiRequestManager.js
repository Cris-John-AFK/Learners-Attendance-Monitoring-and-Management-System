/**
 * Global API Request Manager to prevent rate limiting
 * Implements request queuing and throttling
 */
class ApiRequestManager {
    constructor() {
        this.requestQueue = [];
        this.isProcessing = false;
        this.lastRequestTime = 0;
        this.minInterval = 1000; // Minimum 1 second between requests
        this.maxConcurrent = 3; // Maximum 3 concurrent requests
        this.activeRequests = 0;
    }

    /**
     * Add a request to the queue
     * @param {Function} requestFn - Function that returns a Promise
     * @param {string} priority - 'high', 'normal', 'low'
     * @returns {Promise}
     */
    async queueRequest(requestFn, priority = 'normal') {
        return new Promise((resolve, reject) => {
            const request = {
                fn: requestFn,
                priority,
                resolve,
                reject,
                timestamp: Date.now()
            };

            // Insert based on priority
            if (priority === 'high') {
                this.requestQueue.unshift(request);
            } else {
                this.requestQueue.push(request);
            }

            this.processQueue();
        });
    }

    async processQueue() {
        if (this.isProcessing || this.requestQueue.length === 0 || this.activeRequests >= this.maxConcurrent) {
            return;
        }

        this.isProcessing = true;

        while (this.requestQueue.length > 0 && this.activeRequests < this.maxConcurrent) {
            const now = Date.now();
            const timeSinceLastRequest = now - this.lastRequestTime;

            // Throttle requests to prevent rate limiting
            if (timeSinceLastRequest < this.minInterval) {
                await this.delay(this.minInterval - timeSinceLastRequest);
            }

            const request = this.requestQueue.shift();
            this.activeRequests++;
            this.lastRequestTime = Date.now();

            // Execute request
            try {
                const result = await request.fn();
                request.resolve(result);
            } catch (error) {
                // Handle rate limiting specifically
                if (error.response?.status === 429) {
                    console.warn('Rate limited, re-queuing request with delay');
                    // Re-queue with lower priority after delay
                    setTimeout(() => {
                        this.requestQueue.unshift({
                            ...request,
                            priority: 'low'
                        });
                        this.processQueue();
                    }, 5000);
                } else {
                    request.reject(error);
                }
            } finally {
                this.activeRequests--;
            }
        }

        this.isProcessing = false;

        // Continue processing if there are more requests
        if (this.requestQueue.length > 0) {
            setTimeout(() => this.processQueue(), this.minInterval);
        }
    }

    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Clear all pending requests
     */
    clearQueue() {
        this.requestQueue.forEach(request => {
            request.reject(new Error('Request cancelled'));
        });
        this.requestQueue = [];
    }

    /**
     * Get queue status
     */
    getStatus() {
        return {
            queueLength: this.requestQueue.length,
            activeRequests: this.activeRequests,
            isProcessing: this.isProcessing
        };
    }
}

// Global instance
export const apiRequestManager = new ApiRequestManager();

// Helper function for easy use
export const queueApiRequest = (requestFn, priority = 'normal') => {
    return apiRequestManager.queueRequest(requestFn, priority);
};

export default ApiRequestManager;
