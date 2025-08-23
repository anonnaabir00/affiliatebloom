import React, { useState, useEffect } from 'react';

const ReferralDashboard = () => {
    const [activeTab, setActiveTab] = useState('links');
    const [newUrl, setNewUrl] = useState('');
    const [referralLinks, setReferralLinks] = useState([]);
    const [referralUsers, setReferralUsers] = useState([]);
    const [loading, setLoading] = useState(false);
    const [generating, setGenerating] = useState(false);
    const [currentPage, setCurrentPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);
    const [totalItems, setTotalItems] = useState(0);
    const [stats, setStats] = useState({
        total_referrals: 0,
        active_referrals: 0,
        total_bonus_earned: 0,
        pending_referrals: 0,
        referral_code: '',
        bonus_per_referral: 0,
        total_clicks: 0,
        conversion_rate: 0
    });

    // Mock data for demonstration - replace with actual API calls
    const mockStats = {
        total_referrals: 15,
        active_referrals: 12,
        total_bonus_earned: 150.00,
        pending_referrals: 3,
        referral_code: 'REF123_ABC456',
        bonus_per_referral: 10.00,
        total_clicks: 245,
        conversion_rate: 6.12
    };

    const mockLinks = [
        {
            id: 'link_1',
            name: 'Homepage Referral',
            target_url: 'https://example.com',
            affiliate_url: 'https://example.com?ref=REF123_ABC456',
            clicks: 89,
            conversions: 5,
            created_date: '2024-01-15 10:30:00',
            status: 'active'
        },
        {
            id: 'link_2',
            name: 'Product Page Referral',
            target_url: 'https://example.com/products',
            affiliate_url: 'https://example.com/products?ref=REF123_ABC456',
            clicks: 67,
            conversions: 3,
            created_date: '2024-01-20 14:20:00',
            status: 'active'
        }
    ];

    const mockReferrals = [
        {
            id: 'ref_1',
            referred_username: 'johndoe123',
            referred_email: 'john@example.com',
            status: 'completed',
            bonus_amount: 10.00,
            created_date: '2024-01-15 10:45:00',
            completed_date: '2024-01-15 11:00:00'
        },
        {
            id: 'ref_2',
            referred_username: 'janedoe456',
            referred_email: 'jane@example.com',
            status: 'pending',
            bonus_amount: 10.00,
            created_date: '2024-01-22 09:15:00',
            completed_date: null
        }
    ];

    useEffect(() => {
        // Initialize with mock data - replace with actual API calls
        setStats(mockStats);
        setReferralLinks(mockLinks);
        setReferralUsers(mockReferrals);
        setTotalItems(mockLinks.length);
        setTotalPages(1);
    }, []);

    const validateUrl = (url) => {
        try {
            new URL(url);
            return true;
        } catch {
            return false;
        }
    };

    const handleGenerateLink = () => {
        if (!newUrl.trim()) {
            alert('Please enter a page URL');
            return;
        }

        if (!validateUrl(newUrl.trim())) {
            alert('Please enter a valid URL (e.g., https://example.com/page)');
            return;
        }

        setGenerating(true);

        // Simulate API call
        setTimeout(() => {
            const newLink = {
                id: 'link_' + Date.now(),
                name: 'Link to ' + new URL(newUrl.trim()).hostname,
                target_url: newUrl.trim(),
                affiliate_url: newUrl.trim() + (newUrl.includes('?') ? '&' : '?') + 'ref=' + stats.referral_code,
                clicks: 0,
                conversions: 0,
                created_date: new Date().toISOString(),
                status: 'active'
            };

            setReferralLinks(prev => [newLink, ...prev]);
            setNewUrl('');
            setGenerating(false);
            alert('Referral link generated successfully!');
        }, 1000);
    };

    const handleDeleteLink = (linkId) => {
        if (window.confirm('Are you sure you want to delete this link? This action cannot be undone.')) {
            setReferralLinks(prev => prev.filter(link => link.id !== linkId));
            alert('Link deleted successfully');
        }
    };

    const handleCopyUrl = (url) => {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(() => {
                alert('URL copied to clipboard!');
            }).catch(() => {
                fallbackCopyTextToClipboard(url);
            });
        } else {
            fallbackCopyTextToClipboard(url);
        }
    };

    const fallbackCopyTextToClipboard = (text) => {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.top = "0";
        textArea.style.left = "0";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            const successful = document.execCommand('copy');
            if (successful) {
                alert('URL copied to clipboard!');
            } else {
                alert('Failed to copy URL');
            }
        } catch (err) {
            alert('Failed to copy URL');
        }
        document.body.removeChild(textArea);
    };

    const formatDate = (dateString) => {
        try {
            return new Date(dateString).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch {
            return dateString;
        }
    };

    const StatCard = ({ title, value, subtitle, color = "blue" }) => (
        <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-gray-500 text-sm font-medium">{title}</p>
                    <p className={`text-2xl font-bold text-${color}-600`}>{value}</p>
                    {subtitle && <p className="text-xs text-gray-400 mt-1">{subtitle}</p>}
                </div>
                <div className={`w-12 h-12 bg-${color}-100 rounded-lg flex items-center justify-center`}>
                    <div className={`w-6 h-6 bg-${color}-600 rounded`}></div>
                </div>
            </div>
        </div>
    );

    return (
        <div className="min-h-screen bg-gray-50">
            <div className="max-w-7xl mx-auto px-4 py-8">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-3xl font-bold text-gray-900 mb-2">Referral Dashboard</h1>
                    <p className="text-gray-600">Manage your referral links and track your earnings</p>
                </div>

                {/* Stats Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <StatCard
                        title="Total Referrals"
                        value={stats.total_referrals}
                        subtitle="All time"
                        color="blue"
                    />
                    <StatCard
                        title="Active Referrals"
                        value={stats.active_referrals}
                        subtitle="Completed registrations"
                        color="green"
                    />
                    <StatCard
                        title="Total Earnings"
                        value={`$${stats.total_bonus_earned.toFixed(2)}`}
                        subtitle={`$${stats.bonus_per_referral} per referral`}
                        color="purple"
                    />
                    <StatCard
                        title="Conversion Rate"
                        value={`${stats.conversion_rate}%`}
                        subtitle={`${stats.total_clicks} total clicks`}
                        color="orange"
                    />
                </div>

                {/* Referral Code Card */}
                <div className="bg-gradient-to-r from-blue-500 to-purple-600 text-white p-6 rounded-lg shadow-lg mb-8">
                    <div className="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div>
                            <h3 className="text-xl font-semibold mb-2">Your Referral Code</h3>
                            <p className="text-blue-100 mb-4">Share this code or use it in your referral links</p>
                        </div>
                        <div className="flex items-center space-x-4">
                            <div className="bg-white bg-opacity-20 px-4 py-2 rounded-lg">
                                <code className="text-lg font-mono">{stats.referral_code}</code>
                            </div>
                            <button
                                onClick={() => handleCopyUrl(stats.referral_code)}
                                className="bg-white bg-opacity-20 hover:bg-opacity-30 px-4 py-2 rounded-lg transition-all"
                            >
                                Copy
                            </button>
                        </div>
                    </div>
                </div>

                {/* Tab Navigation */}
                <div className="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
                    <div className="border-b border-gray-200">
                        <nav className="flex space-x-8 px-6">
                            <button
                                onClick={() => setActiveTab('links')}
                                className={`py-4 px-1 border-b-2 font-medium text-sm ${
                                    activeTab === 'links'
                                        ? 'border-blue-500 text-blue-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                }`}
                            >
                                Referral Links
                            </button>
                            <button
                                onClick={() => setActiveTab('referrals')}
                                className={`py-4 px-1 border-b-2 font-medium text-sm ${
                                    activeTab === 'referrals'
                                        ? 'border-blue-500 text-blue-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                }`}
                            >
                                My Referrals
                            </button>
                        </nav>
                    </div>

                    {/* Tab Content */}
                    <div className="p-6">
                        {activeTab === 'links' && (
                            <div>
                                {/* Generate New Link Section */}
                                <div className="mb-8">
                                    <h3 className="text-lg font-semibold text-gray-900 mb-4">Generate New Referral Link</h3>
                                    <div className="flex flex-col sm:flex-row gap-4">
                                        <input
                                            type="url"
                                            placeholder="Enter page URL (e.g., https://example.com/products/premium)"
                                            value={newUrl}
                                            onChange={(e) => setNewUrl(e.target.value)}
                                            className="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                            onKeyPress={(e) => e.key === 'Enter' && handleGenerateLink()}
                                        />
                                        <button
                                            onClick={handleGenerateLink}
                                            disabled={generating || !newUrl.trim()}
                                            className="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors"
                                        >
                                            {generating ? 'Generating...' : 'Generate Link'}
                                        </button>
                                    </div>
                                    <p className="text-sm text-gray-500 mt-2">
                                        Enter the full URL you want to create a referral link for
                                    </p>
                                </div>

                                {/* Links List */}
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-900 mb-4">Your Referral Links</h3>
                                    {referralLinks.length === 0 ? (
                                        <div className="text-center py-12 bg-gray-50 rounded-lg">
                                            <div className="text-gray-400 mb-4">
                                                <div className="w-16 h-16 bg-gray-300 rounded-full mx-auto mb-4 flex items-center justify-center">
                                                    <svg className="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fillRule="evenodd" d="M12.586 4.586a2 2 0 112.828 2.828l-3 3a2 2 0 01-2.828 0 1 1 0 00-1.414 1.414 4 4 0 005.656 0l3-3a4 4 0 00-5.656-5.656l-1.5 1.5a1 1 0 101.414 1.414l1.5-1.5zm-5 5a2 2 0 012.828 0 1 1 0 101.414-1.414 4 4 0 00-5.656 0l-3 3a4 4 0 105.656 5.656l1.5-1.5a1 1 0 10-1.414-1.414l-1.5 1.5a2 2 0 11-2.828-2.828l3-3z" clipRule="evenodd" />
                                                    </svg>
                                                </div>
                                                <h4 className="text-lg font-medium text-gray-600 mb-2">No referral links found</h4>
                                                <p className="text-gray-500">Generate your first referral link above</p>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="space-y-4">
                                            {referralLinks.map((link) => (
                                                <div key={link.id} className="bg-gray-50 border border-gray-200 rounded-lg p-6">
                                                    <div className="flex flex-col lg:flex-row lg:items-start lg:justify-between mb-4">
                                                        <div className="flex-1">
                                                            <div className="flex items-center space-x-3 mb-2">
                                                                <h4 className="text-lg font-medium text-gray-900">{link.name}</h4>
                                                                <span className={`px-2 py-1 text-xs rounded-full ${
                                                                    link.status === 'active'
                                                                        ? 'bg-green-100 text-green-800'
                                                                        : 'bg-gray-100 text-gray-800'
                                                                }`}>
                                                                    {link.status}
                                                                </span>
                                                            </div>
                                                            <div className="flex flex-wrap gap-4 text-sm text-gray-600 mb-4">
                                                                <span>👆 {link.clicks} clicks</span>
                                                                <span>✅ {link.conversions} conversions</span>
                                                                <span>📅 Created {formatDate(link.created_date)}</span>
                                                            </div>
                                                        </div>
                                                        <button
                                                            onClick={() => handleDeleteLink(link.id)}
                                                            className="text-red-600 hover:text-red-800 transition-colors"
                                                        >
                                                            <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fillRule="evenodd" d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9zM4 5a2 2 0 012-2v1a1 1 0 001 1h6a1 1 0 001-1V3a2 2 0 012 2v6.5l1.5-.5L17 13H4v8z" clipRule="evenodd" />
                                                            </svg>
                                                        </button>
                                                    </div>

                                                    {/* Target URL */}
                                                    <div className="mb-4">
                                                        <label className="block text-sm font-medium text-gray-700 mb-2">Target URL:</label>
                                                        <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                                            <p className="text-blue-800 text-sm break-all">{link.target_url}</p>
                                                        </div>
                                                    </div>

                                                    {/* Referral URL */}
                                                    <div>
                                                        <div className="flex items-center justify-between mb-2">
                                                            <label className="block text-sm font-medium text-gray-700">Your Referral URL:</label>
                                                            <div className="flex space-x-2">
                                                                <button
                                                                    onClick={() => handleCopyUrl(link.affiliate_url)}
                                                                    className="text-green-600 hover:text-green-800 text-sm transition-colors flex items-center space-x-1"
                                                                >
                                                                    <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" />
                                                                        <path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z" />
                                                                    </svg>
                                                                    <span>Copy</span>
                                                                </button>
                                                                <button
                                                                    onClick={() => window.open(link.affiliate_url, '_blank')}
                                                                    className="text-blue-600 hover:text-blue-800 text-sm transition-colors flex items-center space-x-1"
                                                                >
                                                                    <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path fillRule="evenodd" d="M4.25 5.5a.75.75 0 00-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 00.75-.75v-4a.75.75 0 011.5 0v4A2.25 2.25 0 0112.75 17h-8.5A2.25 2.25 0 012 14.75v-8.5A2.25 2.25 0 014.25 4h5a.75.75 0 010 1.5h-5z" clipRule="evenodd" />
                                                                        <path fillRule="evenodd" d="M6.194 12.753a.75.75 0 001.06.053L16.5 4.44v2.81a.75.75 0 001.5 0v-4.5a.75.75 0 00-.75-.75h-4.5a.75.75 0 000 1.5h2.553l-9.056 8.194a.75.75 0 00-.053 1.06z" clipRule="evenodd" />
                                                                    </svg>
                                                                    <span>Open</span>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div className="bg-green-50 border border-green-200 rounded-lg p-3">
                                                            <code className="text-green-800 text-sm break-all">{link.affiliate_url}</code>
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

                        {activeTab === 'referrals' && (
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900 mb-4">Your Referrals</h3>
                                {referralUsers.length === 0 ? (
                                    <div className="text-center py-12 bg-gray-50 rounded-lg">
                                        <div className="text-gray-400 mb-4">
                                            <div className="w-16 h-16 bg-gray-300 rounded-full mx-auto mb-4 flex items-center justify-center">
                                                <svg className="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                                                </svg>
                                            </div>
                                            <h4 className="text-lg font-medium text-gray-600 mb-2">No referrals yet</h4>
                                            <p className="text-gray-500">Start sharing your referral links to get your first referrals</p>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="space-y-4">
                                        {referralUsers.map((referral) => (
                                            <div key={referral.id} className="bg-gray-50 border border-gray-200 rounded-lg p-6">
                                                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                                                    <div className="flex-1">
                                                        <div className="flex items-center space-x-3 mb-2">
                                                            <div className="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center">
                                                                <span className="text-white font-medium text-sm">
                                                                    {referral.referred_username.charAt(0).toUpperCase()}
                                                                </span>
                                                            </div>
                                                            <div>
                                                                <h4 className="text-lg font-medium text-gray-900">{referral.referred_username}</h4>
                                                                <p className="text-sm text-gray-600">{referral.referred_email}</p>
                                                            </div>
                                                        </div>
                                                        <div className="flex flex-wrap gap-4 text-sm text-gray-600">
                                                            <span>💰 ${referral.bonus_amount.toFixed(2)} bonus</span>
                                                            <span>📅 Joined {formatDate(referral.created_date)}</span>
                                                            {referral.completed_date && (
                                                                <span>✅ Completed {formatDate(referral.completed_date)}</span>
                                                            )}
                                                        </div>
                                                    </div>
                                                    <div className="mt-4 sm:mt-0">
                                                        <span className={`px-3 py-1 text-sm rounded-full font-medium ${
                                                            referral.status === 'completed'
                                                                ? 'bg-green-100 text-green-800'
                                                                : referral.status === 'pending'
                                                                    ? 'bg-yellow-100 text-yellow-800'
                                                                    : 'bg-gray-100 text-gray-800'
                                                        }`}>
                                                            {referral.status === 'completed' ? '✅ Completed' :
                                                                referral.status === 'pending' ? '⏳ Pending' : referral.status}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </div>

                {/* How It Works Section */}
                <div className="bg-blue-50 border border-blue-200 rounded-lg p-6">
                    <div className="flex items-start space-x-4">
                        <div className="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg className="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                            </svg>
                        </div>
                        <div>
                            <h4 className="text-lg font-semibold text-blue-900 mb-3">How Referrals Work</h4>
                            <div className="text-blue-800 space-y-2">
                                <div className="flex items-start space-x-2">
                                    <span className="text-blue-600 font-semibold">1.</span>
                                    <p>Generate a referral link for any page on your website</p>
                                </div>
                                <div className="flex items-start space-x-2">
                                    <span className="text-blue-600 font-semibold">2.</span>
                                    <p>Share the link with potential customers via social media, email, or direct messaging</p>
                                </div>
                                <div className="flex items-start space-x-2">
                                    <span className="text-blue-600 font-semibold">3.</span>
                                    <p>When someone clicks your link, they'll see a registration form with your referral code</p>
                                </div>
                                <div className="flex items-start space-x-2">
                                    <span className="text-blue-600 font-semibold">4.</span>
                                    <p>When they sign up through your link, you earn ${stats.bonus_per_referral.toFixed(2)} bonus</p>
                                </div>
                                <div className="flex items-start space-x-2">
                                    <span className="text-blue-600 font-semibold">5.</span>
                                    <p>Track your referral performance and earnings in this dashboard</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default ReferralDashboard;