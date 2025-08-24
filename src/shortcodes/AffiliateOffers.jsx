import React, { useState, useEffect } from 'react';
import { Card, Button, Space, Spin, Row, Col, Input, Select, Tag, Badge, Avatar, Tooltip, Modal, message } from 'antd';
import { SearchOutlined, FilterOutlined, EyeOutlined, LinkOutlined, DollarOutlined, TrophyOutlined } from '@ant-design/icons';

const { Search } = Input;
const { Option } = Select;

const AffiliateOffers = () => {
    // Static offers data
    const staticOffers = [
        {
            id: 1,
            title: "Premium VPN Service",
            description: "Secure, fast VPN with 30-day money-back guarantee. Perfect for privacy-conscious users.",
            category: "Technology",
            commission_rate: 45,
            commission_type: "percentage",
            payout: 25.50,
            status: "active",
            merchant: "SecureVPN Pro",
            image: "https://via.placeholder.com/150x100/4F46E5/white?text=VPN",
            clicks: 1250,
            conversions: 45,
            earnings: 1147.50,
            conversion_rate: 3.6,
            epc: 0.92,
            cookie_duration: 60,
            restrictions: "No PPC on brand terms",
            featured: true,
            tags: ["recurring", "high-converting", "premium"]
        },
        {
            id: 2,
            title: "Online Course Platform",
            description: "Learn new skills with our comprehensive online courses. Over 10,000 courses available.",
            category: "Education",
            commission_rate: 30,
            commission_type: "percentage",
            payout: 89.00,
            status: "active",
            merchant: "SkillBoost Academy",
            image: "https://via.placeholder.com/150x100/059669/white?text=EDU",
            clicks: 2100,
            conversions: 78,
            earnings: 6942.00,
            conversion_rate: 3.7,
            epc: 3.31,
            cookie_duration: 45,
            restrictions: "Educational content only",
            featured: false,
            tags: ["education", "recurring", "popular"]
        },
        {
            id: 3,
            title: "Fitness Tracking Watch",
            description: "Advanced fitness tracker with heart rate monitoring, GPS, and 7-day battery life.",
            category: "Health & Fitness",
            commission_rate: 8,
            commission_type: "percentage",
            payout: 24.00,
            status: "active",
            merchant: "FitTech Pro",
            image: "https://via.placeholder.com/150x100/DC2626/white?text=FIT",
            clicks: 3200,
            conversions: 96,
            earnings: 2304.00,
            conversion_rate: 3.0,
            epc: 0.72,
            cookie_duration: 30,
            restrictions: "No misleading health claims",
            featured: true,
            tags: ["physical-product", "trending", "wearables"]
        },
        {
            id: 4,
            title: "Cloud Storage Solution",
            description: "Secure cloud storage with 2TB space, file sharing, and team collaboration features.",
            category: "Technology",
            commission_rate: 35,
            commission_type: "percentage",
            payout: 42.00,
            status: "active",
            merchant: "CloudVault",
            image: "https://via.placeholder.com/150x100/7C3AED/white?text=CLOUD",
            clicks: 850,
            conversions: 25,
            earnings: 1050.00,
            conversion_rate: 2.9,
            epc: 1.24,
            cookie_duration: 90,
            restrictions: "Business/productivity focus required",
            featured: false,
            tags: ["saas", "business", "recurring"]
        },
        {
            id: 5,
            title: "Digital Marketing Course",
            description: "Master digital marketing with our comprehensive 12-week program. Includes certifications.",
            category: "Education",
            commission_rate: 50,
            commission_type: "percentage",
            payout: 199.00,
            status: "pending",
            merchant: "MarketMaster Pro",
            image: "https://via.placeholder.com/150x100/F59E0B/white?text=MKT",
            clicks: 450,
            conversions: 8,
            earnings: 1592.00,
            conversion_rate: 1.8,
            epc: 3.54,
            cookie_duration: 60,
            restrictions: "Must have marketing-related content",
            featured: true,
            tags: ["high-ticket", "course", "certification"]
        },
        {
            id: 6,
            title: "Meal Planning App",
            description: "Personalized meal plans, grocery lists, and nutrition tracking. Family-friendly recipes.",
            category: "Health & Fitness",
            commission_rate: 25,
            commission_type: "percentage",
            payout: 9.99,
            status: "paused",
            merchant: "NutriPlan",
            image: "https://via.placeholder.com/150x100/10B981/white?text=FOOD",
            clicks: 1800,
            conversions: 120,
            earnings: 1198.80,
            conversion_rate: 6.7,
            epc: 0.67,
            cookie_duration: 30,
            restrictions: "Health/nutrition content required",
            featured: false,
            tags: ["mobile-app", "subscription", "lifestyle"]
        },
        {
            id: 7,
            title: "Web Hosting Service",
            description: "Reliable web hosting with 99.9% uptime, SSL certificates, and 24/7 support.",
            category: "Technology",
            commission_rate: 60,
            commission_type: "percentage",
            payout: 65.00,
            status: "active",
            merchant: "HostPro",
            image: "https://via.placeholder.com/150x100/16A34A/white?text=HOST",
            clicks: 920,
            conversions: 28,
            earnings: 1820.00,
            conversion_rate: 3.0,
            epc: 1.98,
            cookie_duration: 120,
            restrictions: "No spam or adult content",
            featured: true,
            tags: ["hosting", "recurring", "business"]
        },
        {
            id: 8,
            title: "Language Learning App",
            description: "Learn 15+ languages with interactive lessons, speech recognition, and cultural insights.",
            category: "Education",
            commission_rate: 40,
            commission_type: "percentage",
            payout: 39.99,
            status: "active",
            merchant: "LingoMaster",
            image: "https://via.placeholder.com/150x100/EA580C/white?text=LANG",
            clicks: 1560,
            conversions: 62,
            earnings: 2479.38,
            conversion_rate: 4.0,
            epc: 1.59,
            cookie_duration: 30,
            restrictions: "Educational content preferred",
            featured: false,
            tags: ["mobile-app", "subscription", "language"]
        }
    ];

    const [filteredOffers, setFilteredOffers] = useState(staticOffers);
    const [loading, setLoading] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [categoryFilter, setCategoryFilter] = useState('all');
    const [statusFilter, setStatusFilter] = useState('all');
    const [sortBy, setSortBy] = useState('commission_desc');
    const [selectedOffer, setSelectedOffer] = useState(null);
    const [detailsModalVisible, setDetailsModalVisible] = useState(false);
    const [generatingLink, setGeneratingLink] = useState(false);

    // Filter and sort offers when filters change
    useEffect(() => {
        filterAndSortOffers();
    }, [searchTerm, categoryFilter, statusFilter, sortBy]);

    const filterAndSortOffers = () => {
        let filtered = [...staticOffers];

        // Apply search filter
        if (searchTerm) {
            filtered = filtered.filter(offer =>
                offer.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
                offer.merchant.toLowerCase().includes(searchTerm.toLowerCase()) ||
                offer.description.toLowerCase().includes(searchTerm.toLowerCase())
            );
        }

        // Apply category filter
        if (categoryFilter !== 'all') {
            filtered = filtered.filter(offer => offer.category === categoryFilter);
        }

        // Apply status filter
        if (statusFilter !== 'all') {
            filtered = filtered.filter(offer => offer.status === statusFilter);
        }

        // Apply sorting
        filtered.sort((a, b) => {
            switch (sortBy) {
                case 'commission_desc':
                    return b.commission_rate - a.commission_rate;
                case 'commission_asc':
                    return a.commission_rate - b.commission_rate;
                case 'payout_desc':
                    return b.payout - a.payout;
                case 'payout_asc':
                    return a.payout - b.payout;
                case 'epc_desc':
                    return b.epc - a.epc;
                case 'conversion_desc':
                    return b.conversion_rate - a.conversion_rate;
                case 'title_asc':
                    return a.title.localeCompare(b.title);
                default:
                    return b.commission_rate - a.commission_rate;
            }
        });

        setFilteredOffers(filtered);
    };

    const generateAffiliateLink = (offerId) => {
        setGeneratingLink(true);
        // Simulate loading time for better UX
        setTimeout(() => {
            setGeneratingLink(false);
            // Generate demo affiliate link
            const demoLink = `https://track.affiliate-network.com/click?offer=${offerId}&affiliate=12345&ref=${Date.now()}`;
            navigator.clipboard.writeText(demoLink);
            message.success('Demo affiliate link copied to clipboard!');
        }, 1000);
    };

    const refreshOffers = () => {
        setLoading(true);
        // Simulate loading time
        setTimeout(() => {
            setLoading(false);
            message.success('Offers refreshed successfully!');
        }, 1500);
    };

    const showOfferDetails = (offer) => {
        setSelectedOffer(offer);
        setDetailsModalVisible(true);
    };

    const getStatusColor = (status) => {
        const colors = {
            'active': 'green',
            'pending': 'orange',
            'paused': 'red',
            'inactive': 'gray'
        };
        return colors[status] || 'gray';
    };

    const getStatusText = (status) => {
        const texts = {
            'active': 'Active',
            'pending': 'Pending Approval',
            'paused': 'Paused',
            'inactive': 'Inactive'
        };
        return texts[status] || 'Unknown';
    };

    const formatNumber = (num) => {
        return new Intl.NumberFormat().format(num || 0);
    };

    const categories = [...new Set(staticOffers.map(offer => offer.category))];

    return (
        <div className="p-6 bg-gray-50">
            <div className="max-w-7xl mx-auto">
                {/* Header */}
                {/*<div className="flex justify-between items-center mb-6">*/}
                {/*    <Button*/}
                {/*        type="primary"*/}
                {/*        onClick={refreshOffers}*/}
                {/*        className="bg-blue-500 hover:bg-blue-600"*/}
                {/*        loading={loading}*/}
                {/*    >*/}
                {/*        <i className="fas fa-refresh mr-2"></i>*/}
                {/*        Refresh Offers*/}
                {/*    </Button>*/}
                {/*</div>*/}

                {/* Filters */}
                <Card className="mb-6">
                    <Row gutter={[16, 16]} align="middle">
                        <Col xs={24} sm={12} md={8}>
                            <Search
                                placeholder="Search offers, merchants..."
                                allowClear
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                prefix={<SearchOutlined />}
                            />
                        </Col>
                        <Col xs={12} sm={6} md={4}>
                            <Select
                                value={categoryFilter}
                                onChange={setCategoryFilter}
                                style={{ width: '100%' }}
                                placeholder="Category"
                            >
                                <Option value="all">All Categories</Option>
                                {categories.map(category => (
                                    <Option key={category} value={category}>{category}</Option>
                                ))}
                            </Select>
                        </Col>
                        <Col xs={12} sm={6} md={4}>
                            <Select
                                value={statusFilter}
                                onChange={setStatusFilter}
                                style={{ width: '100%' }}
                                placeholder="Status"
                            >
                                <Option value="all">All Status</Option>
                                <Option value="active">Active</Option>
                                <Option value="pending">Pending</Option>
                                <Option value="paused">Paused</Option>
                                <Option value="inactive">Inactive</Option>
                            </Select>
                        </Col>
                        <Col xs={24} sm={12} md={8}>
                            <Select
                                value={sortBy}
                                onChange={setSortBy}
                                style={{ width: '100%' }}
                                placeholder="Sort by"
                                prefix={<FilterOutlined />}
                            >
                                <Option value="commission_desc">Commission % (High to Low)</Option>
                                <Option value="commission_asc">Commission % (Low to High)</Option>
                                <Option value="payout_desc">Payout (High to Low)</Option>
                                <Option value="payout_asc">Payout (Low to High)</Option>
                                <Option value="epc_desc">EPC (High to Low)</Option>
                                <Option value="conversion_desc">Conversion Rate (High to Low)</Option>
                                <Option value="title_asc">Title (A to Z)</Option>
                            </Select>
                        </Col>
                    </Row>
                </Card>

                {/* Offers Grid */}
                {loading ? (
                    <div className="text-center py-8">
                        <Spin size="large" />
                        <div className="mt-4 text-gray-600">Loading offers...</div>
                    </div>
                ) : (
                    <Row gutter={[24, 24]}>
                        {filteredOffers.map(offer => (
                            <Col xs={24} sm={12} lg={8} xl={6} key={offer.id}>
                                <Card
                                    className="h-full hover:shadow-lg transition-shadow relative"
                                    cover={
                                        <div className="relative">
                                            <img
                                                alt={offer.title}
                                                src={offer.image}
                                                className="h-32 w-full object-cover"
                                            />
                                            {offer.featured && (
                                                <div className="absolute top-2 right-2">
                                                    <Badge.Ribbon text="Featured" color="gold">
                                                        <div></div>
                                                    </Badge.Ribbon>
                                                </div>
                                            )}
                                            <div className="absolute top-2 left-2">
                                                <Tag color={getStatusColor(offer.status)}>
                                                    {getStatusText(offer.status)}
                                                </Tag>
                                            </div>
                                        </div>
                                    }
                                    actions={[
                                        <Tooltip title="View Details">
                                            <Button
                                                type="link"
                                                icon={<EyeOutlined />}
                                                onClick={() => showOfferDetails(offer)}
                                            />
                                        </Tooltip>,
                                        <Tooltip title="Generate Link">
                                            <Button
                                                type="link"
                                                icon={<LinkOutlined />}
                                                loading={generatingLink}
                                                onClick={() => generateAffiliateLink(offer.id)}
                                                disabled={offer.status !== 'active'}
                                            />
                                        </Tooltip>,
                                        <Tooltip title={`$${offer.payout} payout`}>
                                            <Button
                                                type="link"
                                                icon={<DollarOutlined />}
                                            />
                                        </Tooltip>
                                    ]}
                                >
                                    <Card.Meta
                                        avatar={
                                            <Avatar style={{ backgroundColor: '#1890ff' }}>
                                                {offer.merchant.charAt(0)}
                                            </Avatar>
                                        }
                                        title={
                                            <div className="truncate" title={offer.title}>
                                                {offer.title}
                                            </div>
                                        }
                                        description={
                                            <div className="text-xs text-gray-500 truncate" title={offer.merchant}>
                                                {offer.merchant}
                                            </div>
                                        }
                                    />

                                    <div className="mt-3 space-y-2">
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-gray-600">Commission:</span>
                                            <span className="font-semibold text-green-600">
                                                {offer.commission_rate}%
                                            </span>
                                        </div>

                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-gray-600">Payout:</span>
                                            <span className="font-semibold text-blue-600">
                                                ${offer.payout}
                                            </span>
                                        </div>

                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-gray-600">EPC:</span>
                                            <span className="font-medium">
                                                ${offer.epc}
                                            </span>
                                        </div>

                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-gray-600">Conv. Rate:</span>
                                            <span className="font-medium">
                                                {offer.conversion_rate}%
                                            </span>
                                        </div>

                                        {/* Tags */}
                                        <div className="pt-2">
                                            <div className="flex flex-wrap gap-1">
                                                {offer.tags.slice(0, 2).map((tag, index) => (
                                                    <Tag key={index} size="small" color="blue">
                                                        {tag}
                                                    </Tag>
                                                ))}
                                                {offer.tags.length > 2 && (
                                                    <Tag size="small" color="default">
                                                        +{offer.tags.length - 2}
                                                    </Tag>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </Card>
                            </Col>
                        ))}
                    </Row>
                )}

                {filteredOffers.length === 0 && !loading && (
                    <div className="text-center py-8">
                        <div className="text-gray-500">No offers found matching your criteria.</div>
                        <Button
                            type="link"
                            onClick={() => {
                                setSearchTerm('');
                                setCategoryFilter('all');
                                setStatusFilter('all');
                            }}
                        >
                            Clear Filters
                        </Button>
                    </div>
                )}

                {/* Offer Details Modal */}
                <Modal
                    title={selectedOffer?.title}
                    open={detailsModalVisible}
                    onCancel={() => setDetailsModalVisible(false)}
                    width={800}
                    footer={[
                        <Button key="close" onClick={() => setDetailsModalVisible(false)}>
                            Close
                        </Button>,
                        <Button
                            key="generate"
                            type="primary"
                            icon={<LinkOutlined />}
                            loading={generatingLink}
                            onClick={() => generateAffiliateLink(selectedOffer?.id)}
                            disabled={selectedOffer?.status !== 'active'}
                        >
                            Generate Link
                        </Button>
                    ]}
                >
                    {selectedOffer && (
                        <div className="space-y-4">
                            <Row gutter={[24, 16]}>
                                <Col span={8}>
                                    <img
                                        src={selectedOffer.image}
                                        alt={selectedOffer.title}
                                        className="w-full rounded-lg"
                                    />
                                </Col>
                                <Col span={16}>
                                    <div className="space-y-3">
                                        <div>
                                            <strong>Merchant:</strong> {selectedOffer.merchant}
                                        </div>
                                        <div>
                                            <strong>Category:</strong> <Tag color="blue">{selectedOffer.category}</Tag>
                                        </div>
                                        <div>
                                            <strong>Status:</strong> <Tag color={getStatusColor(selectedOffer.status)}>
                                            {getStatusText(selectedOffer.status)}
                                        </Tag>
                                        </div>
                                        <div>
                                            <strong>Description:</strong>
                                            <p className="mt-1 text-gray-700">{selectedOffer.description}</p>
                                        </div>
                                    </div>
                                </Col>
                            </Row>

                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
                                <div className="text-center p-3 bg-green-50 rounded-lg">
                                    <div className="text-2xl font-bold text-green-600">{selectedOffer.commission_rate}%</div>
                                    <div className="text-sm text-gray-600">Commission</div>
                                </div>
                                <div className="text-center p-3 bg-blue-50 rounded-lg">
                                    <div className="text-2xl font-bold text-blue-600">${selectedOffer.payout}</div>
                                    <div className="text-sm text-gray-600">Payout</div>
                                </div>
                                <div className="text-center p-3 bg-purple-50 rounded-lg">
                                    <div className="text-2xl font-bold text-purple-600">${selectedOffer.epc}</div>
                                    <div className="text-sm text-gray-600">EPC</div>
                                </div>
                                <div className="text-center p-3 bg-orange-50 rounded-lg">
                                    <div className="text-2xl font-bold text-orange-600">{selectedOffer.conversion_rate}%</div>
                                    <div className="text-sm text-gray-600">Conv. Rate</div>
                                </div>
                            </div>

                            <Row gutter={[24, 16]} className="mt-6">
                                <Col span={12}>
                                    <div className="space-y-2">
                                        <div><strong>Total Clicks:</strong> {formatNumber(selectedOffer.clicks)}</div>
                                        <div><strong>Total Conversions:</strong> {formatNumber(selectedOffer.conversions)}</div>
                                        <div><strong>Total Earnings:</strong> ${formatNumber(selectedOffer.earnings)}</div>
                                    </div>
                                </Col>
                                <Col span={12}>
                                    <div className="space-y-2">
                                        <div><strong>Cookie Duration:</strong> {selectedOffer.cookie_duration} days</div>
                                        <div><strong>Restrictions:</strong> {selectedOffer.restrictions}</div>
                                    </div>
                                </Col>
                            </Row>

                            <div className="mt-4">
                                <strong>Tags:</strong>
                                <div className="mt-2">
                                    {selectedOffer.tags.map((tag, index) => (
                                        <Tag key={index} color="blue" className="mb-1">
                                            {tag}
                                        </Tag>
                                    ))}
                                </div>
                            </div>
                        </div>
                    )}
                </Modal>
            </div>
        </div>
    );
};

export default AffiliateOffers;