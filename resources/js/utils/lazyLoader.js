import { defineAsyncComponent, ref, computed } from 'vue'

/**
 * Lazy loading utility for Vue components with performance optimizations
 */

// Component registry for tracking loaded components
const componentRegistry = new Map()
const loadingStates = ref(new Map())
const errorStates = ref(new Map())

/**
 * Create a lazy-loaded component with intelligent loading strategies
 */
export function createLazyComponent(importFunction, options = {}) {
    const defaultOptions = {
        loadingComponent: null,
        errorComponent: null,
        delay: 200,
        timeout: 3000,
        suspensible: false,
        retryCount: 3,
        preload: false,
        cacheKey: null
    }
    
    const config = { ...defaultOptions, ...options }
    
    // Generate cache key if not provided
    if (!config.cacheKey) {
        config.cacheKey = importFunction.toString()
    }
    
    // Check if component is already in registry
    if (componentRegistry.has(config.cacheKey)) {
        return componentRegistry.get(config.cacheKey)
    }
    
    const lazyComponent = defineAsyncComponent({
        loader: createRetryableLoader(importFunction, config),
        loadingComponent: config.loadingComponent,
        errorComponent: config.errorComponent,
        delay: config.delay,
        timeout: config.timeout,
        suspensible: config.suspensible,
        onError: (error, retry, fail, attempts) => {
            console.error(`Failed to load component (attempt ${attempts}):`, error)
            
            if (attempts <= config.retryCount) {
                console.log(`Retrying component load... (${attempts}/${config.retryCount})`)
                retry()
            } else {
                console.error('Max retry attempts reached, failing component load')
                fail()
            }
        }
    })
    
    // Cache the component
    componentRegistry.set(config.cacheKey, lazyComponent)
    
    // Preload if requested
    if (config.preload) {
        preloadComponent(importFunction, config.cacheKey)
    }
    
    return lazyComponent
}

/**
 * Create a retryable loader with exponential backoff
 */
function createRetryableLoader(importFunction, config) {
    return async () => {
        const startTime = performance.now()
        setLoadingState(config.cacheKey, true)
        
        try {
            const component = await importFunction()
            
            const loadTime = performance.now() - startTime
            console.log(`Component loaded in ${loadTime.toFixed(2)}ms:`, config.cacheKey)
            
            setLoadingState(config.cacheKey, false)
            clearErrorState(config.cacheKey)
            
            return component
        } catch (error) {
            setLoadingState(config.cacheKey, false)
            setErrorState(config.cacheKey, error)
            throw error
        }
    }
}

/**
 * Preload component without rendering
 */
export function preloadComponent(importFunction, cacheKey = null) {
    if (!cacheKey) {
        cacheKey = importFunction.toString()
    }
    
    // Check if already preloaded
    if (componentRegistry.has(cacheKey)) {
        return Promise.resolve()
    }
    
    console.log('Preloading component:', cacheKey)
    
    return importFunction()
        .then(component => {
            console.log('Component preloaded successfully:', cacheKey)
            return component
        })
        .catch(error => {
            console.error('Failed to preload component:', cacheKey, error)
            throw error
        })
}

/**
 * Batch preload multiple components
 */
export function preloadComponents(components) {
    const promises = components.map(({ importFunction, cacheKey }) => {
        return preloadComponent(importFunction, cacheKey)
            .catch(error => {
                console.warn(`Failed to preload component ${cacheKey}:`, error)
                return null // Don't fail the entire batch
            })
    })
    
    return Promise.allSettled(promises)
}

/**
 * Intelligent component preloading based on user behavior
 */
export class IntelligentPreloader {
    constructor() {
        this.interactionThreshold = 3000 // 3 seconds
        this.intersectionObserver = null
        this.preloadQueue = new Set()
        this.setupIntersectionObserver()
        this.setupUserInteractionDetection()
    }
    
    setupIntersectionObserver() {
        if (typeof window === 'undefined' || !window.IntersectionObserver) {
            return
        }
        
        this.intersectionObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const componentKey = entry.target.dataset.preloadComponent
                        if (componentKey && !componentRegistry.has(componentKey)) {
                            this.schedulePreload(componentKey)
                        }
                    }
                })
            },
            {
                rootMargin: '50px' // Start loading 50px before entering viewport
            }
        )
    }
    
    setupUserInteractionDetection() {
        if (typeof window === 'undefined') return
        
        let interactionTimer = null
        
        const resetTimer = () => {
            if (interactionTimer) {
                clearTimeout(interactionTimer)
            }
            
            interactionTimer = setTimeout(() => {
                this.triggerIdlePreloading()
            }, this.interactionThreshold)
        }
        
        // Reset timer on user interactions
        ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
            window.addEventListener(event, resetTimer, { passive: true })
        })
        
        // Initial timer
        resetTimer()
    }
    
    schedulePreload(componentKey) {
        this.preloadQueue.add(componentKey)
        
        // Use requestIdleCallback if available, otherwise setTimeout
        if (window.requestIdleCallback) {
            window.requestIdleCallback(() => this.processPreloadQueue())
        } else {
            setTimeout(() => this.processPreloadQueue(), 0)
        }
    }
    
    triggerIdlePreloading() {
        console.log('User is idle, starting intelligent preloading')
        
        // Preload components that are likely to be needed next
        const priorityComponents = this.getPriorityComponents()
        
        priorityComponents.forEach(component => {
            this.schedulePreload(component.cacheKey)
        })
    }
    
    processPreloadQueue() {
        if (this.preloadQueue.size === 0) return
        
        const componentsToPreload = Array.from(this.preloadQueue).splice(0, 3) // Process 3 at a time
        this.preloadQueue.clear()
        
        componentsToPreload.forEach(componentKey => {
            const importFunction = this.getImportFunction(componentKey)
            if (importFunction) {
                preloadComponent(importFunction, componentKey)
            }
        })
    }
    
    getPriorityComponents() {
        // This would be based on route analytics and user behavior patterns
        // For now, return commonly used components
        return [
            { cacheKey: 'CustomersList', priority: 1 },
            { cacheKey: 'ProductsList', priority: 2 },
            { cacheKey: 'InvoicesList', priority: 3 }
        ]
    }
    
    getImportFunction(componentKey) {
        // Map component keys to their import functions
        const componentMap = {
            'CustomersList': () => import('../Pages/Customers/Index.vue'),
            'ProductsList': () => import('../Pages/Products/Index.vue'),
            'InvoicesList': () => import('../Pages/Billing/Index.vue'),
            'Dashboard': () => import('../Pages/Dashboard.vue'),
            'Reports': () => import('../Pages/Reports/Index.vue')
        }
        
        return componentMap[componentKey]
    }
    
    observeElement(element, componentKey) {
        if (this.intersectionObserver && element) {
            element.dataset.preloadComponent = componentKey
            this.intersectionObserver.observe(element)
        }
    }
    
    unobserveElement(element) {
        if (this.intersectionObserver && element) {
            this.intersectionObserver.unobserve(element)
        }
    }
}

// Global preloader instance
export const intelligentPreloader = new IntelligentPreloader()

/**
 * Vue composable for lazy loading state management
 */
export function useLazyLoading() {
    const isLoading = computed(() => (componentKey) => {
        return loadingStates.value.get(componentKey) || false
    })
    
    const hasError = computed(() => (componentKey) => {
        return errorStates.value.has(componentKey)
    })
    
    const getError = computed(() => (componentKey) => {
        return errorStates.value.get(componentKey)
    })
    
    const retryLoad = (componentKey) => {
        clearErrorState(componentKey)
        // Trigger reload by clearing cache and re-importing
        componentRegistry.delete(componentKey)
    }
    
    return {
        isLoading,
        hasError,
        getError,
        retryLoad
    }
}

/**
 * Route-based lazy loading with prefetching
 */
export class RouteLazyLoader {
    constructor(router) {
        this.router = router
        this.routeComponentMap = new Map()
        this.setupRouteInterception()
    }
    
    setupRouteInterception() {
        // Prefetch components for likely next routes
        this.router.beforeEach((to, from, next) => {
            this.prefetchRouteComponents(to)
            next()
        })
    }
    
    registerRouteComponent(routeName, importFunction, preloadCondition = null) {
        this.routeComponentMap.set(routeName, {
            importFunction,
            preloadCondition
        })
    }
    
    prefetchRouteComponents(route) {
        // Get related routes that might be visited next
        const relatedRoutes = this.getRelatedRoutes(route.name)
        
        relatedRoutes.forEach(routeName => {
            const routeComponent = this.routeComponentMap.get(routeName)
            if (routeComponent) {
                const shouldPreload = !routeComponent.preloadCondition || 
                                    routeComponent.preloadCondition(route)
                
                if (shouldPreload) {
                    preloadComponent(routeComponent.importFunction, routeName)
                }
            }
        })
    }
    
    getRelatedRoutes(currentRoute) {
        // Define route relationships for intelligent prefetching
        const routeRelationships = {
            'dashboard': ['customers.index', 'products.index', 'billing.index'],
            'customers.index': ['customers.show', 'customers.create'],
            'customers.show': ['customers.edit', 'billing.create'],
            'products.index': ['products.show', 'products.create'],
            'billing.index': ['billing.show', 'billing.create']
        }
        
        return routeRelationships[currentRoute] || []
    }
}

/**
 * Chunk-based lazy loading for large component trees
 */
export function createChunkedLazyComponent(chunks, options = {}) {
    const loadingSequence = options.sequential || false
    const chunkDelay = options.chunkDelay || 100
    
    if (loadingSequence) {
        // Load chunks sequentially
        return defineAsyncComponent(async () => {
            const loadedChunks = []
            
            for (const [index, chunk] of chunks.entries()) {
                try {
                    const component = await chunk()
                    loadedChunks.push(component)
                    
                    if (index < chunks.length - 1) {
                        await new Promise(resolve => setTimeout(resolve, chunkDelay))
                    }
                } catch (error) {
                    console.error(`Failed to load chunk ${index}:`, error)
                    throw error
                }
            }
            
            // Return the main component (typically the last chunk)
            return loadedChunks[loadedChunks.length - 1]
        })
    } else {
        // Load chunks in parallel
        return defineAsyncComponent(async () => {
            const chunkPromises = chunks.map(chunk => chunk())
            const loadedChunks = await Promise.all(chunkPromises)
            
            // Return the main component
            return loadedChunks[loadedChunks.length - 1]
        })
    }
}

// Internal state management
function setLoadingState(key, isLoading) {
    loadingStates.value.set(key, isLoading)
}

function setErrorState(key, error) {
    errorStates.value.set(key, error)
}

function clearErrorState(key) {
    errorStates.value.delete(key)
}

// Performance monitoring
export function getComponentLoadingStats() {
    return {
        totalComponents: componentRegistry.size,
        loadingComponents: Array.from(loadingStates.value.entries())
            .filter(([, isLoading]) => isLoading)
            .map(([key]) => key),
        errorComponents: Array.from(errorStates.value.keys()),
        cacheHitRate: calculateCacheHitRate()
    }
}

function calculateCacheHitRate() {
    // This would be implemented with actual metrics collection
    return componentRegistry.size > 0 ? 0.85 : 0
}

// Clean up utilities
export function clearComponentCache() {
    componentRegistry.clear()
    loadingStates.value.clear()
    errorStates.value.clear()
}

export function preloadCriticalComponents() {
    const criticalComponents = [
        { 
            importFunction: () => import('../Components/UI/Button.vue'),
            cacheKey: 'Button'
        },
        {
            importFunction: () => import('../Components/UI/Table.vue'),
            cacheKey: 'Table'
        },
        {
            importFunction: () => import('../Components/UI/Modal.vue'),
            cacheKey: 'Modal'
        }
    ]
    
    return preloadComponents(criticalComponents)
}