jQuery(document).ready(function($) {

    // Generate core link
    $(document).on('click', '#generate-core-link', function(e) {
        e.preventDefault();

        if (!affiliateBloom.is_affiliate) {
            alert('You are not an approved core.');
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
                    $('#core-url').val(response.data.affiliate_url);
                    $('#core-result').slideDown();
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