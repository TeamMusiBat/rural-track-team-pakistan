
import { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'app.lovable.cf8949d8f7d14a3f9e99a4c564ace2d1',
  appName: 'rural-track-team',
  webDir: 'dist',
  server: {
    url: 'https://cf8949d8-f7d1-4a3f-9e99-a4c564ace2d1.lovableproject.com?forceHideBadge=true',
    cleartext: true
  },
  plugins: {
    Geolocation: {
      enableBackground: true,
      backgroundPermissionRationale: "This app needs background location access for attendance tracking."
    },
    BackgroundMode: {
      title: "SmartORT Location Tracking",
      text: "Tracking your location for attendance.",
      icon: "icon",
      color: "#4CAF50",
      resume: true,
      silent: false,
      hidden: false
    }
  }
};

export default config;
