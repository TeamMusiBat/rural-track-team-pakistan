
<?php
// Admin tracking page improvements
require_once 'config.php';

// Ensure user is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: index.php');
    exit;
}

// Function to get improved tracking data
function getImprovedTrackingData() {
    global $pdo;
    
    $loggedInUserRole = $_SESSION['role'];
    $locations = [];
    
    try {
        // Get FastAPI base URL from settings
        $fastapi_base_url = getSettings('fastapi_base_url', 'http://54.250.198.0:8000');
        
        // Call FastAPI to get all locations
        $api_url = $fastapi_base_url . "/fetch_all_locations";
        $response = makeApiRequest($api_url, 'GET');

        if ($response !== false && is_array($response)) {
            foreach ($response as $location) {
                if (isset($location['username'])) {
                    // Get user details from database
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                    $stmt->execute([$location['username']]);
                    $user = $stmt->fetch();
                    
                    if ($user) {
                        // Check if user should be visible to current admin
                        if ($loggedInUserRole === 'master' && ($user['role'] === 'developer' || $user['role'] === 'master')) {
                            continue;
                        }
                        
                        // Check if user is checked in
                        $stmt = $pdo->prepare("SELECT id, check_in FROM attendance WHERE user_id = ? AND check_out IS NULL LIMIT 1");
                        $stmt->execute([$user['id']]);
                        $checkin = $stmt->fetch();
                        $isCheckedIn = !empty($checkin);
                        
                        // Calculate work duration
                        $workDuration = '';
                        if ($isCheckedIn && $checkin) {
                            $checkinTime = new DateTime($checkin['check_in'], new DateTimeZone('Asia/Karachi'));
                            $now = new DateTime('now', new DateTimeZone('Asia/Karachi'));
                            $totalSeconds = $now->getTimestamp() - $checkinTime->getTimestamp();
                            $hours = floor($totalSeconds / 3600);
                            $minutes = floor(($totalSeconds % 3600) / 60);
                            $workDuration = sprintf('%d:%02d', $hours, $minutes);
                        }
                        
                        // Only include checked in users (unless developer)
                        if ($isCheckedIn || ($loggedInUserRole === 'developer')) {
                            $locations[] = [
                                'user_id' => $user['id'],
                                'username' => $user['username'],
                                'full_name' => $user['full_name'],
                                'role' => $user['role'],
                                'user_role' => $user['user_role'],
                                'latitude' => $location['latitude'],
                                'longitude' => $location['longitude'],
                                'address' => $location['address'] ?? 'Unknown location',
                                'is_checked_in' => $isCheckedIn,
                                'work_duration' => $workDuration,
                                'timestamp' => getPakistaniTime('Y-m-d H:i:s'),
                                'formatted_time' => getPakistaniTime('h:i A'),
                                'google_maps_url' => "https://maps.google.com/?q={$location['latitude']},{$location['longitude']}"
                            ];
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Tracking data fetch error: " . $e->getMessage());
    }
    
    return $locations;
}

// If this is an AJAX request for tracking data
if (isset($_GET['action']) && $_GET['action'] === 'get_tracking_data') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'locations' => getImprovedTrackingData(),
        'timestamp' => getPakistaniTime('Y-m-d H:i:s A')
    ]);
    exit;
}

// Generate improved tracking page HTML
function generateTrackingPageHTML($locations) {
    $html = '
    <div class="tracking-container">
        <style>
            .tracking-container {
                padding: 20px;
                background: #f8f9fa;
                min-height: 100vh;
            }
            
            .tracking-header {
                background: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                margin-bottom: 20px;
            }
            
            .user-location-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            
            .user-location-card {
                background: white;
                border-radius: 8px;
                padding: 20px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                border-left: 4px solid #007bff;
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }
            
            .user-location-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }
            
            .user-info {
                margin-bottom: 15px;
            }
            
            .user-name {
                font-size: 18px;
                font-weight: 600;
                color: #333;
                margin-bottom: 5px;
            }
            
            .user-username {
                font-size: 14px;
                color: #666;
                margin-bottom: 10px;
            }
            
            .status-badge {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 500;
                text-transform: uppercase;
            }
            
            .status-online {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            
            .status-offline {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
            
            .location-details {
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid #eee;
            }
            
            .detail-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 8px;
                font-size: 14px;
            }
            
            .detail-label {
                font-weight: 500;
                color: #555;
            }
            
            .detail-value {
                color: #333;
                text-align: right;
                flex: 1;
                margin-left: 10px;
            }
            
            .google-maps-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                background: #1a73e8;
                color: white;
                padding: 10px 16px;
                border-radius: 6px;
                text-decoration: none;
                font-size: 14px;
                font-weight: 500;
                margin-top: 15px;
                transition: background-color 0.2s ease;
                border: none;
                cursor: pointer;
            }
            
            .google-maps-btn:hover {
                background: #1557b0;
                color: white;
                text-decoration: none;
            }
            
            .google-maps-btn:focus {
                outline: 2px solid #1a73e8;
                outline-offset: 2px;
            }
            
            .maps-icon {
                width: 16px;
                height: 16px;
            }
            
            .last-updated {
                text-align: center;
                color: #666;
                font-size: 14px;
                margin-top: 20px;
                padding: 10px;
                background: white;
                border-radius: 6px;
            }
            
            .no-locations {
                text-align: center;
                padding: 40px;
                background: white;
                border-radius: 8px;
                color: #666;
            }
            
            .refresh-indicator {
                position: fixed;
                top: 20px;
                right: 20px;
                background: #28a745;
                color: white;
                padding: 8px 16px;
                border-radius: 20px;
                font-size: 12px;
                opacity: 0;
                transition: opacity 0.3s ease;
                z-index: 1000;
            }
            
            .refresh-indicator.show {
                opacity: 1;
            }
        </style>
        
        <div class="tracking-header">
            <h2 style="margin: 0; color: #333;">📍 Live User Tracking</h2>
            <p style="margin: 10px 0 0 0; color: #666;">Real-time location updates • Auto-refresh every 59 seconds</p>
        </div>
        
        <div class="refresh-indicator" id="refresh-indicator">
            🔄 Refreshing locations...
        </div>
        
        <div id="user-locations-list" class="user-location-grid">';
    
    if (empty($locations)) {
        $html .= '
            <div class="no-locations">
                <h3>No Active Locations</h3>
                <p>No users are currently checked in with location tracking enabled.</p>
            </div>';
    } else {
        foreach ($locations as $location) {
            $statusClass = $location['is_checked_in'] ? 'status-online' : 'status-offline';
            $statusText = $location['is_checked_in'] ? 'Checked In' : 'Checked Out';
            
            $html .= '
            <div class="user-location-card">
                <div class="user-info">
                    <div class="user-name">' . htmlspecialchars($location['full_name']) . '</div>
                    <div class="user-username">@' . htmlspecialchars($location['username']) . '</div>
                    <span class="status-badge ' . $statusClass . '">' . $statusText . '</span>
                </div>
                
                <div class="location-details">
                    <div class="detail-row">
                        <span class="detail-label">Role:</span>
                        <span class="detail-value">' . htmlspecialchars($location['user_role'] ?? $location['role']) . '</span>
                    </div>';
            
            if (!empty($location['work_duration'])) {
                $html .= '
                    <div class="detail-row">
                        <span class="detail-label">Work Duration:</span>
                        <span class="detail-value">' . htmlspecialchars($location['work_duration']) . '</span>
                    </div>';
            }
            
            $html .= '
                    <div class="detail-row">
                        <span class="detail-label">Last Updated:</span>
                        <span class="detail-value">' . htmlspecialchars($location['formatted_time']) . '</span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Address:</span>
                        <span class="detail-value">' . htmlspecialchars($location['address']) . '</span>
                    </div>
                </div>
                
                <a href="' . $location['google_maps_url'] . '" 
                   target="_blank" 
                   class="google-maps-btn">
                    <svg class="maps-icon" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                    </svg>
                    Open in Google Maps
                </a>
            </div>';
        }
    }
    
    $html .= '
        </div>
        
        <div class="last-updated" id="last-updated">
            Last updated: ' . getPakistaniTime('h:i:s A') . '
        </div>
    </div>
    
    <script>
        // Include the admin auto-refresh functionality
        document.addEventListener("DOMContentLoaded", function() {
            // Start auto-refresh specific to tracking page
            if (window.location.search.includes("tab=tracking")) {
                console.log("Starting tracking page auto-refresh");
                
                // Refresh every 59 seconds
                setInterval(function() {
                    refreshTrackingData();
                }, 59000);
            }
        });
        
        // Function to refresh tracking data
        async function refreshTrackingData() {
            const indicator = document.getElementById("refresh-indicator");
            
            try {
                // Show refresh indicator
                if (indicator) {
                    indicator.classList.add("show");
                }
                
                const response = await fetch("admin_tracking_fixes.php?action=get_tracking_data");
                const data = await response.json();
                
                if (data.success) {
                    updateTrackingDisplay(data.locations);
                    
                    // Update last updated time
                    const lastUpdated = document.getElementById("last-updated");
                    if (lastUpdated) {
                        lastUpdated.textContent = `Last updated: ${data.timestamp}`;
                    }
                }
                
            } catch (error) {
                console.error("Error refreshing tracking data:", error);
            } finally {
                // Hide refresh indicator after 1 second
                setTimeout(() => {
                    if (indicator) {
                        indicator.classList.remove("show");
                    }
                }, 1000);
            }
        }
        
        // Function to update the tracking display
        function updateTrackingDisplay(locations) {
            const container = document.getElementById("user-locations-list");
            if (!container) return;
            
            // Clear existing content
            container.innerHTML = "";
            
            if (locations.length === 0) {
                container.innerHTML = `
                    <div class="no-locations">
                        <h3>No Active Locations</h3>
                        <p>No users are currently checked in with location tracking enabled.</p>
                    </div>
                `;
                return;
            }
            
            // Add updated location cards
            locations.forEach(location => {
                const card = createLocationCard(location);
                container.appendChild(card);
            });
        }
        
        // Function to create location card element
        function createLocationCard(location) {
            const card = document.createElement("div");
            card.className = "user-location-card";
            
            const statusClass = location.is_checked_in ? "status-online" : "status-offline";
            const statusText = location.is_checked_in ? "Checked In" : "Checked Out";
            
            card.innerHTML = `
                <div class="user-info">
                    <div class="user-name">${escapeHtml(location.full_name)}</div>
                    <div class="user-username">@${escapeHtml(location.username)}</div>
                    <span class="status-badge ${statusClass}">${statusText}</span>
                </div>
                
                <div class="location-details">
                    <div class="detail-row">
                        <span class="detail-label">Role:</span>
                        <span class="detail-value">${escapeHtml(location.user_role || location.role)}</span>
                    </div>
                    ${location.work_duration ? `
                    <div class="detail-row">
                        <span class="detail-label">Work Duration:</span>
                        <span class="detail-value">${escapeHtml(location.work_duration)}</span>
                    </div>` : ""}
                    <div class="detail-row">
                        <span class="detail-label">Last Updated:</span>
                        <span class="detail-value">${escapeHtml(location.formatted_time)}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Address:</span>
                        <span class="detail-value">${escapeHtml(location.address)}</span>
                    </div>
                </div>
                
                <a href="${location.google_maps_url}" target="_blank" class="google-maps-btn">
                    <svg class="maps-icon" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                    </svg>
                    Open in Google Maps
                </a>
            `;
            
            return card;
        }
        
        // Utility function to escape HTML
        function escapeHtml(text) {
            const map = {
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                "\"": "&quot;",
                "\'": "&#039;"
            };
            return text.replace(/[&<>"\']/g, function(m) { return map[m]; });
        }
    </script>';
    
    return $html;
}

// If this is not an AJAX request, return the full HTML
if (!isset($_GET['action'])) {
    $locations = getImprovedTrackingData();
    echo generateTrackingPageHTML($locations);
}
?>
