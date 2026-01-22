/**
 * Affiliate Bloom Admin JavaScript
 */
(function($) {
    'use strict';

    var AffiliateBloomAdmin = {
        init: function() {
            this.bindEvents();
            this.initDivisionFilter();
        },

        bindEvents: function() {
            // Modal events
            $(document).on('click', '.modal-close, .modal-overlay', this.closeModal);
            $(document).on('keydown', this.handleEscKey);

            // Application events
            $(document).on('click', '.view-application', this.viewApplication);
            $(document).on('click', '.approve-application', this.approveApplication);
            $(document).on('click', '.reject-application', this.rejectApplication);

            // Affiliate events
            $(document).on('click', '.view-affiliate', this.viewAffiliate);
            $(document).on('click', '.view-team', this.viewTeam);

            // Bulk actions
            $(document).on('click', '#bulk-apply', this.handleBulkAction);
            $(document).on('change', '#select-all', this.toggleSelectAll);

            // Team search
            $(document).on('keyup', '#team-search', this.filterTeamTable);
        },

        // Modal Functions
        openModal: function(title, content, actions) {
            $('#modal-title').text(title || affiliateBloomAdmin.strings.loading);
            $('#modal-body').html(content || '<p>' + affiliateBloomAdmin.strings.loading + '</p>');
            $('#modal-actions').html(actions || '');
            $('#affiliate-bloom-modal').show();
            $('body').css('overflow', 'hidden');
        },

        closeModal: function() {
            $('#affiliate-bloom-modal').hide();
            $('body').css('overflow', '');
        },

        handleEscKey: function(e) {
            if (e.key === 'Escape') {
                AffiliateBloomAdmin.closeModal();
            }
        },

        // Application Functions
        viewApplication: function() {
            var appId = $(this).data('id');
            AffiliateBloomAdmin.openModal(affiliateBloomAdmin.strings.loading, null, null);

            $.post(affiliateBloomAdmin.ajaxUrl, {
                action: 'get_application_details',
                application_id: appId,
                nonce: affiliateBloomAdmin.nonce
            }, function(response) {
                if (response.success) {
                    $('#modal-title').text(response.data.title);
                    $('#modal-body').html(response.data.html);
                    $('#modal-actions').html(response.data.actions);
                } else {
                    $('#modal-body').html('<p class="error">' + affiliateBloomAdmin.strings.error + '</p>');
                }
            }).fail(function() {
                $('#modal-body').html('<p class="error">' + affiliateBloomAdmin.strings.error + '</p>');
            });
        },

        approveApplication: function() {
            var appId = $(this).data('id');

            if (!confirm(affiliateBloomAdmin.strings.confirmApprove)) {
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true).text('Processing...');

            $.post(affiliateBloomAdmin.ajaxUrl, {
                action: 'approve_affiliate_application',
                application_id: appId,
                nonce: affiliateBloomAdmin.nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || affiliateBloomAdmin.strings.error);
                    $button.prop('disabled', false).text('Approve');
                }
            }).fail(function() {
                alert(affiliateBloomAdmin.strings.error);
                $button.prop('disabled', false).text('Approve');
            });
        },

        rejectApplication: function() {
            var appId = $(this).data('id');
            var reason = prompt(affiliateBloomAdmin.strings.confirmReject);

            if (reason === null) {
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true).text('Processing...');

            $.post(affiliateBloomAdmin.ajaxUrl, {
                action: 'reject_affiliate_application',
                application_id: appId,
                reason: reason,
                nonce: affiliateBloomAdmin.nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || affiliateBloomAdmin.strings.error);
                    $button.prop('disabled', false).text('Reject');
                }
            }).fail(function() {
                alert(affiliateBloomAdmin.strings.error);
                $button.prop('disabled', false).text('Reject');
            });
        },

        // Affiliate Functions
        viewAffiliate: function() {
            var userId = $(this).data('id');
            AffiliateBloomAdmin.openModal(affiliateBloomAdmin.strings.loading, null, null);

            $.post(affiliateBloomAdmin.ajaxUrl, {
                action: 'affiliate_bloom_get_affiliate_details',
                user_id: userId,
                nonce: affiliateBloomAdmin.nonce
            }, function(response) {
                if (response.success) {
                    $('#modal-title').text(response.data.title);
                    $('#modal-body').html(response.data.html);
                } else {
                    $('#modal-body').html('<p class="error">' + affiliateBloomAdmin.strings.error + '</p>');
                }
            }).fail(function() {
                $('#modal-body').html('<p class="error">' + affiliateBloomAdmin.strings.error + '</p>');
            });
        },

        viewTeam: function() {
            var userId = $(this).data('id');
            AffiliateBloomAdmin.openModal(affiliateBloomAdmin.strings.loading, null, null);

            $.post(affiliateBloomAdmin.ajaxUrl, {
                action: 'affiliate_bloom_get_affiliate_details',
                user_id: userId,
                nonce: affiliateBloomAdmin.nonce
            }, function(response) {
                if (response.success) {
                    $('#modal-title').text(response.data.title);
                    $('#modal-body').html(response.data.html);
                } else {
                    $('#modal-body').html('<p class="error">' + affiliateBloomAdmin.strings.error + '</p>');
                }
            }).fail(function() {
                $('#modal-body').html('<p class="error">' + affiliateBloomAdmin.strings.error + '</p>');
            });
        },

        // Bulk Actions
        handleBulkAction: function() {
            var action = $('#bulk-action-selector').val();
            var selected = $('.application-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            if (!action) {
                alert('Please select an action.');
                return;
            }

            if (selected.length === 0) {
                alert('Please select at least one application.');
                return;
            }

            var confirmMsg = action === 'approve'
                ? 'Are you sure you want to approve ' + selected.length + ' application(s)?'
                : 'Are you sure you want to reject ' + selected.length + ' application(s)?';

            if (!confirm(confirmMsg)) {
                return;
            }

            var reason = '';
            if (action === 'reject') {
                reason = prompt('Rejection reason (optional):') || '';
            }

            var processed = 0;
            var total = selected.length;

            selected.forEach(function(appId) {
                $.post(affiliateBloomAdmin.ajaxUrl, {
                    action: action === 'approve' ? 'approve_affiliate_application' : 'reject_affiliate_application',
                    application_id: appId,
                    reason: reason,
                    nonce: affiliateBloomAdmin.nonce
                }, function() {
                    processed++;
                    if (processed === total) {
                        location.reload();
                    }
                });
            });
        },

        toggleSelectAll: function() {
            var isChecked = $(this).prop('checked');
            $('.application-checkbox').prop('checked', isChecked);
        },

        // Team Search
        filterTeamTable: function() {
            var searchTerm = $(this).val().toLowerCase();

            $('.affiliate-bloom-teams tbody tr').each(function() {
                var text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(searchTerm) > -1);
            });
        },

        // Division Filter for Leaderboard
        initDivisionFilter: function() {
            var divisions = {
                'Barishal': ['Barguna', 'Barishal', 'Bhola', 'Jhalokati', 'Patuakhali', 'Pirojpur'],
                'Chattogram': ['Bandarban', 'Brahmanbaria', 'Chandpur', 'Chattogram', 'Comilla', "Cox's Bazar", 'Feni', 'Khagrachhari', 'Lakshmipur', 'Noakhali', 'Rangamati'],
                'Dhaka': ['Dhaka', 'Faridpur', 'Gazipur', 'Gopalganj', 'Kishoreganj', 'Madaripur', 'Manikganj', 'Munshiganj', 'Narayanganj', 'Narsingdi', 'Rajbari', 'Shariatpur', 'Tangail'],
                'Khulna': ['Bagerhat', 'Chuadanga', 'Jessore', 'Jhenaidah', 'Khulna', 'Kushtia', 'Magura', 'Meherpur', 'Narail', 'Satkhira'],
                'Mymensingh': ['Jamalpur', 'Mymensingh', 'Netrokona', 'Sherpur'],
                'Rajshahi': ['Bogra', 'Chapainawabganj', 'Joypurhat', 'Naogaon', 'Natore', 'Nawabganj', 'Pabna', 'Rajshahi', 'Sirajganj'],
                'Rangpur': ['Dinajpur', 'Gaibandha', 'Kurigram', 'Lalmonirhat', 'Nilphamari', 'Panchagarh', 'Rangpur', 'Thakurgaon'],
                'Sylhet': ['Habiganj', 'Moulvibazar', 'Sunamganj', 'Sylhet']
            };

            $('#filter-division').on('change', function() {
                var division = $(this).val();
                var $zillaSelect = $('#filter-zilla');
                var currentZilla = $zillaSelect.val();

                $zillaSelect.html('<option value="">All Zillas</option>');

                if (division && divisions[division]) {
                    divisions[division].forEach(function(zilla) {
                        var selected = zilla === currentZilla ? ' selected' : '';
                        $zillaSelect.append('<option value="' + zilla + '"' + selected + '>' + zilla + '</option>');
                    });
                }
            });
        }
    };

    $(document).ready(function() {
        AffiliateBloomAdmin.init();
    });

})(jQuery);
