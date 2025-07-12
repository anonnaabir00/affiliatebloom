jQuery(document).ready(function($) {

    // Generate affiliate link
    $(document).on('click', '#generate-affiliate-link', function(e) {
        e.preventDefault();

        if (!affiliateBloom.is_affiliate) {
            alert('You are not an approved affiliate.');
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
                } else {
                    alert('Error: ' + response.data);
                    button.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                alert(affiliateBloom.messages.error);
                button.prop('disabled', false).text(originalText);
            }
        });
    });