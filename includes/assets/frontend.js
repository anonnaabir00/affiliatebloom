jQuery(document).ready(function($) {

    // Global variables
    let currentPage = 1;
    let performanceChart = null;

    // Generate affiliate link
    $(document).on('click', '#generate-affiliate-link', function(e) {
        e.preventDefault();

        if (!affiliateBloom.is_affiliate) {
            showNotification('You are not an approved affiliate.', 'error');
            return;
        }

        var productId = $(this).data('product-id');
        var button = $(this);
        var originalText = button.text();

        button.prop('disabled', true).text(affiliateBloom.messages.generating);

        $.ajax({
            url: affiliateBloom.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_affiliate_link',
                product_id: productId,
                nonce: affiliateBloom.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#affiliate-url').val(response.data.affiliate_url);
                    $('#affiliate-result').slideDown();
                    button.text(affiliateBloom.messages.generated).addClass('success');

                    // Update share buttons
                    updateShareButtons(response.data.affiliate_url);

                    showNotification(response.data.message, 'success');
                } else {
                    showNotification('Error: ' + response.data, 'error');
                    button.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                showNotification(affiliateBloom.messages.error, 'error');
                button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Copy affiliate link to clipboard
    $(document).on('click', '#copy-affiliate-link, .copy-link-btn', function(e) {
        e.preventDefault();

        var url;
        if ($(this).hasClass('copy-link-btn')) {
            url = $(this).data('url');
        } else {
            url = $('#affiliate-url').val();
        }

        if (!url) {
            showNotification('No URL to copy', 'error');
            return;
        }

        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function() {
                showCopySuccess($(e.target));
            }).catch(function() {
                fallbackCopyTextToClipboard(url, $(e.target));
            });
        } else {
            fallbackCopyTextToClipboard(url, $(e.target));
        }
    });

    // Fallback copy function for older browsers
    function fallbackCopyTextToClipboard(text, button) {
        var textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.top = "0";
        textArea.style.left = "0";
        textArea.style.position = "fixed";

        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            var successful = document.execCommand('copy');
            if (successful) {
                showCopySuccess(button);
            } else {
                showNotification('Failed to copy link', 'error');
            }
        } catch (err) {
            showNotification('Failed to copy link', 'error');
        }

        document.body.removeChild(textArea);
    }

    function showCopySuccess(button) {
        var originalText = button.text();
        var originalTitle = button.attr('title');

        button.text(affiliateBloom.messages.copied)
            .addClass('success')
            .attr('title', affiliateBloom.messages.copied);

        setTimeout(function() {
            button.text(originalText)
                .removeClass('success')
                .attr('title', originalTitle);
        }, 2000);
    }

    // Social sharing
    $(document).on('click', '.affiliate-share-btn', function(e) {
        e.preventDefault();

        var platform = $(this).data('platform');
        var url = $('#affiliate-url').val();
        var productTitle = $('h1.product_title').text() || 'Check out this amazing product!';

        if (!url) {
            showNotification('Please generate an affiliate link first', 'error');
            return;
        }

        var shareUrl = '';
        var windowOptions = 'width=600,height=400,scrollbars=yes,resizable=yes,toolbar=no,menubar=no,location=no,directories=no,status=no';

        switch(platform) {
            case 'facebook':
                shareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url);
                break;
            case 'twitter':
                shareUrl = 'https://twitter.com/intent/tweet?url=' + encodeURIComponent(url) + '&text=' + encodeURIComponent(productTitle);
                break;
            case 'whatsapp':
                shareUrl = 'https://wa.me/?text=' + encodeURIComponent(productTitle + ' ' + url);
                break;
            case 'linkedin':
                shareUrl = 'https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(url);
                break;
            case 'telegram':
                shareUrl = 'https://t.me/share/url?url=' + encodeURIComponent(url) + '&text=' + encodeURIComponent(productTitle);
                break;
        }

        if (shareUrl) {
            window.open(shareUrl, 'share', windowOptions);
        }
    });

    function updateShareButtons(url) {
        $('.affiliate-share-btn').each(function() {
            $(this).data('url', url);
        });
    }

    // Dashboard tabs
    $(document).on('click', '.affiliate-tab-button', function(e) {
        e.preventDefault();

        var tabId = $(this).data('tab');

        // Update active states
        $('.affiliate-tab-button').removeClass('active');
        $(this).addClass('active');

        $('.affiliate-tab-panel').removeClass('active');
        $('#tab-' + tabId).addClass('active');

        // Load content for specific tabs
        if (tabId === 'performance' && !performanceChart) {
            loadPerformanceChart();
        } else if (tabId === 'links') {
            // Auto-load links when tab is opened
            if ($('#affiliate-links-container').children().length <= 1) {
                loadAffiliateLinks(1);
            }
        }
    });

    // Load affiliate links
    $(document).on('click', '#load-affiliate-links', function(e) {
        e.preventDefault();
        loadAffiliateLinks(1);
    });

    // Pagination for affiliate links
    $(document).on('click', '.affiliate-pagination-btn', function(e) {
        e.preventDefault();
        var page = $(this).data('page');
        loadAffiliateLinks(page);
    });

    function loadAffiliateLinks(page) {
        var container = $('#affiliate-links-container');
        var button = $('#load-affiliate-links');

        button.prop('disabled', true).text(affiliateBloom.messages.loading);
        container.addClass('loading');

        $.ajax({
            url: affiliateBloom.ajax_url,
            type: 'POST',
            data: {
                action: 'get_user_affiliate_links',
                page: page,
                nonce: affiliateBloom.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayAffiliateLinks(response.data.links);
                    displayPagination(response.data.current_page, response.data.total_pages);
                    currentPage = response.data.current_page;

                    if (response.data.links.length === 0) {
                        container.html('<div class="affiliate-no-links"><p>' + affiliateBloom.messages.no_links + '</p></div>');
                    }
                } else {
                    showNotification('Error loading links: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotification(affiliateBloom.messages.error, 'error');
            },
            complete: function() {
                button.prop('disabled', false).text('Refresh Links');
                container.removeClass('loading');
            }
        });
    }

    function displayAffiliateLinks(links) {
        var container = $('#affiliate-links-container');
        var html = '';

        if (links.length > 0) {
            html += '<div class="affiliate-links-grid">';

            links.forEach(function(link) {
                html += `
                  <div class="affiliate-link-card" data-link-id="${link.id}">
                      <div class="affiliate-link-header">
                          <h4>${escapeHtml(link.product_name)}</h4>
                          <div class="affiliate-link-actions">
                              <button class="copy-link-btn" data-url="${link.affiliate_url}" title="Copy Link">📋</button>
                              <button class="delete-link-btn" data-link-id="${link.id}" title="Delete Link">🗑️</button>
                          </div>
                      </div>
                      
                      <div class="affiliate-link-url">
                          <input type="text" value="${link.affiliate_url}" readonly>
                      </div>
                      
                      <div class="affiliate-link-stats">
                          <div class="stat-item">
                              <span class="stat-label">Clicks:</span>
                              <span class="stat-value">${link.clicks}</span>
                          </div>
                          <div class="stat-item">
                              <span class="stat-label">Conversions:</span>
                              <span class="stat-value">${link.conversions}</span>
                          </div>
                          <div class="stat-item">
                              <span class="stat-label">Rate:</span>
                              <span class="stat-value">${link.conversion_rate}%</span>
                          </div>
                      </div>
                      
                      <div class="affiliate-link-date">
                          Created: ${formatDate(link.created_date)}
                      </div>
                  </div>
              `;
            });

            html += '</div>';
        } else {
            html = '<div class="affiliate-no-links"><p>' + affiliateBloom.messages.no_links + '</p></div>';
        }

        container.html(html);
    }

    function displayPagination(currentPage, totalPages) {
        var container = $('#affiliate-links-pagination');
        var html = '';

        if (totalPages > 1) {
            html += '<div class="affiliate-pagination">';

            // Previous button
            if (currentPage > 1) {
                html += `<button class="affiliate-pagination-btn" data-page="${currentPage - 1}">← Previous</button>`;
            }

            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                if (i === currentPage) {
                    html += `<span class="affiliate-pagination-current">${i}</span>`;
                } else {
                    html += `<button class="affiliate-pagination-btn" data-page="${i}">${i}</button>`;
                }
            }

            // Next button
            if (currentPage < totalPages) {
                html += `<button class="affiliate-pagination-btn" data-page="${currentPage + 1}">Next →</button>`;
            }

            html += '</div>';
        }

        container.html(html);
    }

    // Delete affiliate link
    $(document).on('click', '.delete-link-btn', function(e) {
        e.preventDefault();

        if (!confirm(affiliateBloom.messages.confirm_delete)) {
            return;
        }

        var linkId = $(this).data('link-id');
        var linkCard = $(this).closest('.affiliate-link-card');

        $.ajax({
            url: affiliateBloom.ajax_url,
            type: 'POST',
            data: {
                action: 'delete_affiliate_link',
                link_id: linkId,
                nonce: affiliateBloom.nonce
            },
            success: function(response) {
                if (response.success) {
                    linkCard.fadeOut(300, function() {
                        $(this).remove();

                        // Check if no more links
                        if ($('.affiliate-link-card').length === 0) {
                            $('#affiliate-links-container').html('<div class="affiliate-no-links"><p>' + affiliateBloom.messages.no_links + '</p></div>');
                        }
                    });

                    showNotification(affiliateBloom.messages.deleted, 'success');
                } else {
                    showNotification('Error: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotification(affiliateBloom.messages.error, 'error');
            }
        });
    });

    // Load performance chart
    function loadPerformanceChart() {
        $.ajax({
            url: affiliateBloom.ajax_url,
            type: 'POST',
            data: {
                action: 'get_affiliate_stats',
                nonce: affiliateBloom.nonce
            },
            success: function(response) {
                if (response.success && response.data.monthly_stats) {
                    createPerformanceChart(response.data.monthly_stats);
                }
            },
            error: function() {
                console.log('Failed to load performance data');
            }
        });
    }

    function createPerformanceChart(monthlyData) {
        var ctx = document.getElementById('affiliate-performance-chart');
        if (!ctx) return;

        // Destroy existing chart if it exists
        if (performanceChart) {
            performanceChart.destroy();
        }

        var labels = monthlyData.map(function(item) {
            return new Date(item.month + '-01').toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        });

        var clicksData = monthlyData.map(function(item) {
            return item.clicks;
        });

        var conversionsData = monthlyData.map(function(item) {
            return item.conversions;
        });

        // Simple chart implementation (you can replace with Chart.js if available)
        var canvas = ctx.getContext('2d');
        var width = ctx.width;
        var height = ctx.height;

        // Clear canvas
        canvas.clearRect(0, 0, width, height);

        // Draw simple line chart
        drawSimpleChart(canvas, width, height, labels, clicksData, conversionsData);
    }

    function drawSimpleChart(ctx, width, height, labels, clicksData, conversionsData) {
        var padding = 40;
        var chartWidth = width - (padding * 2);
        var chartHeight = height - (padding * 2);

        // Find max values
        var maxClicks = Math.max(...clicksData, 1);
        var maxConversions = Math.max(...conversionsData, 1);
        var maxValue = Math.max(maxClicks, maxConversions);

        // Draw axes
        ctx.strokeStyle = '#ddd';
        ctx.lineWidth = 1;

        // Y-axis
        ctx.beginPath();
        ctx.moveTo(padding, padding);
        ctx.lineTo(padding, height - padding);
        ctx.stroke();

        // X-axis
        ctx.beginPath();
        ctx.moveTo(padding, height - padding);
        ctx.lineTo(width - padding, height - padding);
        ctx.stroke();

        // Draw data points and lines
        if (labels.length > 1) {
            var stepX = chartWidth / (labels.length - 1);

            // Draw clicks line
            ctx.strokeStyle = '#3498db';
            ctx.lineWidth = 2;
            ctx.beginPath();

            for (let i = 0; i < clicksData.length; i++) {
                var x = padding + (i * stepX);
                var y = height - padding - (clicksData[i] / maxValue * chartHeight);

                if (i === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
            }
            ctx.stroke();

            // Draw conversions line
            ctx.strokeStyle = '#e74c3c';
            ctx.lineWidth = 2;
            ctx.beginPath();

            for (let i = 0; i < conversionsData.length; i++) {
                var x = padding + (i * stepX);
                var y = height - padding - (conversionsData[i] / maxValue * chartHeight);

                if (i === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
            }
            ctx.stroke();
        }

        // Add legend
        ctx.fillStyle = '#3498db';
        ctx.fillRect(10, 10, 15, 15);
        ctx.fillStyle = '#333';
        ctx.font = '12px Arial';
        ctx.fillText('Clicks', 30, 22);

        ctx.fillStyle = '#e74c3c';
        ctx.fillRect(80, 10, 15, 15);
        ctx.fillStyle = '#333';
        ctx.fillText('Conversions', 100, 22);
    }

    // Utility functions
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };

        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function formatDate(dateString) {
        var date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    function showNotification(message, type) {
        // Remove existing notifications
        $('.affiliate-notification').remove();

        var notificationClass = 'affiliate-notification affiliate-notification-' + type;
        var notification = $('<div class="' + notificationClass + '">' + message + '</div>');

        $('body').append(notification);

        // Show notification
        setTimeout(function() {
            notification.addClass('show');
        }, 100);

        // Hide notification after 5 seconds
        setTimeout(function() {
            notification.removeClass('show');
            setTimeout(function() {
                notification.remove();
            }, 300);
        }, 5000);
    }

    // Auto-refresh stats every 5 minutes
    setInterval(function() {
        if ($('.affiliate-bloom-dashboard').length > 0) {
            refreshDashboardStats();
        }
    }, 300000); // 5 minutes

    function refreshDashboardStats() {
        $.ajax({
            url: affiliateBloom.ajax_url,
            type: 'POST',
            data: {
                action: 'get_affiliate_stats',
                nonce: affiliateBloom.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateDashboardStats(response.data);
                }
            },
            error: function() {
                console.log('Failed to refresh stats');
            }
        });
    }

    function updateDashboardStats(stats) {
        $('.affiliate-stat-card').each(function() {
            var card = $(this);
            var statText = card.find('p').text().toLowerCase();

            if (statText.includes('clicks')) {
                card.find('h3').text(numberFormat(stats.total_clicks));
            } else if (statText.includes('conversions')) {
                card.find('h3').text(numberFormat(stats.total_conversions));
            } else if (statText.includes('total earnings')) {
                card.find('h3').text('$' + numberFormat(stats.total_earnings, 2));
            } else if (statText.includes('pending')) {
                card.find('h3').text('$' + numberFormat(stats.pending_earnings, 2));
            } else if (statText.includes('balance')) {
                card.find('h3').text('$' + numberFormat(stats.current_balance, 2));
            } else if (statText.includes('rate')) {
                card.find('h3').text(stats.conversion_rate + '%');
            }
        });
    }

    function numberFormat(number, decimals = 0) {
        return Number(number).toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    // Initialize tooltips
    $(document).on('mouseenter', '[title]', function() {
        var $this = $(this);
        var title = $this.attr('title');

        if (title) {
            $this.data('original-title', title);
            $this.removeAttr('title');

            var tooltip = $('<div class="affiliate-tooltip">' + title + '</div>');
            $('body').append(tooltip);

            var offset = $this.offset();
            tooltip.css({
                top: offset.top - tooltip.outerHeight() - 5,
                left: offset.left + ($this.outerWidth() / 2) - (tooltip.outerWidth() / 2)
            }).fadeIn(200);
        }
    });

    $(document).on('mouseleave', '[data-original-title]', function() {
        var $this = $(this);
        $this.attr('title', $this.data('original-title'));
        $('.affiliate-tooltip').remove();
    });

    // Handle responsive menu toggle
    $(document).on('click', '.affiliate-mobile-menu-toggle', function(e) {
        e.preventDefault();
        $('.affiliate-tab-nav').toggleClass('mobile-open');
    });

    // Close mobile menu when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.affiliate-tab-nav, .affiliate-mobile-menu-toggle').length) {
            $('.affiliate-tab-nav').removeClass('mobile-open');
        }
    });
});