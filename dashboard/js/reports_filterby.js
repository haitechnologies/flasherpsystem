/**
 * reports_filterby.js
 *
 * Auto-submitting wiring for report pages that offer a `filter_by`
 * quick-range dropdown (Today / This Week / This Month / ... ).
 *
 * When the user picks a preset the report form is submitted immediately;
 * the server-side `getDateRangeByFilter()` helper resolves the matching
 * start/end dates. A "0" (Please select) value does nothing so a manual
 * date range can still be typed afterwards.
 */
(function ($) {
    'use strict';

    $(document).on('change', '#filter_by', function () {
        var form = $(this).closest('form');

        if (!form.length) {
            return;
        }

        if (this.value === '0' || this.value === '') {
            return;
        }

        form.submit();
    });
})(jQuery);