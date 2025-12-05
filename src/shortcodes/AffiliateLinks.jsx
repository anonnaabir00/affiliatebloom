import React, { useState, useEffect } from 'react';
import { Button, Input, Card, Typography, Space, message, Spin, Pagination, Modal, Badge, Tooltip } from 'antd';
import {fetchData} from "../services/fetchData.js";
import {createData} from "../services/createData.js";

const { Title, Text } = Typography;

const AffiliateLinks = () => {
    const [newUrl, setNewUrl] = useState('');
    const [linkName, setLinkName] = useState('');
    const [affiliateLinks, setAffiliateLinks] = useState([]);
    const [loading, setLoading] = useState(false);
    const [generating, setGenerating] = useState(false);
    const [currentPage, setCurrentPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);
    const [totalLinks, setTotalLinks] = useState(0);
    const [urlError, setUrlError] = useState('');

    const validateUrl = (url) => {
        const allowedDomain = 'cloudwaysapps.com'; // Change this to your specific domain

        try {
            const urlObj = new URL(url);
            const hostname = urlObj.hostname.replace('www.', '');

            if (hostname !== allowedDomain) {
                setUrlError(`Only URLs from ${allowedDomain} are allowed`);
                return false;
            }

            setUrlError('');
            return true;
        } catch (e) {
            setUrlError('Please enter a valid URL');
            return false;
        }
    };

    // Load initial data
    useEffect(() => {
        loadAffiliateLinks(1);
    }, []);

    const loadAffiliateLinks = (page = 1) => {
        setLoading(true);
        fetchData('affiliate_bloom_get_user_links', (response) => {
            setLoading(false);
            if (response.success) {
                setAffiliateLinks(response.data.links || []);
                setCurrentPage(response.data.current_page || 1);
                setTotalPages(response.data.total_pages || 1);
                setTotalLinks(response.data.total_links || 0);
            } else {
                message.error(response.data?.message || 'Failed to load affiliate links');
            }
        }, {
            nonce: affiliateBloom.nonce,
            page: page,
            per_page: 10
        });
    };

    const handleGenerateLink = async () => {
        if (!newUrl.trim()) {
            message.warning('Please enter a page URL');
            return;
        }

        if (!validateUrl(newUrl.trim())) {
            return; // Stop if validation fails
        }

        setGenerating(true);

        try {
            const response = await createData('affiliate_bloom_generate_link', {
                nonce: affiliateBloom.nonce,
                product_url: newUrl.trim(),
                link_name: linkName.trim() || `Link for ${newUrl.trim()}`
            });

            if (response.success) {
                message.success(response.data.message || 'Affiliate link generated successfully!');
                setNewUrl('');
                setLinkName('');
                // Reload links
                loadAffiliateLinks(currentPage);
            }
        } catch (error) {
            message.error(error.data?.message || 'Failed to generate affiliate link');
        } finally {
            setGenerating(false);
        }
    };

    const handleDeleteLink = (linkId, linkName) => {
        Modal.confirm({
            title: 'Delete Affiliate Link',
            content: `Are you sure you want to delete "${linkName}"? This action cannot be undone.`,
            okText: 'Yes, Delete',
            cancelText: 'Cancel',
            okType: 'danger',
            onOk: async () => {
                try {
                    const response = await createData('affiliate_bloom_delete_link', {
                        nonce: affiliateBloom.nonce,
                        link_id: linkId
                    });

                    if (response.success) {
                        message.success('Link deleted successfully');
                        loadAffiliateLinks(currentPage);
                    }
                } catch (error) {
                    message.error(error.data?.message || 'Failed to delete link');
                }
            }
        });
    };

    const handleCopyUrl = (url) => {
        navigator.clipboard.writeText(url).then(() => {
            message.success('URL copied to clipboard!');
        }).catch(() => {
            message.error('Failed to copy URL');
        });
    };

    const handleOpenUrl = (url) => {
        window.open(url, '_blank');
    };

    const handlePageChange = (page) => {
        setCurrentPage(page);
        loadAffiliateLinks(page);
    };

    const handleRefresh = () => {
        loadAffiliateLinks(currentPage);
    };

    // Helper function to get click status color
    const getClickStatusColor = (clicks) => {
        if (clicks === 0) return 'default';
        if (clicks < 10) return 'blue';
        if (clicks < 50) return 'green';
        if (clicks < 100) return 'orange';
        return 'red';
    };

    // Helper function to format numbers
    const formatNumber = (num) => {
        return new Intl.NumberFormat().format(num || 0);
    };

    return (
        <div className="p-6 bg-gray-50 min-h-screen">
            <div className="max-w-6xl mx-auto">
                {/* Generate New URL Section */}
                <Card className="mb-8 shadow-sm">
                    <Title level={3} className="mb-6">
                        Generate New Affiliate URL
                    </Title>
                    <div className="space-y-4">
                        <div>
                            <Text className="block mb-2 text-gray-700">Page URL</Text>
                            <Input
                                placeholder={`Enter page URL (e.g., https://example.com/products/premium)`}
                                value={newUrl}
                                onChange={(e) => {
                                    setNewUrl(e.target.value);
                                    if (e.target.value.trim()) {
                                        validateUrl(e.target.value.trim());
                                    } else {
                                        setUrlError('');
                                    }
                                }}
                                size="large"
                                className="rounded-lg"
                                status={urlError ? 'error' : ''}
                            />
                            {urlError && (
                                <Text type="danger" className="text-sm mt-1 block">
                                    {urlError}
                                </Text>
                            )}
                        </div>
                        <div>
                            <Text className="block mb-2 text-gray-700">Link Name (Optional)</Text>
                            <Input
                                placeholder="Enter a name for this link"
                                value={linkName}
                                onChange={(e) => setLinkName(e.target.value)}
                                size="large"
                                className="rounded-lg"
                            />
                        </div>
                        <Button
                            type="primary"
                            size="large"
                            onClick={handleGenerateLink}
                            loading={generating}
                            className="bg-blue-500 hover:bg-blue-600 px-8"
                        >
                            {generating ? 'Generating...' : 'Generate Link'}
                        </Button>
                    </div>
                </Card>

                {/* Affiliate Links List */}
                <div className="space-y-6 mt-6">
                    {loading && affiliateLinks.length === 0 ? (
                        <div className="text-center py-8">
                            <Spin size="large" />
                            <div className="mt-4 text-gray-600">Loading affiliate links...</div>
                        </div>
                    ) : affiliateLinks.length === 0 ? (
                        <Card className="text-center py-12">
                            <div className="text-gray-500 mb-4">
                                <i className="fas fa-link text-4xl mb-4"></i>
                                <div>No affiliate links found</div>
                                <div className="text-sm">Generate your first affiliate link above</div>
                            </div>
                        </Card>
                    ) : (
                        affiliateLinks.map((link) => (
                            <Card key={link.id} className="shadow-sm hover:shadow-md transition-shadow">
                                <div className="space-y-4">
                                    <div className="flex justify-between items-start">
                                        <div className="flex items-center space-x-3">
                                            <Title level={4} className="mb-1">
                                                {link.name || `Link #${link.id}`}
                                            </Title>
                                            {/* Click Badge */}
                                            <Tooltip title={`${formatNumber(link.clicks)} total clicks`}>
                                                <Badge
                                                    count={formatNumber(link.clicks)}
                                                    color={getClickStatusColor(link.clicks)}
                                                    overflowCount={9999}
                                                />
                                            </Tooltip>
                                            {/* Conversion Badge if any */}
                                            {link.conversions > 0 && (
                                                <Tooltip title={`${link.conversions} conversions`}>
                                                    <Badge
                                                        count={`${link.conversions} conv`}
                                                        color="green"
                                                    />
                                                </Tooltip>
                                            )}
                                        </div>
                                        <Button
                                            type="text"
                                            danger
                                            onClick={() => handleDeleteLink(link.id, link.name || `Link #${link.id}`)}
                                            className="text-red-500 hover:text-red-700"
                                        >
                                            <i className="fas fa-trash"></i>
                                        </Button>
                                    </div>

                                    {/* Original URL */}
                                    <div>
                                        <Text className="block text-gray-500 text-sm mb-1">Original URL:</Text>
                                        <Text className="text-gray-700 text-sm">
                                            {link.product_url}
                                        </Text>
                                    </div>

                                    {/* Affiliate URL Display */}
                                    <div className="bg-gray-100 p-4 rounded-lg">
                                        <div className="flex justify-between items-center mb-2">
                                            <Text className="text-gray-500 text-sm">Affiliate URL:</Text>
                                            <Space>
                                                <Button
                                                    type="text"
                                                    size="small"
                                                    onClick={() => handleCopyUrl(link.affiliate_url)}
                                                    className="text-gray-500 hover:text-blue-500"
                                                    title="Copy URL"
                                                >
                                                    <i className="fas fa-copy"></i>
                                                </Button>
                                                <Button
                                                    type="text"
                                                    size="small"
                                                    onClick={() => handleOpenUrl(link.affiliate_url)}
                                                    className="text-gray-500 hover:text-blue-500"
                                                    title="Test Link (Opens in new tab)"
                                                >
                                                    <i className="fas fa-external-link-alt"></i>
                                                </Button>
                                            </Space>
                                        </div>
                                        <Text
                                            code
                                            className="text-blue-600 text-sm block"
                                            style={{ wordBreak: 'break-all' }}
                                        >
                                            {link.affiliate_url}
                                        </Text>
                                    </div>

                                    {/* Individual Link Stats */}
                                    <div className="border-t pt-4">
                                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                            <div className="bg-blue-50 p-3 rounded-lg text-center">
                                                <div className="text-blue-600 text-xl font-bold">
                                                    {formatNumber(link.clicks || 0)}
                                                </div>
                                                <div className="text-gray-500 text-xs">Clicks</div>
                                            </div>
                                            <div className="bg-green-50 p-3 rounded-lg text-center">
                                                <div className="text-green-600 text-xl font-bold">
                                                    {formatNumber(link.conversions || 0)}
                                                </div>
                                                <div className="text-gray-500 text-xs">Conversions</div>
                                            </div>
                                            <div className="bg-purple-50 p-3 rounded-lg text-center">
                                                <div className="text-purple-600 text-xl font-bold">
                                                    {link.conversion_rate || 0}%
                                                </div>
                                                <div className="text-gray-500 text-xs">Rate</div>
                                            </div>
                                            <div className="bg-gray-50 p-3 rounded-lg text-center">
                                                <div className="text-gray-600 text-sm font-bold">
                                                    {new Date(link.created_date).toLocaleDateString()}
                                                </div>
                                                <div className="text-gray-500 text-xs">Created</div>
                                            </div>
                                        </div>

                                        {/* Click Performance Indicator */}
                                        {link.clicks > 0 && (
                                            <div className="mt-3">
                                                <div className="flex justify-between text-xs text-gray-500 mb-1">
                                                    <span>Performance</span>
                                                    <span>{link.clicks} clicks</span>
                                                </div>
                                                <div className="w-full bg-gray-200 rounded-full h-2">
                                                    <div
                                                        className={`h-2 rounded-full ${
                                                            link.clicks < 10 ? 'bg-red-400' :
                                                                link.clicks < 50 ? 'bg-yellow-400' :
                                                                    link.clicks < 100 ? 'bg-blue-400' : 'bg-green-400'
                                                        }`}
                                                        style={{
                                                            width: `${Math.min((link.clicks / 100) * 100, 100)}%`
                                                        }}
                                                    ></div>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </Card>
                        ))
                    )}
                </div>

                {/* Pagination */}
                {totalPages > 1 && (
                    <div className="text-center mt-8">
                        <Pagination
                            current={currentPage}
                            total={totalLinks}
                            pageSize={10}
                            onChange={handlePageChange}
                            showSizeChanger={false}
                            showQuickJumper
                            showTotal={(total, range) =>
                                `${range[0]}-${range[1]} of ${total} links`
                            }
                        />
                    </div>
                )}
            </div>
        </div>
    );
};

export default AffiliateLinks;