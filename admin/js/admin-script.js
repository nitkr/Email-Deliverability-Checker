jQuery(document).ready(function($) {
    // For test email form.
    $('#edc-test-form').on('submit', function(e) {
        e.preventDefault();
        var recipient = $('#edc-recipient').val();
        var nonce = edc_ajax.nonce;

        $('#edc-message').removeClass('edc-success edc-error').text('');

        $.ajax({
            url: edc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'edc_send_test_email',
                recipient: recipient,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#edc-message').addClass('edc-success').text(response.data);
                } else {
                    var logNote = edc_ajax.log_note;
                    $('#edc-message').addClass('edc-error').html('<pre><code>' + response.data + '</code></pre><em>' + logNote + '</em>');
                }
            },
            error: function() {
                $('#edc-message').addClass('edc-error').text('AJAX error occurred. Please try again.');
            }
        });
    });

    // Toggle error details in logs table.
    $(document).on('click', '.edc-toggle-error', function(e) {
        e.preventDefault();
        var logId = $(this).data('log-id');
        $('#edc-error-' + logId).slideToggle();
    });
});
