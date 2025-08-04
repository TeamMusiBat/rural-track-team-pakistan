
import { Capacitor } from '@capacitor/core';
import { Geolocation } from '@capacitor/geolocation';

export class MobileLocationService {
    private isTracking = false;
    private watchId: string | null = null;
    private backgroundUpdateInterval: number | null = null;
    private lastUpdateTime = 0;
    private username: string | null = null;
    private fastApiUrl = 'http://54.250.198.0:8000';
    private maxRetries = 3;
    private retryCount = 0;
    
    constructor() {
        this.init();
    }
    
    private async init() {
        console.log('Initializing MobileLocationService...');
        
        if (Capacitor.isNativePlatform()) {
            console.log('Native platform detected, requesting permissions...');
            await this.requestNativePermissions();
        } else {
            console.log('Web platform detected, using web geolocation...');
            await this.requestWebPermissions();
        }
        
        this.setupBackgroundHandling();
    }
    
    private async requestNativePermissions() {
        try {
            const permissions = await Geolocation.requestPermissions();
            console.log('Geolocation permissions:', permissions);
            
            if (permissions.location === 'granted') {
                console.log('Location permission granted');
                return true;
            } else {
                console.error('Location permission denied');
                return false;
            }
        } catch (error) {
            console.error('Permission request failed:', error);
            return false;
        }
    }
    
    private async requestWebPermissions() {
        return new Promise<boolean>((resolve) => {
            if (!navigator.geolocation) {
                console.error('Geolocation not supported');
                resolve(false);
                return;
            }
            
            navigator.geolocation.getCurrentPosition(
                () => {
                    console.log('Web geolocation permission granted');
                    resolve(true);
                },
                (error) => {
                    console.error('Web geolocation permission denied:', error);
                    resolve(false);
                },
                { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
            );
        });
    }
    
    private setupBackgroundHandling() {
        // Handle page visibility changes for background tracking
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && this.isTracking) {
                console.log('Page hidden - continuing background tracking');
                this.enableBackgroundMode();
            } else if (!document.hidden && this.isTracking) {
                console.log('Page visible - resuming foreground tracking');
                this.enableForegroundMode();
            }
        });
        
        // Handle beforeunload to maintain tracking
        window.addEventListener('beforeunload', () => {
            if (this.isTracking) {
                console.log('Page unloading - scheduling background update');
                this.scheduleBackgroundUpdate();
            }
        });
        
        // Handle app state changes for Capacitor
        if (Capacitor.isNativePlatform()) {
            document.addEventListener('resume', () => {
                console.log('App resumed from background');
                if (this.isTracking) {
                    this.getCurrentLocationAndUpdate();
                }
            });
            
            document.addEventListener('pause', () => {
                console.log('App paused to background');
                if (this.isTracking) {
                    this.scheduleBackgroundUpdate();
                }
            });
        }
    }
    
    private enableBackgroundMode() {
        if (this.backgroundUpdateInterval) {
            clearInterval(this.backgroundUpdateInterval);
        }
        
        // More frequent updates in background (every 30 seconds)
        this.backgroundUpdateInterval = window.setInterval(() => {
            if (this.isTracking) {
                this.getCurrentLocationAndUpdate();
            }
        }, 30000);
        
        console.log('Background mode enabled with 30s intervals');
    }
    
    private enableForegroundMode() {
        if (this.backgroundUpdateInterval) {
            clearInterval(this.backgroundUpdateInterval);
        }
        
        // Less frequent updates in foreground (every 60 seconds)
        this.backgroundUpdateInterval = window.setInterval(() => {
            if (this.isTracking) {
                this.getCurrentLocationAndUpdate();
            }
        }, 60000);
        
        console.log('Foreground mode enabled with 60s intervals');
    }
    
    private scheduleBackgroundUpdate() {
        setTimeout(() => {
            if (this.isTracking) {
                this.getCurrentLocationAndUpdate();
            }
        }, 5000);
    }
    
    async startTracking(username: string) {
        this.username = username;
        this.isTracking = true;
        this.retryCount = 0;
        
        console.log(`Starting location tracking for user: ${username}`);
        
        try {
            if (Capacitor.isNativePlatform()) {
                await this.startNativeTracking();
            } else {
                this.startWebTracking();
            }
            
            // Start background updates immediately
            this.enableForegroundMode();
            
            // Initial location update
            await this.getCurrentLocationAndUpdate();
            
        } catch (error) {
            console.error('Failed to start tracking:', error);
            this.showError('Failed to start location tracking');
        }
    }
    
    private async startNativeTracking() {
        try {
            console.log('Starting native location tracking...');
            
            // Use Capacitor's watchPosition with correct signature
            this.watchId = await Geolocation.watchPosition(
                {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 60000
                },
                (position, err) => {
                    if (err) {
                        console.error('Native location error:', err);
                        this.handleLocationError(err);
                        return;
                    }
                    
                    if (position && position.coords) {
                        console.log('Native location update:', position.coords);
                        this.updateLocationToServer(
                            position.coords.latitude,
                            position.coords.longitude
                        );
                    }
                }
            );
            
            console.log('Native location tracking started with watchId:', this.watchId);
        } catch (error) {
            console.error('Native tracking failed:', error);
            throw error;
        }
    }
    
    private startWebTracking() {
        if (!navigator.geolocation) {
            throw new Error('Geolocation not supported');
        }
        
        console.log('Starting web location tracking...');
        
        this.watchId = navigator.geolocation.watchPosition(
            (position) => {
                console.log('Web location update:', position.coords);
                this.updateLocationToServer(
                    position.coords.latitude,
                    position.coords.longitude
                );
            },
            (error) => {
                console.error('Web location error:', error);
                this.handleLocationError(error);
            },
            {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 60000
            }
        ) as any;
        
        console.log('Web location tracking started with watchId:', this.watchId);
    }
    
    private handleLocationError(error: any) {
        console.error('Location error:', error);
        
        if (this.retryCount < this.maxRetries) {
            this.retryCount++;
            console.log(`Retrying location request (${this.retryCount}/${this.maxRetries})...`);
            
            setTimeout(() => {
                this.getCurrentLocationAndUpdate();
            }, 5000);
        } else {
            this.showError('Location tracking failed after multiple attempts');
        }
    }
    
    private async getCurrentLocationAndUpdate() {
        try {
            if (Capacitor.isNativePlatform()) {
                const position = await Geolocation.getCurrentPosition({
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 60000
                });
                
                if (position && position.coords) {
                    console.log('Native current location:', position.coords);
                    await this.updateLocationToServer(
                        position.coords.latitude,
                        position.coords.longitude
                    );
                }
            } else {
                navigator.geolocation.getCurrentPosition(
                    async (position) => {
                        console.log('Web current location:', position.coords);
                        await this.updateLocationToServer(
                            position.coords.latitude,
                            position.coords.longitude
                        );
                    },
                    (error) => {
                        console.error('Current location error:', error);
                        this.handleLocationError(error);
                    },
                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 }
                );
            }
        } catch (error) {
            console.error('Get current location failed:', error);
            this.handleLocationError(error);
        }
    }
    
    private async updateLocationToServer(latitude: number, longitude: number) {
        const now = Date.now();
        
        // Rate limiting - only update once per minute
        if (now - this.lastUpdateTime < 60000) {
            console.log('Rate limiting - skipping update');
            return;
        }
        
        if (!this.username) {
            console.error('No username for location update');
            return;
        }
        
        try {
            console.log(`Updating location for ${this.username}: ${latitude}, ${longitude}`);
            
            const response = await fetch(
                `${this.fastApiUrl}/update_location/${this.username}/${longitude}_${latitude}`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Cache-Control': 'no-cache',
                        'X-Bypass-Service-Worker': 'true'
                    },
                    body: JSON.stringify({
                        timestamp: new Date().toISOString(),
                        accuracy: 'high'
                    })
                }
            );
            
            if (response.ok) {
                const data = await response.json();
                this.lastUpdateTime = now;
                this.retryCount = 0; // Reset retry count on success
                console.log('Location updated successfully:', data);
                this.showLocationUpdateFeedback();
            } else {
                throw new Error(`Server responded with status: ${response.status}`);
            }
        } catch (error) {
            console.error('Location update failed:', error);
            this.handleLocationError(error);
        }
    }
    
    private showLocationUpdateFeedback() {
        // Brief visual feedback
        const indicator = document.getElementById('location-status') || this.createLocationIndicator();
        indicator.style.backgroundColor = '#4CAF50';
        indicator.textContent = '📍';
        
        setTimeout(() => {
            indicator.style.backgroundColor = '#666';
            indicator.textContent = '📍';
        }, 2000);
    }
    
    private createLocationIndicator() {
        const indicator = document.createElement('div');
        indicator.id = 'location-status';
        indicator.style.cssText = `
            position: fixed;
            top: 10px;
            right: 10px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #666;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            z-index: 1000;
            transition: all 0.3s ease;
        `;
        indicator.textContent = '📍';
        document.body.appendChild(indicator);
        return indicator;
    }
    
    private showError(message: string) {
        console.error('MobileLocationService Error:', message);
        
        // Show user-friendly error
        const errorDiv = document.createElement('div');
        errorDiv.style.cssText = `
            position: fixed;
            top: 50px;
            left: 50%;
            transform: translateX(-50%);
            background: #f44336;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            z-index: 10000;
            font-size: 14px;
        `;
        errorDiv.textContent = message;
        document.body.appendChild(errorDiv);
        
        setTimeout(() => {
            errorDiv.remove();
        }, 5000);
    }
    
    stopTracking() {
        console.log('Stopping location tracking...');
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
        
        // Remove location indicator
        const indicator = document.getElementById('location-status');
        if (indicator) {
            indicator.remove();
        }
        
        console.log('Location tracking stopped');
    }
    
    getStatus() {
        return {
            isTracking: this.isTracking,
            isNative: Capacitor.isNativePlatform(),
            platform: Capacitor.getPlatform(),
            username: this.username,
            lastUpdate: this.lastUpdateTime ? new Date(this.lastUpdateTime).toLocaleTimeString() : 'Never',
            retryCount: this.retryCount
        };
    }
}

// Create and export global instance
export const mobileLocationService = new MobileLocationService();

// Make it globally available for dashboard scripts
(window as any).mobileLocationService = mobileLocationService;
