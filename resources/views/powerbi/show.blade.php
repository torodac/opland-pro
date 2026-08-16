<x-app-layout :breadcrumb="[['label' => 'Power BI', 'url' => '']]" :project="$project">

<div id="pbi-report-container" style="width:100%;height:calc(100vh - 6.5rem);border-radius:12px;overflow:hidden;border:.5px solid rgba(0,0,0,.08);"></div>

<script src="https://cdn.jsdelivr.net/npm/powerbi-client@2.23.1/dist/powerbi.min.js"></script>
<script>
(function () {
    var models = window['powerbi-client'].models;

    var filtros = @json($filtros);
    var filters = filtros.map(function (f) {
        return {
            $schema: 'http://powerbi.com/product/schema#basic',
            target: { table: f.tabla, column: f.columna },
            displaySettings: { isLockedInViewMode: true, isHiddenInViewMode: true },
            operator: 'In',
            values: Array.isArray(f.valor) ? f.valor : [f.valor],
            filterType: models.FilterType.Basic,
        };
    });

    var config = {
        type: 'report',
        tokenType: models.TokenType.Aad,
        accessToken: @json($token),
        embedUrl: @json($embedUrl),
        id: @json($reportId),
        permissions: models.Permissions.Read,
        settings: {
            panes: {
                filters: { visible: {{ $filtersVisible ? 'true' : 'false' }} },
                pageNavigation: { visible: {{ $pageNavigation ? 'true' : 'false' }} },
            },
        },
        filters: filters,
    };

    var container = document.getElementById('pbi-report-container');
    var powerbi = window.powerbi;
    var report = powerbi.embed(container, config);

    report.on('loaded', function () {
        @if($reportPage)
        report.setPage(@json($reportPage)).catch(function (e) { console.error(e); });
        @endif
    });

    report.on('error', function (event) {
        console.error('Power BI error:', event.detail);
    });
})();
</script>

</x-app-layout>
