import { Capacitor } from '@capacitor/core';
import { Geolocation } from '@capacitor/geolocation';

// Use built-in background approaches instead of external plugins
export class MobileLocationService {
    private isTracking = false;
    private watchId: string | null = null;
    private backgroundUpdateInterval: number | null = null;
    private lastUpdateTime = 0;
    private username: string | null = null;
    private fastApiUrl = 'http://54.250.198.0:8000';
    
    constructor() {
        this.init();
    }
    
    private async init() {
        if (Capacitor.isNativePlatform()) {
            // Request permissions for native platforms
            await this.requestPermissions();
        }
        
        // Set up page visibility handling for background updates
        this.setupBackgroundHandling();
    }
    
    private async requestPermissions() {
        try {
            const permissions = await Geolocation.requestPermissions();
            console.log('Location permissions:', permissions);
        } catch (error) {
            console.error('Permission request failed:', error);
        }
    }
    
    private setupBackgroundHandling() {
        // Handle page visibility changes to maintain tracking
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && this.isTracking) {
                console.log('Page hidden - maintaining background location tracking');
                this.maintainBackgroundTracking();
            } else if (!document.hidden && this.isTracking) {
                console.log('Page visible - resuming active location tracking');
                this.resumeActiveTracking();
            }
        });
        
        // Handle page beforeunload to keep tracking alive
        window.addEventListener('beforeunload', () => {
            if (this.isTracking) {
                // Keep tracking active even when page unloads
                this.scheduleBackgroundUpdate();
            }
        });
        
        // Use Web Workers for background processing if available
        if ('serviceWorker' in navigator) {
            this.setupServiceWorkerTracking();
        }
    }
    
    private maintainBackgroundTracking() {
        // Use high-frequency updates when in background
        if (this.backgroundUpdateInterval) {
            clearInterval(this.backgroundUpdateInterval);
        }
        
        this.backgroundUpdateInterval = window.setInterval(() => {
            if (this.isTracking) {
                this.getCurrentLocationAndUpdate();
            }
        }, 30000); // Update every 30 seconds in background
    }
    
    private resumeActiveTracking() {
        // Resume normal tracking frequency when active
        if (this.backgroundUpdateInterval) {
            clearInterval(this.backgroundUpdateInterval);
        }
        
        this.backgroundUpdateInterval = window.setInterval(() => {
            if (this.isTracking) {
                this.getCurrentLocationAndUpdate();
            }
        }, 60000); // Update every 60 seconds when active
    }
    
    private async setupServiceWorkerTracking() {
        try {
            const registration = await navigator.serviceWorker.register('/sw.js');
            console.log('Service Worker registered for background tracking');
            
            // Send tracking commands to service worker
            if (registration.active) {
                registration.active.postMessage({
                    command: 'SETUP_LOCATION_TRACKING',
                    fastApiUrl: this.fastApiUrl
                });
            }
        } catch (error) {
            console.log('Service Worker not available, using fallback methods');
        }
    }
    
    private scheduleBackgroundUpdate() {
        // Use setTimeout to schedule immediate background update
        setTimeout(() => {
            if (this.isTracking) {
                this.getCurrentLocationAndUpdate();
            }
        }, 1000);
    }
    
    async startTracking(username: string) {
        this.username = username;
        this.isTracking = true;
        
        console.log(`Starting location tracking for user: ${username}`);
        
        if (Capacitor.isNativePlatform()) {
            await this.startNativeTracking();
        } else {
            this.startWebTracking();
        }
        
        // Start background updates
        this.resumeActiveTracking();
    }
    
    private async startNativeTracking() {
        try {
            // High accuracy tracking for native platforms with background support
            this.watchId = await Geolocation.watchPosition(
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 30000
                },
                (position) => {
                    if (position) {
                        this.updateLocationToServer(
                            position.coords.latitude,
                            position.coords.longitude
                        );
                    }
                }
            );
            
            console.log('Native location tracking started');
        } catch (error) {
            console.error('Native tracking failed:', error);
            this.startWebTracking(); // Fallback to web
        }
    }
    
    private startWebTracking() {
        if (navigator.geolocation) {
            this.watchId = navigator.geolocation.watchPosition(
                (position) => {
                    this.updateLocationToServer(
                        position.coords.latitude,
                        position.coords.longitude
                    );
                },
                (error) => {
                    console.error('Web geolocation error:', error);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 30000
                }
            ) as any;
            
            console.log('Web location tracking started');
        }
    }
    
    private async getCurrentLocationAndUpdate() {
        try {
            if (Capacitor.isNativePlatform()) {
                const position = await Geolocation.getCurrentPosition({
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 30000
                });
                
                this.updateLocationToServer(
                    position.coords.latitude,
                    position.coords.longitude
                );
            } else {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        this.updateLocationToServer(
                            position.coords.latitude,
                            position.coords.longitude
                        );
                    },
                    (error) => console.error('Location update error:', error),
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
                );
            }
        } catch (error) {
            console.error('Background location update failed:', error);
        }
    }
    
    private async updateLocationToServer(latitude: number, longitude: number) {
        const now = Date.now();
        
        // Rate limiting - only update once per minute
        if (now - this.lastUpdateTime < 60000) {
            return;
        }
        
        if (!this.username) {
            console.error('No username for location update');
            return;
        }
        
        try {
            const response = await fetch(
                `${this.fastApiUrl}/update_location/${this.username}/${longitude}_${latitude}`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Cache-Control': 'no-cache',
                        'X-Bypass-Service-Worker': 'true'
                    },
                    body: JSON.stringify({})
                }
            );
            
            if (response.ok) {
                const data = await response.json();
                this.lastUpdateTime = now;
                console.log('Location updated successfully:', data.message);
                
                // Visual feedback
                this.showLocationUpdateFeedback();
            }
        } catch (error) {
            console.error('Location update failed:', error);
        }
    }
    
    private showLocationUpdateFeedback() {
        // Show brief visual feedback
        const body = document.body;
        if (body) {
            body.style.transition = 'background-color 0.3s ease';
            body.style.backgroundColor = '#E8F5E8';
            
            setTimeout(() => {
                body.style.backgroundColor = '';
            }, 1000);
        }
    }
    
    stopTracking() {
        this.isTracking = false;
        
        if (this.watchId) {
            if (Capacitor.isNativePlatform()) {
                Geolocation.clearWatch({ id: this.watchId });
            } else {
                navigator.geolocation.clearWatch(this.watchId as any);
            }
            this.watchId = null;
        }
        
        if (this.backgroundUpdateInterval) {
            clearInterval(this.backgroundUpdateInterval);
            this.backgroundUpdateInterval = null;
        }
        
        console.log('Location tracking stopped');
    }
    
    getStatus() {
        return {
            isTracking: this.isTracking,
            isNative: Capacitor.isNativePlatform(),
            platform: Capacitor.getPlatform(),
            lastUpdate: this.lastUpdateTime ? new Date(this.lastUpdateTime).toLocaleTimeString() : 'Never'
        };
    }
}

// Create global instance
export const mobileLocationService = new MobileLocationService();
