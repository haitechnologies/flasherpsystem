(function(window, $) {
    'use strict';

    function getJQuery() {
        return window.jQuery || window.$ || $;
    }

    function hasDataTableApi() {
        var jq = getJQuery();
        return !!(jq && jq.fn && (jq.fn.DataTable || jq.fn.dataTable));
    }

    function getIsDataTableFn() {
        var jq = getJQuery();
        if (!jq || !jq.fn) {
            return null;
        }

        if (jq.fn.DataTable && typeof jq.fn.DataTable.isDataTable === 'function') {
            return jq.fn.DataTable.isDataTable;
        }

        if (jq.fn.dataTable && typeof jq.fn.dataTable.isDataTable === 'function') {
            return jq.fn.dataTable.isDataTable;
        }

        return null;
    }

    function getTableApi($el) {
        if (!$el || !$el.length) {
            return null;
        }

        if (typeof $el.DataTable === 'function') {
            return {
                init: function(config) { return $el.DataTable(config); },
                instance: function() { return $el.DataTable(); }
            };
        }

        if (typeof $el.dataTable === 'function') {
            return {
                init: function(config) { return $el.dataTable(config); },
                instance: function() { return $el.dataTable(); }
            };
        }

        return null;
    }

    function getDatatablesUrl() {
        try {
            return new URL('datatables.php', window.location.href).toString();
        } catch (error) {
            return 'datatables.php';
        }
    }

    function inferColumnsFromTable($table) {
        var columns = [];

        if (!$table || !$table.length) {
            return columns;
        }

        $table.find('thead th').each(function(index) {
            var text = ($(this).text() || '').replace(/\s+/g, ' ').trim().toLowerCase();
            var column = { data: index };

            if (text === 'action' || text === 'actions' || text === 'edit' || text === 'select' ||
                text === 'logo' || text === 'image' || text === 'qr' || text === 'sr.' || text === 'sr' ||
                text === 'primary') {
                column.orderable = false;
                column.searchable = false;
            }

            columns.push(column);
        });

        return columns;
    }

    function bindDeleteHandler(jq) {
        if (!jq || typeof jq !== 'function' || jq(document).data('hai-datatable-delete-bound')) {
            return;
        }

        jq(document)
            .data('hai-datatable-delete-bound', true)
            .on('click.haiDatatableDelete', 'a[data-action="delete_record"]', function(e) {
                e.preventDefault();

                var id = jq(this).data('id');
                var module = jq(this).data('module');
                var confirmMsg = jq(this).data('confirm') || 'Are you sure?';

                if (!id || !module || !window.confirm(confirmMsg)) {
                    return;
                }

                var csrfToken = window.HAI_CSRF_TOKEN || jq('input[name="csrf_token"]').first().val() || '';

                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete_' + module + '">' +
                    '<input type="hidden" name="id" value="' + id + '">' +
                    '<input type="hidden" name="csrf_token" value="' + csrfToken + '">';
                document.body.appendChild(form);
                form.submit();
            });
    }

    function autoInitListingTables() {
        var jq = getJQuery();

        if (!jq || typeof jq !== 'function') {
            return;
        }

        bindDeleteHandler(jq);

        jq('table.custom_datatables[id^="grid-"]').each(function() {
            var $table = jq(this);
            var selector = '#' + ($table.attr('id') || '');
            var moduleName = ($table.attr('id') || '').replace(/^grid-/, '');

            if (!selector || selector === '#' || !moduleName || $table.data('haiDatatableInitialized')) {
                return;
            }

            $table.data('haiDatatableInitialized', true);

            init(selector, moduleName, {
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                columns: inferColumnsFromTable($table),
                order: [[0, 'desc']]
            });
        });
    }

    function buildAjaxErrorHandler(moduleName, tableSelector) {
        return function(xhr, status, error) {
            var jq = getJQuery();

            console.error('[' + moduleName + '] DataTable AJAX Error:', error);
            console.error('[' + moduleName + '] Status:', xhr.status, '|', status);
            console.error('[' + moduleName + '] Response:', xhr.responseText);

            if (jq && tableSelector) {
                jq('.grid-error').remove();
                jq(tableSelector).append('<tbody class="grid-error"><tr><th colspan="99">Error loading data. Check browser console.</th></tr></tbody>');
                jq(tableSelector + '_processing').hide();
            }
        };
    }

    function getDefaultConfig(tableSelector, moduleName, overrides) {
        var jq = getJQuery();

        var base = {
            processing: true,
            serverSide: true,
            stateSave: false,
            deferRender: true,
            retrieve: false,
            autoWidth: false,
            searchDelay: 400,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            responsive: true,
            dom: (window.HAIDataTable && window.HAIDataTable.defaultDom)
                ? window.HAIDataTable.defaultDom
                : "<'dt-header'<'dt-head-left'fl><'dt-head-right'>>rt<'dt-footer'<'dt-foot-left'i><'dt-foot-right'p>>",
            ajax: {
                url: getDatatablesUrl(),
                type: 'POST',
                data: function(d) {
                    d.ajax_action = 'listing_' + moduleName;
                    d.module = moduleName;
                    d.action = d.action || d.ajax_action;

                    var csrfFromWindow = window.HAI_CSRF_TOKEN || '';
                    var csrfFromInput = '';
                    if (jq && typeof jq === 'function') {
                        csrfFromInput = jq('input[name="csrf_token"]').first().val() || '';
                    }

                    if (typeof d.csrf_token === 'undefined' || d.csrf_token === '') {
                        d.csrf_token = csrfFromWindow || csrfFromInput || '';
                    }
                    return d;
                },
                error: buildAjaxErrorHandler(moduleName, tableSelector)
            },
            drawCallback: function() {
                if (window.HAIDataTable && typeof window.HAIDataTable.applyLayoutFixes === 'function') {
                    window.HAIDataTable.applyLayoutFixes(tableSelector);
                }
            }
        };

        if (!jq) {
            return base;
        }

        var config = jq.extend(true, {}, base, overrides || {});
        var overrideAjaxData = overrides && overrides.ajax ? overrides.ajax.data : undefined;

        if (overrideAjaxData !== undefined) {
            config.ajax = config.ajax || {};

            config.ajax.data = function(d) {
                var payload = d || {};

                function mergeIntoPayload(currentPayload, returnedPayload) {
                    if (!returnedPayload || typeof returnedPayload !== 'object') {
                        return currentPayload;
                    }

                    // Preserve DataTables request contract (draw/start/length/order/search)
                    // and only merge additional fields from override handlers.
                    if (returnedPayload === currentPayload) {
                        return currentPayload;
                    }

                    return jq.extend(true, currentPayload, returnedPayload);
                }

                if (base.ajax && typeof base.ajax.data === 'function') {
                    var baseResult = base.ajax.data.call(this, payload);
                    payload = mergeIntoPayload(payload, baseResult);
                }

                if (typeof overrideAjaxData === 'function') {
                    var overrideResult = overrideAjaxData.call(this, payload);
                    payload = mergeIntoPayload(payload, overrideResult);
                } else if (overrideAjaxData && typeof overrideAjaxData === 'object') {
                    payload = jq.extend(true, payload, overrideAjaxData);
                }

                return payload;
            };
        }

        return config;
    }

    function init(selector, moduleName, overrides) {
        var jq = getJQuery();

        if (!selector || !moduleName) {
            throw new Error('DataTableInitializer.init requires selector and moduleName');
        }

        if (!jq) {
            console.error('[HAIDatatableInitializer] jQuery is not available for selector:', selector);
            return null;
        }

        if (!jq(selector).length) {
            console.error('[HAIDatatableInitializer] Table selector not found:', selector);
            return null;
        }

        if (!hasDataTableApi()) {
            var attempts = 0;
            var maxAttempts = 40;
            var retryDelayMs = 75;
            var retryTimer = window.setInterval(function() {
                attempts++;

                if (hasDataTableApi()) {
                    window.clearInterval(retryTimer);
                    init(selector, moduleName, overrides || {});
                    return;
                }

                if (attempts >= maxAttempts) {
                    window.clearInterval(retryTimer);
                    console.error('[HAIDatatableInitializer] DataTables unavailable after retries for selector:', selector);
                }
            }, retryDelayMs);

            return null;
        }

        var isDataTable = getIsDataTableFn();
        var $table = jq(selector);
        var tableApi = getTableApi($table);

        if (!tableApi) {
            console.error('[HAIDatatableInitializer] Unable to resolve DataTables API for selector:', selector);
            return null;
        }

        if (isDataTable && isDataTable(selector)) {
            tableApi.instance().destroy();
        }

        var config = getDefaultConfig(selector, moduleName, overrides || {});
        var instance = tableApi.init(config);
        $table.data('haiDatatableInitialized', true);

        // Always apply shared layout normalization even when page-level config
        // overrides drawCallback/initComplete.
        jq(selector)
            .off('init.dt.haiLayout draw.dt.haiLayout')
            .on('init.dt.haiLayout draw.dt.haiLayout', function() {
                if (window.HAIDataTable && typeof window.HAIDataTable.applyLayoutFixes === 'function') {
                    window.HAIDataTable.applyLayoutFixes(selector);
                }
            });

        return instance;
    }

    window.HAIDatatableInitializer = {
        init: init,
        getDefaultConfig: getDefaultConfig,
        autoInitListingTables: autoInitListingTables
    };

    $(function() {
        window.setTimeout(autoInitListingTables, 0);
    });
})(window, window.jQuery);
