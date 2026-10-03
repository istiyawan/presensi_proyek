// Urutan import CSS penting: plugin dulu, tema terakhir agar bisa menimpa.
import '@fontsource-variable/plus-jakarta-sans';
import 'bootstrap-icons/font/bootstrap-icons.css';
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';
import 'datatables.net-responsive-bs5/css/responsive.bootstrap5.css';
import 'select2/dist/css/select2.css';
import 'select2-bootstrap-5-theme/dist/select2-bootstrap-5-theme.css';
import 'flatpickr/dist/flatpickr.css';
import 'leaflet/dist/leaflet.css';
import '../scss/app.scss';

import $ from 'jquery';
import * as bootstrap from 'bootstrap';
import DataTable from 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';
import select2 from 'select2';
import flatpickr from 'flatpickr';
import { Indonesian } from 'flatpickr/dist/l10n/id.js';

import { setupHttp } from './core/http';
import { setupShell } from './core/shell';

window.$ = window.jQuery = $;
window.bootstrap = bootstrap;
DataTable.use($);
DataTable.use(bootstrap);
select2(window, $);
flatpickr.localize(Indonesian);

setupHttp();

$(() => {
    setupShell();

    // Script per halaman: <body data-page="employees"> → ./pages/employees.js
    const page = document.body.dataset.page;
    const pages = import.meta.glob('./pages/*.js');
    const loader = pages[`./pages/${page}.js`];
    if (loader) {
        loader().then((m) => m.default?.());
    }
});
