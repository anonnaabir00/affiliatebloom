import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import './index.css';

import AffiliateLinks from "./shortcodes/AffiliateLinks.jsx";
import LoginBonus from "./shortcodes/LoginBonus.jsx";
import ReferralLinks from "./shortcodes/ReferralLinks.jsx";
import ReferralDashboard from "./shortcodes/ReferralLinks.jsx";
import AuthLogin from "./shortcodes/AuthLogin.jsx";
import AuthRegister from "./shortcodes/AuthRegister.jsx";
import DashboardStats from "./shortcodes/DashboardStats.jsx";
import AffiliateOffers from "./shortcodes/AffiliateOffers.jsx";


const affiliateLinksElements = document.querySelectorAll('.affiliate_links');
affiliateLinksElements.forEach(element => {
    const key = element.getAttribute('data-key');
    createRoot(element).render(
        <AffiliateLinks dataKey={key} />
    );
});

const ReferralLinksElements = document.querySelectorAll('.referral_links');
ReferralLinksElements.forEach(element => {
    const key = element.getAttribute('data-key');
    createRoot(element).render(
        <ReferralDashboard dataKey={key} />
    );
});

const partnerLoginElements = document.querySelectorAll('.auth_login');
partnerLoginElements.forEach(element => {
    const key = element.getAttribute('data-key');
    createRoot(element).render(
        <AuthLogin dataKey={key} />
    );
});

const partnerRegistrationElements = document.querySelectorAll('.auth_register');
partnerRegistrationElements.forEach(element => {
    const key = element.getAttribute('data-key');
    createRoot(element).render(
        <AuthRegister dataKey={key} />
    );
});

const LoginBonusElements = document.querySelectorAll('.login_bonus');
LoginBonusElements.forEach(element => {
    const key = element.getAttribute('data-key');
    createRoot(element).render(
        <LoginBonus dataKey={key} />
    );
});

const DashboardStatsElements = document.querySelectorAll('.dashboard_stats');
DashboardStatsElements.forEach(element => {
    const key = element.getAttribute('data-key');
    createRoot(element).render(
        <DashboardStats dataKey={key} />
    );
});

const affiliateOffersElements = document.querySelectorAll('.affiliate_offers');
affiliateOffersElements.forEach(element => {
    const key = element.getAttribute('data-key');
    createRoot(element).render(
        <AffiliateOffers dataKey={key} />
    );
});