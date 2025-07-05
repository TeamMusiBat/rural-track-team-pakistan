
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { InfoIcon, Smartphone, Laptop, ExternalLink, CheckCircle, MapPin, Clock, UserRound, UsersRound, ShieldAlert, AlertTriangle } from "lucide-react";
import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';

const Features = ({ isLoading }: { isLoading: boolean }) => {
  const features = [
    { icon: <MapPin className="h-5 w-5 text-indigo-600" />, title: "Real-time tracking", description: "Live employee location tracking with permissions and distance monitoring" },
    { icon: <Clock className="h-5 w-5 text-indigo-600" />, title: "Smart attendance", description: "Automated check-in/out with Pakistan time zone and duration tracking" },
    { icon: <Smartphone className="h-5 w-5 text-indigo-600" />, title: "Cross-platform", description: "Works on iOS, Android, Windows, Mac and all modern browsers" },
    { icon: <Laptop className="h-5 w-5 text-indigo-600" />, title: "Offline support", description: "Fully functional offline with smart sync when connection restores" },
    { icon: <UserRound className="h-5 w-5 text-indigo-600" />, title: "Role management", description: "Powerful user roles with specialized permissions for each role" },
    { icon: <UsersRound className="h-5 w-5 text-indigo-600" />, title: "Admin dashboard", description: "Comprehensive admin controls for monitoring and management" }
  ];
  
  return (
    <div className={`grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-6 ${isLoading ? 'opacity-0' : 'opacity-100 transition-opacity duration-500'}`}>
      {features.map((feature, index) => (
        <motion.div
          key={index}
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: isLoading ? 0 : 1, y: isLoading ? 20 : 0 }}
          transition={{ duration: 0.5, delay: 0.2 + (index * 0.1) }}
        >
          <div className="flex items-start space-x-3 p-4 rounded-xl bg-white shadow-sm hover:shadow-md transition-shadow border border-slate-100">
            <div className="flex-shrink-0 p-2 bg-indigo-50 rounded-lg mt-1">
              {feature.icon}
            </div>
            <div>
              <h3 className="font-medium text-slate-800">{feature.title}</h3>
              <p className="text-xs text-slate-500 mt-1">{feature.description}</p>
            </div>
          </div>
        </motion.div>
      ))}
    </div>
  );
};

// New component to check and handle location permissions
const LocationPermissionCheck = () => {
  const [permissionStatus, setPermissionStatus] = useState<string>('checking');
  
  useEffect(() => {
    // Check if geolocation is supported
    if (!navigator.geolocation) {
      setPermissionStatus('unsupported');
      return;
    }
    
    // Check permission status if the Permissions API is available
    if (navigator.permissions && navigator.permissions.query) {
      navigator.permissions.query({ name: 'geolocation' })
        .then(status => {
          setPermissionStatus(status.state);
          
          // Listen for changes to permission
          status.onchange = () => {
            setPermissionStatus(status.state);
          };
        })
        .catch(() => {
          // If permissions query fails, try to get location directly
          checkByGettingPosition();
        });
    } else {
      // Fallback for browsers without Permissions API
      checkByGettingPosition();
    }
  }, []);
  
  // Fallback method to check permission by attempting to get position
  const checkByGettingPosition = () => {
    navigator.geolocation.getCurrentPosition(
      () => setPermissionStatus('granted'),
      (error) => {
        if (error.code === error.PERMISSION_DENIED) {
          setPermissionStatus('denied');
        } else {
          setPermissionStatus('prompt');
        }
      }
    );
  };
  
  // Request permission explicitly
  const requestPermission = () => {
    navigator.geolocation.getCurrentPosition(
      () => setPermissionStatus('granted'),
      (error) => {
        if (error.code === error.PERMISSION_DENIED) {
          setPermissionStatus('denied');
          alert('Location access is denied. Please enable location in your browser settings to use all features.');
        }
      },
      { enableHighAccuracy: true }
    );
  };
  
  if (permissionStatus === 'checking' || permissionStatus === 'prompt') {
    return (
      <motion.div
        initial={{ opacity: 0, y: -10 }}
        animate={{ opacity: 1, y: 0 }}
        className="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-4"
      >
        <div className="flex items-start">
          <AlertTriangle className="h-5 w-5 text-amber-600 mt-0.5 mr-2 flex-shrink-0" />
          <div>
            <h3 className="font-medium text-amber-800">Location permission needed</h3>
            <p className="text-sm text-amber-700 mt-1">
              SmartOutreach requires location access to track attendance. Without this permission, the app cannot function properly.
            </p>
            <Button
              className="mt-3 bg-amber-600 hover:bg-amber-700 text-white"
              size="sm"
              onClick={requestPermission}
            >
              Enable Location Access
            </Button>
          </div>
        </div>
      </motion.div>
    );
  }
  
  if (permissionStatus === 'denied') {
    return (
      <motion.div
        initial={{ opacity: 0, y: -10 }}
        animate={{ opacity: 1, y: 0 }}
        className="bg-red-50 border border-red-200 rounded-lg p-4 mb-4"
      >
        <div className="flex items-start">
          <AlertTriangle className="h-5 w-5 text-red-600 mt-0.5 mr-2 flex-shrink-0" />
          <div>
            <h3 className="font-medium text-red-800">Location access denied</h3>
            <p className="text-sm text-red-700 mt-1">
              You've denied location access. This app requires location permission to function properly. Please update your browser settings to allow location access.
            </p>
            <Button
              className="mt-3 bg-red-600 hover:bg-red-700 text-white"
              size="sm"
              onClick={() => window.open('https://support.google.com/chrome/answer/142065', '_blank')}
            >
              How to Enable Location
            </Button>
          </div>
        </div>
      </motion.div>
    );
  }
  
  if (permissionStatus === 'unsupported') {
    return (
      <motion.div
        initial={{ opacity: 0, y: -10 }}
        animate={{ opacity: 1, y: 0 }}
        className="bg-red-50 border border-red-200 rounded-lg p-4 mb-4"
      >
        <div className="flex items-start">
          <AlertTriangle className="h-5 w-5 text-red-600 mt-0.5 mr-2 flex-shrink-0" />
          <div>
            <h3 className="font-medium text-red-800">Location not supported</h3>
            <p className="text-sm text-red-700 mt-1">
              Your browser doesn't support geolocation. Please try a different browser to use all features of this app.
            </p>
          </div>
        </div>
      </motion.div>
    );
  }
  
  return null; // Don't show anything if permission is granted
};

const Index = () => {
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    // Simulate loading to show the interface
    const timer = setTimeout(() => {
      setIsLoading(false);
    }, 1500);

    return () => clearTimeout(timer);
  }, []);

  return (
    <div className="min-h-screen bg-gradient-to-b from-indigo-50 to-white">
      <div className="container max-w-6xl mx-auto px-4 py-8">
        <motion.div
          initial={{ opacity: 0, y: -20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5 }}
          className="text-center mb-6"
        >
          <h1 className="text-4xl md:text-5xl font-bold text-indigo-700 mb-3">SmartOutreach</h1>
          <p className="text-lg text-slate-600 max-w-2xl mx-auto">
            Advanced employee tracking & attendance management system designed for businesses of all sizes.
          </p>
        </motion.div>
        
        {/* Location permission check banner */}
        <LocationPermissionCheck />

        <div className="grid md:grid-cols-5 gap-8 items-start">
          <div className="md:col-span-3">
            <motion.div
              initial={{ opacity: 0, scale: 0.95 }}
              animate={{ opacity: isLoading ? 0 : 1, scale: isLoading ? 0.95 : 1 }}
              transition={{ duration: 0.5, delay: 0.2 }}
              className="bg-white rounded-2xl shadow-xl overflow-hidden border border-indigo-100"
            >
              <div className="relative">
                <div className="absolute inset-0 bg-gradient-to-r from-indigo-600 to-indigo-800 opacity-90"></div>
                <div className="relative p-8 text-white">
                  <h2 className="text-2xl font-bold mb-4">Employee Management Solution</h2>
                  <p className="opacity-90 mb-6">
                    SmartOutreach helps you monitor employee attendance, track locations, and manage your team effectively through a modern, intuitive interface.
                  </p>
                  <div className="flex flex-wrap gap-3">
                    <div className="flex items-center px-3 py-1 bg-white bg-opacity-20 rounded-full text-sm">
                      <CheckCircle className="h-4 w-4 mr-1" /> Real-time tracking
                    </div>
                    <div className="flex items-center px-3 py-1 bg-white bg-opacity-20 rounded-full text-sm">
                      <CheckCircle className="h-4 w-4 mr-1" /> Pakistan timezone
                    </div>
                    <div className="flex items-center px-3 py-1 bg-white bg-opacity-20 rounded-full text-sm">
                      <CheckCircle className="h-4 w-4 mr-1" /> Cross-platform
                    </div>
                  </div>
                </div>
              </div>
              
              <div className="p-6">
                <h3 className="font-medium text-slate-800 text-lg mb-4">Key Features</h3>
                <Features isLoading={isLoading} />
              </div>
            </motion.div>
          </div>
          
          <motion.div
            initial={{ opacity: 0, x: 20 }}
            animate={{ opacity: isLoading ? 0 : 1, x: isLoading ? 20 : 0 }}
            transition={{ duration: 0.5, delay: 0.3 }}
            className="md:col-span-2"
          >
            <Card className="shadow-lg border-indigo-100">
              <CardHeader>
                <CardTitle className="text-2xl text-indigo-700">Get Started</CardTitle>
                <CardDescription>
                  Install SmartOutreach on your web server
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                {isLoading ? (
                  <div className="flex flex-col items-center justify-center py-8">
                    <div className="w-16 h-16 border-t-4 border-indigo-600 border-solid rounded-full animate-spin"></div>
                    <p className="text-slate-600 mt-4">Preparing application...</p>
                  </div>
                ) : (
                  <>
                    <Alert className="bg-amber-50 border-amber-200">
                      <InfoIcon className="h-4 w-4 text-amber-600" />
                      <AlertTitle className="text-amber-800">Installation Required</AlertTitle>
                      <AlertDescription className="text-amber-700 text-sm">
                        This is a PHP application that needs to be installed on your web server. You cannot run it directly from this preview.
                      </AlertDescription>
                    </Alert>
                    
                    <div className="mt-4 space-y-3">
                      <h3 className="font-medium text-slate-700 text-sm">Installation Steps:</h3>
                      <ol className="text-xs text-slate-600 space-y-2 list-decimal list-inside">
                        <li>Download all files including PHP, database, and assets</li>
                        <li>Upload to your PHP-enabled web server</li>
                        <li>Navigate to <code className="bg-slate-100 px-1 rounded">install.php</code> to set up the database</li>
                        <li>Login with credentials provided during installation</li>
                        <li>Enable location permission when prompted</li>
                      </ol>
                    </div>
                    
                    <Alert className="bg-blue-50 border-blue-200 mt-3">
                      <ShieldAlert className="h-4 w-4 text-blue-600" />
                      <AlertTitle className="text-blue-800">Security Note</AlertTitle>
                      <AlertDescription className="text-blue-700 text-sm">
                        For security reasons, credentials are not shown publicly. They will be securely provided during installation.
                      </AlertDescription>
                    </Alert>
                  </>
                )}
              </CardContent>
              <CardFooter className="flex flex-col gap-4">
                <a 
                  href="https://github.com/your-repository/smart-outreach-tracker" 
                  target="_blank" 
                  rel="noopener noreferrer"
                  className="w-full"
                >
                  <Button 
                    className="w-full bg-indigo-600 hover:bg-indigo-700" 
                    disabled={isLoading}
                  >
                    {isLoading ? "Preparing..." : "Download Application Files"}
                    <ExternalLink className="ml-2 h-4 w-4" />
                  </Button>
                </a>
                <div className="text-center text-xs text-slate-500 space-y-1 pt-2">
                  <p>Cross-platform support: iOS, Android, Windows, Mac, and browsers</p>
                  <p>For technical support, contact administrator</p>
                </div>
              </CardFooter>
            </Card>
          </motion.div>
        </div>
        
        <motion.footer
          initial={{ opacity: 0 }}
          animate={{ opacity: isLoading ? 0 : 1 }}
          transition={{ duration: 0.5, delay: 0.5 }}
          className="mt-16 text-center text-sm text-slate-500"
        >
          <p>© {new Date().getFullYear()} SmartOutreach. All rights reserved.</p>
          <p className="mt-1">Built with modern technologies for cross-platform compatibility.</p>
        </motion.footer>
      </div>
    </div>
  );
};

export default Index;
