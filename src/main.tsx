
import React from 'react'; // Add explicit React import
import { createRoot } from 'react-dom/client';
import App from './App.tsx';
import './index.css';

// Make sure we have a valid root element
const rootElement = document.getElementById("root");

if (!rootElement) {
  console.error("Root element not found!");
} else {
  createRoot(rootElement).render(
    <React.StrictMode>
      <App />
    </React.StrictMode>
  );
}
