jQuery(function ($) {
    const $bulkBar = $('#wpcBulkBar');
    const $selectedCount = $('#selectedCount');
    const $checkboxes = $('.cb-image');
    const $selectAll = $('#cb-select-all');

    /**
     * UI: Handle Selection & Bulk Bar Visibility
     */
    function updateBulkUI() {
        const checkedCount = $('.cb-image:checked').length;
        $selectedCount.text(checkedCount);
        
        if (checkedCount > 0) {
            $bulkBar.addClass('active');
        } else {
            $bulkBar.removeClass('active');
        }
    }

    $selectAll.on('change', function() {
        $checkboxes.prop('checked', this.checked);
        updateBulkUI();
    });

    $checkboxes.on('change', updateBulkUI);

    /**
     * Logic: Single Convert
     */
    $(document).on('click', '.convert-btn', function (e) {
        e.preventDefault();
        const $btn = $(this);
        const $row = $btn.closest('tr');
        convertImage($row, $btn);
    });

    /**
     * Logic: Bulk Convert
     */
    $('#btnBulkConvert').on('click', function() {
        const $selected = $('.cb-image:checked');
        if ($selected.length === 0) return;

        // UI Prep
        $(this).prop('disabled', true).addClass('processing');
        $('.wpc-global-progress').addClass('active');
        
        const total = $selected.length;
        let processed = 0;
        
        // Helper to update global bar
        function updateGlobalProgress() {
            processed++;
            const percent = (processed / total) * 100;
            document.querySelector('.wpc-global-bar').style.setProperty('--prog', percent + '%');
            $('#globalProgressText').text(`${processed}/${total} Completed`);
        }

        // Create a queue to prevent server overload
        let queue = $selected.toArray();

        function processNext() {
            if (queue.length === 0) {
                // All done
                setTimeout(() => location.reload(), 1000);
                return;
            }

            const imgInput = queue.shift();
            const $row = $(imgInput).closest('tr');
            const $btn = $row.find('.convert-btn');

            // Scroll to row (optional UI candy)
            // $('html, body').animate({ scrollTop: $row.offset().top - 150 }, 200);

            convertImage($row, $btn)
                .always(() => {
                    updateGlobalProgress();
                    processNext(); // Recursive call
                });
        }

        // Start the queue
        processNext();
    });

    /**
     * Core Conversion Function (Returns Promise)
     */
    function convertImage($row, $btn) {
        const id = $row.data('id');
        const $progressBox = $row.find('.progress-mini');
        const $bar = $progressBox.find('.bar');

        $btn.hide();
        $progressBox.show();
        $bar.css('width', '100%').addClass('loading-anim'); // Add CSS animation class if desired

        return $.post(WPC.ajax, {
            action: 'wpc_convert',
            id: id,
            nonce: WPC.nonce
        })
        .done(function (res) {
            if (res.success) {
                $row.css('background', '#d4edda').fadeOut(500, function() {
                    $(this).remove();
                    // Update main stats count if present
                });
            } else {
                $btn.show().text('Retry').css('color', '#d63638');
                $progressBox.hide();
                alert(res.data || 'Error converting');
            }
        })
        .fail(function () {
            $btn.show().text('Error');
            $progressBox.hide();
        });
    }
});