import { createRoot } from 'react-dom/client'
import { StrictMode } from 'react'
import App from './App'
import './index.css'

// Admin panel root
const adminElement = document.getElementById("affiliate-bloom-admin")
if (adminElement) {
    createRoot(adminElement).render(
        <StrictMode>
            <App />
        </StrictMode>
    )
}