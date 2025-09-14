import React, { useState, useEffect } from 'react';

const ReferralDashboard = () => {
    const [stats, setStats] = useState({
        referral_code: '',
        total_referrals: 0,
        total_earnings: 0,
        bonus_per_referral: 10.00
    });
    const [referrals, setReferrals] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    // Get WordPress AJAX URL (assuming it's available globally)
    const ajaxUrl = window.ajaxurl || '/wp-admin/admin-ajax.php';

    useEffect(() => {
        fetchReferralData();
    }, []);

    const fetchReferralData = async () => {
        setLoading(true);
        setError('');

        try {
            // Fetch stats
            const statsResponse = await fetch(ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'affiliate_bloom_get_referral_stats',
                    nonce: affiliateBloom.nonce
                })
            });

            const statsData = await statsResponse.json();

            if (statsData.success) {
                setStats(statsData.data.stats);
            } else {
                throw new Error(statsData.data?.message || 'Failed to fetch stats');
            }

            // Fetch referrals list
            const referralsResponse = await fetch(ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'affiliate_bloom_get_referral_history',
                    nonce: affiliateBloom.nonce
                })
            });

            const referralsData = await referralsResponse.json();

            if (referralsData.success) {
                setReferrals(referralsData.data.referrals || []);
            } else {
                throw new Error(referralsData.data?.message || 'Failed to fetch referrals');
            }

        } catch (err) {
            console.error('Error fetching referral data:', err);
            setError('Failed to load referral data. Please refresh the page.');
        } finally {
            setLoading(false);
        }
    };

    const generateReferralUrl = (targetPath = '') => {
        let fullUrl;

        if (targetPath && targetPath.startsWith('http')) {
            // Full URL provided
            fullUrl = targetPath;
        } else if (targetPath) {
            // Relative path provided
            fullUrl = window.location.origin + targetPath;
        } else {
            // No path provided, use current origin
            fullUrl = window.location.origin;
        }

        try {
            const url = new URL(fullUrl);
            url.searchParams.set('ref', stats.referral_code);
            return url.toString();
        } catch (error) {
            // Fallback if URL construction fails
            const separator = fullUrl.includes('?') ? '&' : '?';
            return `${fullUrl}${separator}ref=${stats.referral_code}`;
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
                day: 'numeric'
            });
        } catch {
            return dateString;
        }
    };

    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    };

    if (loading) {
        return (
            <div className="min-h-screen bg-gray-50 flex items-center justify-center">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p className="text-gray-600">Loading your referral dashboard...</p>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="min-h-screen bg-gray-50 flex items-center justify-center">
                <div className="text-center">
                    <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg className="w-8 h-8 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                        </svg>
                    </div>
                    <h3 className="text-lg font-medium text-gray-900 mb-2">Error Loading Data</h3>
                    <p className="text-gray-600 mb-4">{error}</p>
                    <button
                        onClick={fetchReferralData}
                        className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        Retry
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gray-50">
            <div className="max-w-4xl mx-auto px-4 py-8">
                {/* Stats Cards */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-gray-500 text-sm font-medium">Total Referrals</p>
                                <p className="text-2xl font-bold text-blue-600">{stats.total_referrals}</p>
                            </div>
                            <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <svg className="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-gray-500 text-sm font-medium">Total Earnings</p>
                                <p className="text-2xl font-bold text-green-600">{formatCurrency(stats.total_earnings)}</p>
                            </div>
                            <div className="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <svg className="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z" />
                                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clipRule="evenodd" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-gray-500 text-sm font-medium">Per Referral</p>
                                <p className="text-2xl font-bold text-purple-600">{formatCurrency(stats.bonus_per_referral)}</p>
                            </div>
                            <div className="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                <svg className="w-6 h-6 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fillRule="evenodd" d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1zM12 2a1 1 0 01.967.744L14.146 7.2 17.5 9.134a1 1 0 010 1.732L14.146 12.8l-1.179 4.456a1 1 0 01-1.934 0L9.854 12.8 6.5 10.866a1 1 0 010-1.732L9.854 7.2l1.179-4.456A1 1 0 0112 2z" clipRule="evenodd" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Referral Code Section */}
                <div className="bg-gradient-to-r from-blue-500 to-purple-600 text-white p-6 rounded-lg shadow-lg mb-8">
                    <div className="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div>
                            <h3 className="text-xl font-semibold mb-2">Your Referral Code</h3>
                            <p className="text-blue-100 mb-4">Share this code or add it to any URL</p>
                        </div>
                        <div className="flex items-center space-x-4">
                            <div className="bg-white bg-opacity-20 px-4 py-2 rounded-lg">
                                <code className="text-lg font-mono">{stats.referral_code}</code>
                            </div>
                            <button
                                onClick={() => handleCopyUrl(stats.referral_code)}
                                className="bg-white bg-opacity-20 hover:bg-opacity-30 px-4 py-2 rounded-lg transition-all"
                            >
                                Copy Code
                            </button>
                        </div>
                    </div>
                </div>

                {/* Quick Links Section */}
                {stats.referral_code && (
                    <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8">
                        <h3 className="text-lg font-semibold text-gray-900 mb-4">Quick Referral Links</h3>
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Homepage</label>
                                <div className="flex items-center space-x-2">
                                    <input
                                        type="text"
                                        value={generateReferralUrl()}
                                        readOnly
                                        className="flex-1 px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-sm"
                                    />
                                    <button
                                        onClick={() => handleCopyUrl(generateReferralUrl())}
                                        className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm"
                                    >
                                        Copy
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Sign Up Page</label>
                                <div className="flex items-center space-x-2">
                                    <input
                                        type="text"
                                        value={generateReferralUrl('/register')}
                                        readOnly
                                        className="flex-1 px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-sm"
                                    />
                                    <button
                                        onClick={() => handleCopyUrl(generateReferralUrl('/register'))}
                                        className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm"
                                    >
                                        Copy
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Referrals List */}
                <div className="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div className="px-6 py-4 border-b border-gray-200">
                        <div className="flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-gray-900">Your Referrals</h3>
                            <button
                                onClick={fetchReferralData}
                                className="text-blue-600 hover:text-blue-800 text-sm font-medium"
                            >
                                Refresh
                            </button>
                        </div>
                    </div>
                    <div className="p-6">
                        {referrals.length === 0 ? (
                            <div className="text-center py-12 bg-gray-50 rounded-lg">
                                <div className="w-16 h-16 bg-gray-300 rounded-full mx-auto mb-4 flex items-center justify-center">
                                    <svg className="w-8 h-8 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                                    </svg>
                                </div>
                                <h4 className="text-lg font-medium text-gray-600 mb-2">No referrals yet</h4>
                                <p className="text-gray-500">Start sharing your referral links to earn rewards</p>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {referrals.map((referral) => (
                                    <div key={referral.id} className="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center space-x-3">
                                                <div className="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center">
                                                    <span className="text-white font-medium text-sm">
                                                        {referral.referred_username.charAt(0).toUpperCase()}
                                                    </span>
                                                </div>
                                                <div>
                                                    <h4 className="text-lg font-medium text-gray-900">{referral.referred_username}</h4>
                                                    <p className="text-sm text-gray-600">{referral.referred_email}</p>
                                                    <p className="text-xs text-gray-500">Joined {formatDate(referral.created_date)}</p>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <div className="text-lg font-semibold text-green-600">
                                                    +{formatCurrency(referral.bonus_amount)}
                                                </div>
                                                <div className="text-xs text-gray-500">Bonus earned</div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* How It Works */}
                <div className="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
                    <div className="flex items-start space-x-4">
                        <div className="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg className="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                            </svg>
                        </div>
                        <div>
                            <h4 className="text-lg font-semibold text-blue-900 mb-3">How It Works</h4>
                            <div className="text-blue-800 space-y-2">
                                <div className="flex items-start space-x-2">
                                    <span className="text-blue-600 font-semibold">1.</span>
                                    <p>Copy your referral code or one of the quick links above</p>
                                </div>
                                <div className="flex items-start space-x-2">
                                    <span className="text-blue-600 font-semibold">2.</span>
                                    <p>Share it with friends via social media, email, or direct message</p>
                                </div>
                                <div className="flex items-start space-x-2">
                                    <span className="text-blue-600 font-semibold">3.</span>
                                    <p>When someone registers using your link, you earn {formatCurrency(stats.bonus_per_referral)}</p>
                                </div>
                                <div className="flex items-start space-x-2">
                                    <span className="text-blue-600 font-semibold">4.</span>
                                    <p>Your earnings are automatically added to your account balance</p>
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