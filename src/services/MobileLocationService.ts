import { Capacitor } from '@capacitor/core';
import { Geolocation } from '@capacitor/geolocation';

// Define BackgroundMode interface since the plugin might not have full types
declare global {
  interface Window {
    BackgroundMode?: {
      enable(): Promise<void>;
      disable(): Promise<void>;
      setEnabled(enabled: boolean): Promise<void>;
      isEnabled(): Promise<boolean>;
    };
  }
}

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
            await this.enableBackgroundMode();
        }
    }
    
    private async requestPermissions() {
        try {
            const permissions = await Geolocation.requestPermissions();
            console.log('Location permissions:', permissions);
        } catch (error) {
            console.error('Permission request failed:', error);
        }
    }
    
    private async enableBackgroundMode() {
        try {
            if (Capacitor.isNativePlatform() && window.BackgroundMode) {
                await window.BackgroundMode.enable();
                console.log('Background mode enabled');
            }
        } catch (error) {
            console.error('Background mode setup failed:', error);
        }
    }
    
    async startTracking(username: string) {
        this.username = username;
        this.isTracking = true;
        
        if (Capacitor.isNativePlatform()) {
            await this.startNativeTracking();
        } else {
            this.startWebTracking();
        }
    }
    
    private async startNativeTracking() {
        try {
            // High accuracy tracking for native platforms
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
            
            // Additional background updates every 60 seconds for native
            this.backgroundUpdateInterval = window.setInterval(async () => {
                if (this.isTracking) {
                    try {
                        const position = await Geolocation.getCurrentPosition({
                            enableHighAccuracy: true,
                            timeout: 10000,
                            maximumAge: 30000
                        });
                        
                        this.updateLocationToServer(
                            position.coords.latitude,
                            position.coords.longitude
                        );
                    } catch (error) {
                        console.error('Background location update failed:', error);
                    }
                }
            }, 60000);
            
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
            
            // Web background updates with Page Visibility API
            this.backgroundUpdateInterval = window.setInterval(() => {
                if (this.isTracking) {
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            this.updateLocationToServer(
                                position.coords.latitude,
                                position.coords.longitude
                            );
                        },
                        (error) => console.error('Web location error:', error),
                        { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
                    );
                }
            }, 60000);
            
            // Handle page visibility changes to ensure background updates
            document.addEventListener('visibilitychange', () => {
                if (document.hidden && this.isTracking) {
                    // Page is hidden, but keep tracking
                    console.log('Page hidden - continuing background location tracking');
                } else if (!document.hidden && this.isTracking) {
                    console.log('Page visible - location tracking active');
                }
            });
            
            console.log('Web location tracking started');
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
