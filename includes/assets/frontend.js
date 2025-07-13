jQuery(document).ready(function($) {

    // Test AJAX connection
    function testAjax() {
        $.ajax({
            url: affiliate_bloom_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'affiliate_bloom_test',
                nonce: affiliate_bloom_ajax.nonce
            },
            success: function(response) {
                console.log('AJAX Test:', response);
            },
            error: function(xhr, status, error) {
                console.error('AJAX Test Error:', error);
            }
        });
    }

    // Test on page load
    testAjax();

    // Load dashboard stats on page load
    if ($('.affiliate-bloom-dashboard').length) {
        loadDashboardStats();
        loadAffiliateLinks();
    }

    // Generate affiliate link from dashboard
    $('#generate-affiliate-link').on('click', function(e) {
        e.preventDefault();

        const productUrl = $('#product-url').val().trim();
        if (!productUrl) {
            alert('Please enter a product URL');
            return;
        }

        const button = $(this);
        const originalText = button.text();
        button.text('Generating...').prop('disabled', true);

        $.ajax({
            url: affiliate_bloom_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_affiliate_link',
                product_url: productUrl,
                nonce: affiliate_bloom_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#generated-link').val(response.data.affiliate_url);
                    $('#generated-link-result').slideDown();
                    $('#product-url').val('');

                    // Refresh links
                    loadAffiliateLinks();

                    // Show success message
                    showNotification('Affiliate link generated successfully!', 'success');
                } else {
                    showNotification('Error: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                showNotification('Failed to generate link. Please try again.', 'error');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });

    // Generate affiliate link from shortcode
    $('.generate-link-btn').on('click', function(e) {
        e.preventDefault();

        const button = $(this);
        const productId = button.data('product-id');
        const originalText = button.text();
        const resultDiv = button.siblings('.generated-link-result');

        button.text('Generating...').prop('disabled', true);

        $.ajax({
            url: affiliate_bloom_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_affiliate_link',
                product_id: productId,
                nonce: affiliate_bloom_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultDiv.find('.generated-link').val(response.data.affiliate_url);
                    resultDiv.slideDown();
                    showNotification('Affiliate link generated successfully!', 'success');
                } else {
                    showNotification('Error: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                showNotification('Failed to generate link. Please try again.', 'error');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });

    // Copy link to clipboard
    $(document).on('click', '#copy-link, .copy-link-btn', function(e) {
        e.preventDefault();

        const linkInput = $(this).siblings('input[type="text"]').length ?
            $(this).siblings('input[type="text"]') :
            $(this).closest('.link-result').find('input[type="text"]');

        if (linkInput.length) {
            linkInput.select();
            document.execCommand('copy');

            const button = $(this);
            const originalText = button.text();
            button.text('Copied!');

            setTimeout(function() {
                button.text(originalText);
            }, 2000);

            showNotification('Link copied to clipboard!', 'success');
        }
    });

    // Refresh links
    $('#refresh-links').on('click', function(e) {
        e.preventDefault();
        loadAffiliateLinks();
    });

    // Load dashboard stats
    function loadDashboardStats() {
        $.ajax({
            url: affiliate_bloom_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_affiliate_stats',
                nonce: affiliate_bloom_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    const stats = response.data.stats;

                    $('#total-clicks').text(stats.total_clicks);
                    $('#total-conversions').text(stats.total_conversions);
                    $('#conversion-rate').text(stats.conversion_rate + '%');
                    $('#total-earnings').text('$' + parseFloat(stats.total_earnings).toFixed(2));
                }
            },
            error: function(xhr, status, error) {
                console.error('Stats Error:', error);
            }
        });
    }

    // Load affiliate links
    function loadAffiliateLinks(page = 1) {
        const container = $('#links-container');

        // Show loading
        container.html('<div class="loading-spinner"><div class="spinner"></div><p>Loading your links...</p></div>');

        $.ajax({
            url: affiliate_bloom_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_user_affiliate_links',
                page: page,
                nonce: affiliate_bloom_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;

                    if (data.links.length === 0) {
                        container.html('<div class="no-links"><p>No affiliate links found. Generate your first link above!</p></div>');
                        return;
                    }

                    let html = '<div class="links-table">';
                    html += '<div class="links-header">';
                    html += '<div class="link-col">Link</div>';
                    html += '<div class="stats-col">Clicks</div>';
                    html += '<div class="stats-col">Conversions</div>';
                    html += '<div class="stats-col">Rate</div>';
                    html += '<div class="date-col">Created</div>';
                    html += '<div class="actions-col">Actions</div>';
                    html += '</div>';

                    data.links.forEach(function(link) {
                        html += '<div class="link-row">';
                        html += '<div class="link-col">';
                        html += '<div class="link-url">' + truncateUrl(link.product_url || 'Product ID: ' + link.product_id) + '</div>';
                        html += '<div class="affiliate-url">' + link.affiliate_url + '</div>';
                        html += '</div>';
                        html += '<div class="stats-col">' + link.clicks + '</div>';
                        html += '<div class="stats-col">' + link.conversions + '</div>';
                        html += '<div class="stats-col">' + link.conversion_rate + '%</div>';
                        html += '<div class="date-col">' + formatDate(link.created_date) + '</div>';
                        html += '<div class="actions-col">';
                        html += '<button class="copy-link-action" data-url="' + link.affiliate_url + '">Copy</button>';
                        html += '<button class="delete-link-action" data-id="' + link.id + '">Delete</button>';
                        html += '</div>';
                        html += '</div>';
                    });

                    html += '</div>';
                    container.html(html);

                    // Update pagination
                    updatePagination(data.current_page, data.total_pages);
                } else {
                    container.html('<div class="error"><p>Error loading links: ' + response.data + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Links Error:', error);
                container.html('<div class="error"><p>Failed to load links. Please try again.</p></div>');
            }
        });
    }

    // Copy link from table
    $(document).on('click', '.copy-link-action', function(e) {
        e.preventDefault();

        const url = $(this).data('url');
        const button = $(this);
        const originalText = button.text();

        // Create temporary input
        const temp = $('<input>');
        $('body').append(temp);
        temp.val(url).select();
        document.execCommand('copy');
        temp.remove();

        button.text('Copied!');
        setTimeout(function() {
            button.text(originalText);
        }, 2000);

        showNotification('Link copied to clipboard!', 'success');
    });

    // Delete link
    $(document).on('click', '.delete-link-action', function(e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to delete this link?')) {
            return;
        }

        const linkId = $(this).data('id');
        const button = $(this);
        const originalText = button.text();

        button.text('Deleting...').prop('disabled', true);

        $.ajax({
            url: affiliate_bloom_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'delete_affiliate_link',
                link_id: linkId,
                nonce: affiliate_bloom_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    loadAffiliateLinks();
                    showNotification('Link deleted successfully!', 'success');
                } else {
                    showNotification('Error: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Delete Error:', error);
                showNotification('Failed to delete link. Please try again.', 'error');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });

    // Pagination
    function updatePagination(currentPage, totalPages) {
        const container = $('#links-pagination');

        if (totalPages <= 1) {
            container.empty();
            return;
        }

        let html = '<div class="pagination">';

        // Previous button
        if (currentPage > 1) {
            html += '<button class="page-btn" data-page="' + (currentPage - 1) + '">&laquo; Previous</button>';
        }

        // Page numbers
        for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
            const activeClass = i === currentPage ? ' active' : '';
            html += '<button class="page-btn' + activeClass + '" data-page="' + i + '">' + i + '</button>';
        }

        // Next button
        if (currentPage < totalPages) {
            html += '<button class="page-btn" data-page="' + (currentPage + 1) + '">Next &raquo;</button>';
        }

        html += '</div>';
        container.html(html);
    }

    // Handle pagination clicks
    $(document).on('click', '.page-btn', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        loadAffiliateLinks(page);
    });

    // Helper functions
    function truncateUrl(url, maxLength = 50) {
        if (url.length <= maxLength) return url;
        return url.substring(0, maxLength) + '...';
    }

    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString();
    }

    function showNotification(message, type = 'info') {
        // Remove existing notifications
        $('.affiliate-bloom-notification').remove();

        const notification = $('<div class="affiliate-bloom-notification ' + type + '">' + message + '</div>');
        $('body').append(notification);

        // Show notification
        setTimeout(function() {
            notification.addClass('show');
        }, 100);

        // Hide after 3 seconds
        setTimeout(function() {
            notification.removeClass('show');
            setTimeout(function() {
                notification.remove();
            }, 300);
        }, 3000);
    }
});