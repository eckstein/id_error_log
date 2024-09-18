jQuery(document).ready(function($) {
    function refreshLog() {
        $.ajax({
            url: wpErrorLog.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_error_log',
                nonce: wpErrorLog.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#error-log-content').val(response.data.content || 'No errors logged.');
                } else {
                    alert('Failed to fetch log: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('AJAX error: ' + textStatus + ' - ' + errorThrown);
            }
        });
    }

    $('#refresh-log').on('click', function(e) {
        e.preventDefault();
        refreshLog();
    });

    $('#clear-log').on('click', function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to clear the debug log?')) {
            $.ajax({
                url: wpErrorLog.ajaxurl,
                type: 'POST',
                data: {
                    action: 'clear_error_log',
                    nonce: wpErrorLog.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        refreshLog();
                    } else {
                        alert('Failed to clear log: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Failed to clear log. Please try again.');
                }
            });
        }
    });

    // Initial load
    refreshLog();

    // Auto-refresh every 30 seconds
    setInterval(refreshLog, 30000);
});