import React, { useState, useEffect } from 'react';
import { Card, Button, Space, Spin, Row, Col, Tabs, Progress } from 'antd';
import { LineChart, Line, AreaChart, Area, BarChart, Bar, PieChart, Pie, Cell, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import AffiliateOffers from "./AffiliateOffers.jsx";
import { fetchData } from "../services/fetchData.js";

const DashboardStats = () => {
    const [stats, setStats] = useState({
        total_clicks: 0,
        total_conversions: 0,
        conversion_rate: 0,
        total_earnings: 0,
        pending_earnings: 0,
        current_balance: 0
    });
    const [loading, setLoading] = useState(false);
    const [autoRefresh, setAutoRefresh] = useState(false);
    const [chartData, setChartData] = useState([]);
    const [earningsData, setEarningsData] = useState([]);
    const [clicksData, setClicksData] = useState([]);
    const [performanceData, setPerformanceData] = useState([]);
    const [chartLoading, setChartLoading] = useState(false);

    // Load initial stats and chart data
    useEffect(() => {
        loadUserStats();
        loadChartData();
    }, []);

    // Auto-refresh every 30 seconds when enabled
    useEffect(() => {
        let interval;
        if (autoRefresh) {
            interval = setInterval(() => {
                loadUserStats();
                loadChartData();
            }, 30000); // 30 seconds
        }
        return () => {
            if (interval) clearInterval(interval);
        };
    }, [autoRefresh]);

    const loadUserStats = () => {
        setLoading(true);
        fetchData('affiliate_bloom_get_user_stats', (response) => {
            setLoading(false);
            if (response.success && response.data?.stats) {
                setStats(response.data.stats);
                generateEarningsBreakdown(response.data.stats);
                generateClicksBreakdown(response.data.stats);
            }
        }, { nonce: affiliateBloom.nonce });
    };

    const loadChartData = () => {
        setChartLoading(true);

        // Load daily performance data (last 7 days)
        fetchData('affiliate_bloom_get_daily_stats', (response) => {
            let formattedData = [];

            if (response.success && response.data?.daily_stats && response.data.daily_stats.length > 0) {
                // Use real data if available
                formattedData = response.data.daily_stats.map(day => ({
                    day: new Date(day.date).toLocaleDateString('en', { weekday: 'short' }),
                    date: day.date,
                    clicks: parseInt(day.clicks) || 0,
                    conversions: parseInt(day.conversions) || 0,
                    earnings: parseFloat(day.earnings) || 0
                }));
            } else {
                // Generate demo data based on current stats for visualization
                const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                const baseClicks = Math.max(stats.total_clicks || 50, 10);
                const baseConversions = Math.max(stats.total_conversions || 5, 1);
                const baseEarnings = Math.max(stats.total_earnings || 25, 5);

                formattedData = days.map((dayName, index) => {
                    const date = new Date();
                    date.setDate(date.getDate() - (6 - index));

                    // Create realistic daily variations (10-30% of weekly average)
                    const dailyClicks = Math.floor((baseClicks / 7) * (0.7 + Math.random() * 0.6));
                    const dailyConversions = Math.floor((baseConversions / 7) * (0.5 + Math.random() * 1));
                    const dailyEarnings = parseFloat(((baseEarnings / 7) * (0.6 + Math.random() * 0.8)).toFixed(2));

                    return {
                        day: dayName,
                        date: date.toISOString().split('T')[0],
                        clicks: dailyClicks,
                        conversions: dailyConversions,
                        earnings: dailyEarnings
                    };
                });
            }

            setChartData(formattedData);
            setChartLoading(false);
        }, {
            nonce: affiliateBloom.nonce,
            days: 7
        });

        // Load performance targets data (focusing on clicks)
        fetchData('affiliate_bloom_get_performance_targets', (response) => {
            let performanceTargets = [];

            if (response.success && response.data?.targets) {
                performanceTargets = response.data.targets;
            } else {
                // Generate realistic targets based on current stats - focus on clicks
                const currentClicks = stats.total_clicks || 100;
                const currentEarnings = stats.total_earnings || 50;

                performanceTargets = [
                    {
                        metric: 'Clicks',
                        value: currentClicks,
                        target: Math.ceil(currentClicks * 1.25)
                    },
                    {
                        metric: 'Earnings ($)',
                        value: Math.floor(currentEarnings),
                        target: Math.ceil(currentEarnings * 1.3)
                    }
                ];
            }

            setPerformanceData(performanceTargets);
        }, { nonce: affiliateBloom.nonce });
    };

    const generateEarningsBreakdown = (statsData) => {
        const currentBalance = parseFloat(statsData.current_balance) || 0;
        const pendingEarnings = parseFloat(statsData.pending_earnings) || 0;
        const totalEarnings = parseFloat(statsData.total_earnings) || 0;
        const withdrawnAmount = Math.max(0, totalEarnings - currentBalance - pendingEarnings);

        // If no real earnings data, create demo data for visualization
        if (totalEarnings === 0 && currentBalance === 0 && pendingEarnings === 0) {
            setEarningsData([
                { name: 'Available Balance', value: 25, color: '#10B981' },
                { name: 'Pending Earnings', value: 15, color: '#F59E0B' },
                { name: 'Withdrawn', value: 60, color: '#6B7280' }
            ]);
            return;
        }

        const breakdown = [];

        if (currentBalance > 0) {
            breakdown.push({
                name: 'Available Balance',
                value: currentBalance,
                color: '#10B981'
            });
        }

        if (pendingEarnings > 0) {
            breakdown.push({
                name: 'Pending Earnings',
                value: pendingEarnings,
                color: '#F59E0B'
            });
        }

        if (withdrawnAmount > 0) {
            breakdown.push({
                name: 'Withdrawn',
                value: withdrawnAmount,
                color: '#6B7280'
            });
        }

        // If all values are 0, show a single segment
        if (breakdown.length === 0) {
            breakdown.push({
                name: 'No Earnings Yet',
                value: 1,
                color: '#E5E7EB'
            });
        }

        setEarningsData(breakdown);
    };

    const generateClicksBreakdown = (statsData) => {
        const totalClicks = parseInt(statsData.total_clicks) || 0;
        const totalConversions = parseInt(statsData.total_conversions) || 0;
        const nonConvertingClicks = Math.max(0, totalClicks - totalConversions);

        // If no real clicks data, create demo data for visualization
        if (totalClicks === 0) {
            setClicksData([
                { name: 'Converting Clicks', value: 15, color: '#10B981' },
                { name: 'Non-Converting Clicks', value: 85, color: '#3B82F6' }
            ]);
            return;
        }

        const breakdown = [];

        if (totalConversions > 0) {
            breakdown.push({
                name: 'Converting Clicks',
                value: totalConversions,
                color: '#10B981'
            });
        }

        if (nonConvertingClicks > 0) {
            breakdown.push({
                name: 'Non-Converting Clicks',
                value: nonConvertingClicks,
                color: '#3B82F6'
            });
        }

        // If all values are 0, show a single segment
        if (breakdown.length === 0) {
            breakdown.push({
                name: 'No Clicks Yet',
                value: 1,
                color: '#E5E7EB'
            });
        }

        setClicksData(breakdown);
    };

    const handleRefresh = () => {
        loadUserStats();
        loadChartData();
    };

    const toggleAutoRefresh = () => {
        setAutoRefresh(!autoRefresh);
    };

    // Helper function to format numbers
    const formatNumber = (num) => {
        return new Intl.NumberFormat().format(num || 0);
    };

    // Custom colors for charts
    const CHART_COLORS = ['#3B82F6', '#10B981', '#8B5CF6', '#F59E0B', '#EF4444', '#06B6D4'];

    return (
        <div className="p-6 bg-gray-50">
            <div className="max-w-7xl mx-auto">
                {/* Header */}
                {/*<div className="flex justify-between items-center mb-6">*/}
                {/*    <Space>*/}
                {/*        <Button*/}
                {/*            type={autoRefresh ? "primary" : "default"}*/}
                {/*            onClick={toggleAutoRefresh}*/}
                {/*            className={autoRefresh ? "bg-green-500 hover:bg-green-600" : ""}*/}
                {/*        >*/}
                {/*            <i className={`fas ${autoRefresh ? 'fa-pause' : 'fa-play'} mr-2`}></i>*/}
                {/*            {autoRefresh ? 'Stop Auto-Refresh' : 'Auto-Refresh'}*/}
                {/*        </Button>*/}
                {/*        <Button*/}
                {/*            type="primary"*/}
                {/*            onClick={handleRefresh}*/}
                {/*            className="bg-blue-500 hover:bg-blue-600"*/}
                {/*            loading={loading}*/}
                {/*        >*/}
                {/*            <i className="fas fa-refresh mr-2"></i>*/}
                {/*            Refresh Stats*/}
                {/*        </Button>*/}
                {/*    </Space>*/}
                {/*</div>*/}

                {loading ? (
                    <div className="text-center py-8">
                        <Spin size="large" />
                        <div className="mt-4 text-gray-600">Loading statistics...</div>
                    </div>
                ) : (
                    <>
                        {/* Stats Cards */}
                        <Row gutter={[16, 16]} className="mb-8">
                            <Col xs={12} sm={8} lg={4}>
                                <Card className="text-center hover:shadow-md transition-shadow">
                                    <div className="text-2xl font-bold text-blue-600">{formatNumber(stats.total_clicks)}</div>
                                    <div className="text-gray-500 text-sm">Total Clicks</div>
                                    <Progress
                                        percent={Math.min((stats.total_clicks / 1000) * 100, 100)}
                                        showInfo={false}
                                        strokeColor="#3B82F6"
                                        size="small"
                                        className="mt-2"
                                    />
                                </Card>
                            </Col>
                            <Col xs={12} sm={8} lg={4}>
                                <Card className="text-center hover:shadow-md transition-shadow">
                                    <div className="text-2xl font-bold text-green-600">{formatNumber(stats.total_conversions)}</div>
                                    <div className="text-gray-500 text-sm">Conversions</div>
                                    <Progress
                                        percent={Math.min((stats.total_conversions / 100) * 100, 100)}
                                        showInfo={false}
                                        strokeColor="#10B981"
                                        size="small"
                                        className="mt-2"
                                    />
                                </Card>
                            </Col>
                            <Col xs={12} sm={8} lg={4}>
                                <Card className="text-center hover:shadow-md transition-shadow">
                                    <div className="text-2xl font-bold text-purple-600">{stats.conversion_rate}%</div>
                                    <div className="text-gray-500 text-sm">Conversion Rate</div>
                                    <Progress
                                        percent={stats.conversion_rate}
                                        showInfo={false}
                                        strokeColor="#8B5CF6"
                                        size="small"
                                        className="mt-2"
                                    />
                                </Card>
                            </Col>
                            <Col xs={12} sm={8} lg={4}>
                                <Card className="text-center hover:shadow-md transition-shadow">
                                    <div className="text-2xl font-bold text-orange-600">${formatNumber(stats.total_earnings)}</div>
                                    <div className="text-gray-500 text-sm">Total Earnings</div>
                                    <Progress
                                        percent={Math.min((stats.total_earnings / 10000) * 100, 100)}
                                        showInfo={false}
                                        strokeColor="#F59E0B"
                                        size="small"
                                        className="mt-2"
                                    />
                                </Card>
                            </Col>
                            <Col xs={12} sm={8} lg={4}>
                                <Card className="text-center hover:shadow-md transition-shadow">
                                    <div className="text-2xl font-bold text-yellow-600">${formatNumber(stats.pending_earnings)}</div>
                                    <div className="text-gray-500 text-sm">Pending</div>
                                    <Progress
                                        percent={Math.min((stats.pending_earnings / 1000) * 100, 100)}
                                        showInfo={false}
                                        strokeColor="#EAB308"
                                        size="small"
                                        className="mt-2"
                                    />
                                </Card>
                            </Col>
                            <Col xs={12} sm={8} lg={4}>
                                <Card className="text-center hover:shadow-md transition-shadow">
                                    <div className="text-2xl font-bold text-teal-600">${formatNumber(stats.current_balance)}</div>
                                    <div className="text-gray-500 text-sm">Balance</div>
                                    <Progress
                                        percent={Math.min((stats.current_balance / 5000) * 100, 100)}
                                        showInfo={false}
                                        strokeColor="#06B6D4"
                                        size="small"
                                        className="mt-2"
                                    />
                                </Card>
                            </Col>
                        </Row>

                        {/* Charts Section */}
                        <Card className="shadow-sm">
                            <Tabs
                                defaultActiveKey="overview"
                                items={[
                                    {
                                        key: 'overview',
                                        label: (
                                            <span>
                                                <i className="fas fa-chart-line mr-2"></i>
                                                Overview
                                            </span>
                                        ),
                                        children: (
                                            <Row gutter={[24, 24]}>
                                                {/* Clicks Breakdown */}
                                                <Col xs={24} lg={12}>
                                                    <Card title="Clicks Breakdown" className="h-full">
                                                        <ResponsiveContainer width="100%" height={300}>
                                                            <PieChart>
                                                                <Pie
                                                                    data={clicksData}
                                                                    cx="50%"
                                                                    cy="50%"
                                                                    labelLine={false}
                                                                    label={({ name, percent }) => `${name}: ${(percent * 1).toFixed(0)}%`}
                                                                    outerRadius={80}
                                                                    fill="#8884d8"
                                                                    dataKey="value"
                                                                >
                                                                    {clicksData.map((entry, index) => (
                                                                        <Cell key={`cell-${index}`} fill={entry.color} />
                                                                    ))}
                                                                </Pie>
                                                                <Tooltip
                                                                    formatter={(value) => [`${formatNumber(value)}`, 'Clicks']}
                                                                    contentStyle={{
                                                                        backgroundColor: '#f9fafb',
                                                                        border: '1px solid #e5e7eb',
                                                                        borderRadius: '8px'
                                                                    }}
                                                                />
                                                            </PieChart>
                                                        </ResponsiveContainer>
                                                    </Card>
                                                </Col>

                                                {/* Earnings Breakdown */}
                                                <Col xs={24} lg={12}>
                                                    <Card title="Earnings Breakdown" className="h-full">
                                                        <ResponsiveContainer width="100%" height={300}>
                                                            <PieChart>
                                                                <Pie
                                                                    data={earningsData}
                                                                    cx="50%"
                                                                    cy="50%"
                                                                    labelLine={false}
                                                                    label={({ name, percent }) => `${name}: ${(percent * 1).toFixed(0)}%`}
                                                                    outerRadius={80}
                                                                    fill="#8884d8"
                                                                    dataKey="value"
                                                                >
                                                                    {earningsData.map((entry, index) => (
                                                                        <Cell key={`cell-${index}`} fill={entry.color} />
                                                                    ))}
                                                                </Pie>
                                                                <Tooltip
                                                                    formatter={(value) => [`$${formatNumber(value)}`, 'Amount']}
                                                                    contentStyle={{
                                                                        backgroundColor: '#f9fafb',
                                                                        border: '1px solid #e5e7eb',
                                                                        borderRadius: '8px'
                                                                    }}
                                                                />
                                                            </PieChart>
                                                        </ResponsiveContainer>
                                                    </Card>
                                                </Col>
                                            </Row>
                                        )
                                    },
                                    {
                                        key: 'performance',
                                        label: (
                                            <span>
                                                <i className="fas fa-chart-bar mr-2"></i>
                                                Performance
                                            </span>
                                        ),
                                        children: (
                                            <Row gutter={[24, 24]}>
                                                {/* Daily Clicks Trend */}
                                                <Col xs={24} lg={12}>
                                                    <Card title="Daily Clicks Trend" className="h-full">
                                                        <ResponsiveContainer width="100%" height={300}>
                                                            <LineChart data={chartData}>
                                                                <CartesianGrid strokeDasharray="3 3" />
                                                                <XAxis dataKey="day" />
                                                                <YAxis />
                                                                <Tooltip
                                                                    formatter={(value) => [`${value}`, 'Clicks']}
                                                                    contentStyle={{
                                                                        backgroundColor: '#f9fafb',
                                                                        border: '1px solid #e5e7eb',
                                                                        borderRadius: '8px'
                                                                    }}
                                                                />
                                                                <Legend />
                                                                <Line
                                                                    type="monotone"
                                                                    dataKey="clicks"
                                                                    stroke="#3B82F6"
                                                                    strokeWidth={3}
                                                                    dot={{ fill: '#3B82F6', strokeWidth: 2, r: 6 }}
                                                                    name="Daily Clicks"
                                                                />
                                                            </LineChart>
                                                        </ResponsiveContainer>
                                                    </Card>
                                                </Col>

                                                {/* Daily Earnings Trend */}
                                                <Col xs={24} lg={12}>
                                                    <Card title="Daily Earnings Trend" className="h-full">
                                                        <ResponsiveContainer width="100%" height={300}>
                                                            <LineChart data={chartData}>
                                                                <CartesianGrid strokeDasharray="3 3" />
                                                                <XAxis dataKey="day" />
                                                                <YAxis />
                                                                <Tooltip
                                                                    formatter={(value) => [`$${value}`, 'Earnings']}
                                                                    contentStyle={{
                                                                        backgroundColor: '#f9fafb',
                                                                        border: '1px solid #e5e7eb',
                                                                        borderRadius: '8px'
                                                                    }}
                                                                />
                                                                <Legend />
                                                                <Line
                                                                    type="monotone"
                                                                    dataKey="earnings"
                                                                    stroke="#F59E0B"
                                                                    strokeWidth={3}
                                                                    dot={{ fill: '#F59E0B', strokeWidth: 2, r: 6 }}
                                                                    name="Daily Earnings"
                                                                />
                                                            </LineChart>
                                                        </ResponsiveContainer>
                                                    </Card>
                                                </Col>
                                            </Row>
                                        )
                                    },
                                    {
                                        key: 'analytics',
                                        label: (
                                            <span>
                                                <i className="fas fa-analytics mr-2"></i>
                                                Analytics
                                            </span>
                                        ),
                                        children: (
                                            <Row gutter={[24, 24]}>
                                                {/* Clicks Analysis */}
                                                <Col span={24}>
                                                    <Card title="Clicks Analysis">
                                                        <div className="space-y-4">
                                                            <div className="flex items-center justify-between p-4 bg-blue-50 rounded-lg">
                                                                <div className="flex items-center space-x-3">
                                                                    <div className="w-4 h-4 bg-blue-500 rounded"></div>
                                                                    <span className="font-medium">Total Clicks</span>
                                                                </div>
                                                                <div className="text-right">
                                                                    <div className="text-2xl font-bold text-blue-600">{formatNumber(stats.total_clicks)}</div>
                                                                    <div className="text-sm text-gray-500">100%</div>
                                                                </div>
                                                            </div>
                                                            <div className="flex items-center justify-between p-4 bg-green-50 rounded-lg">
                                                                <div className="flex items-center space-x-3">
                                                                    <div className="w-4 h-4 bg-green-500 rounded"></div>
                                                                    <span className="font-medium">Converting Clicks</span>
                                                                </div>
                                                                <div className="text-right">
                                                                    <div className="text-2xl font-bold text-green-600">{formatNumber(stats.total_conversions)}</div>
                                                                    <div className="text-sm text-gray-500">{stats.conversion_rate}%</div>
                                                                </div>
                                                            </div>
                                                            <div className="flex items-center justify-between p-4 bg-yellow-50 rounded-lg">
                                                                <div className="flex items-center space-x-3">
                                                                    <div className="w-4 h-4 bg-yellow-500 rounded"></div>
                                                                    <span className="font-medium">Revenue Per Click</span>
                                                                </div>
                                                                <div className="text-right">
                                                                    <div className="text-2xl font-bold text-yellow-600">
                                                                        ${(stats.total_earnings / Math.max(stats.total_clicks, 1)).toFixed(2)}
                                                                    </div>
                                                                    <div className="text-sm text-gray-500">
                                                                        ${formatNumber(stats.total_earnings)} total earnings
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </Card>
                                                </Col>
                                            </Row>
                                        )
                                    }
                                ]}
                            />
                        </Card>
                    </>
                )}

                {/* Loading indicator for auto-refresh */}
                {autoRefresh && (
                    <div className="fixed bottom-4 right-4">
                        <Card className="shadow-lg">
                            <div className="flex items-center space-x-2 text-green-600">
                                <Spin size="small" />
                                <span className="text-sm">Auto-refreshing stats...</span>
                            </div>
                        </Card>
                    </div>
                )}
            </div>
        </div>
    );
};

export default DashboardStats;