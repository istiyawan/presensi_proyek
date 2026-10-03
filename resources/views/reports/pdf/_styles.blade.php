<style>
    @page { margin: 22mm 13mm 16mm 13mm; }
    * { box-sizing: border-box; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 8.6pt; color: #111; margin: 0; }

    .report-head { text-align: center; margin-bottom: 9mm; }
    .report-head h1 { font-size: 17pt; font-weight: normal; margin: 0 0 3mm; }
    .report-head p { font-size: 11pt; margin: 0 0 2mm; }
    .report-head .meta { font-size: 8pt; color: #555; margin-top: 2mm; }

    table.grid { width: 100%; border-collapse: collapse; }
    table.grid thead { display: table-header-group; }
    table.grid tr { page-break-inside: avoid; }
    table.grid th {
        background: #e3f0fb;
        font-weight: normal;
        text-align: left;
        vertical-align: top;
        border: 1px solid #111;
        padding: 4px 6px;
    }
    table.grid td { border: 1px solid #111; padding: 4px 6px; vertical-align: top; }
    table.grid tbody tr:nth-child(even) td { background: #f4f4f4; }
    .c { text-align: center; }
    .r { text-align: right; }
    .muted { color: #666; }
    .note { display: block; font-size: 6.8pt; color: #7a4a00; margin-top: 1px; }

    .photos { text-align: center; white-space: nowrap; }
    .photo { display: inline-block; width: 30px; margin: 0 3px; text-align: center; vertical-align: top; }
    .photo img { width: 30px; height: 38px; }
    .photo .ph { width: 30px; height: 38px; border: 1px dashed #bbb; }
    .photo span { display: block; font-size: 5.5pt; color: #444; margin-top: 1px; }

    .sign { margin-top: 12mm; page-break-inside: avoid; font-family: "Times New Roman", Times, serif; font-size: 11pt; }
    .sign table { width: 100%; border-collapse: collapse; }
    .sign td { text-align: center; vertical-align: top; padding: 0 4mm; }
    .sign .space { height: 22mm; }
    .sign .name { text-decoration: underline; }
    .sign .title { font-style: italic; }

    .page-break { page-break-after: always; }
    .footer-meta { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 6.5pt; color: #888; }
</style>
