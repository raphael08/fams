/* Turns every <table class="table"> that has a <thead> into a responsive DataTable with search + export buttons.
   Opt out with class "no-datatable". Header cells named "Actions" (or empty) are not sortable/exported. */
(function ($) {
    if (!$ || !$.fn.DataTable) { return; }
    $.fn.dataTable.ext.errMode = 'none';

    function colCount($row) {
        var n = 0;
        $row.children('td,th').each(function () { n += parseInt($(this).attr('colspan') || 1, 10); });
        return n;
    }

    $(function () {
        var title = $.trim($('main h4').first().text()) || document.title;

        $('main table.table').not('.no-datatable').each(function () {
            var $t = $(this);
            if ($t.closest('.no-datatable').length || !$t.children('thead').length) { return; }

            // "No records" placeholder rows use colspan; DataTables shows its own empty message instead.
            $t.children('tbody').children('tr').has('td[colspan]').remove();

            var $ths = $t.children('thead').find('tr').last().children('th');
            if (!$ths.length) { return; }
            var cols = $ths.length, ok = true;
            $t.children('tbody').children('tr').each(function () { if (colCount($(this)) !== cols) { ok = false; } });
            if (!ok) { return; }   // irregular table: leave it as a normal table

            var skip = [];
            $ths.each(function (i) {
                var txt = $.trim($(this).text()).toLowerCase();
                if (txt === '' || txt === 'action' || txt === 'actions' || $(this).hasClass('no-export')) {
                    skip.push(i);
                    $(this).addClass('no-export');
                }
            });

            var base = { className: 'btn btn-sm btn-outline-secondary', title: title, exportOptions: { columns: ':not(.no-export)' } };
            try {
                $t.DataTable({
                    responsive: true,
                    pageLength: 25,
                    lengthMenu: [10, 25, 50, 100, 500],
                    order: [],
                    autoWidth: false,
                    columnDefs: [
                        { targets: skip, orderable: false, searchable: false },
                        { targets: $ths.first().hasClass('select-col') ? 1 : 0, responsivePriority: 1 },
                        { targets: -1, responsivePriority: 2 }
                    ],
                    language: { search: '', searchPlaceholder: 'Search…', emptyTable: 'No records found.' },
                    layout: {
                        topStart: { buttons: [
                            $.extend({}, base, { extend: 'copy',  text: '<i class="bi bi-clipboard"></i> Copy' }),
                            $.extend({}, base, { extend: 'csv',   text: '<i class="bi bi-filetype-csv"></i> CSV' }),
                            $.extend({}, base, { extend: 'excel', text: '<i class="bi bi-file-earmark-excel"></i> Excel' }),
                            $.extend({}, base, { extend: 'pdf',   text: '<i class="bi bi-file-earmark-pdf"></i> PDF', orientation: 'landscape', pageSize: 'A4' }),
                            $.extend({}, base, { extend: 'print', text: '<i class="bi bi-printer"></i> Print' })
                        ] },
                        topEnd: 'search',
                        bottomStart: ['pageLength', 'info'],
                        bottomEnd: 'paging'
                    }
                });
            } catch (e) { /* keep the plain table if DataTables cannot start */ }
        });
    });
})(window.jQuery);
