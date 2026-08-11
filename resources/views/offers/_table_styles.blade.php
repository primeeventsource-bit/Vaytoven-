{{-- Shared styling for the two offer registers (owner dashboard + admin). --}}
<style>
    .vyt-offers-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .vyt-offers {
        width: 100%; border-collapse: collapse; font-size: 13.5px;
        min-width: 940px;
    }
    .vyt-offers th {
        text-align: left; font-size: 11px; letter-spacing: .08em;
        text-transform: uppercase; color: var(--muted); font-weight: 650;
        padding: 0 14px 10px 0; border-bottom: 1px solid var(--line);
        white-space: nowrap;
    }
    .vyt-offers td {
        padding: 14px 14px 14px 0; border-bottom: 1px solid var(--line);
        vertical-align: top;
    }
    .vyt-offers tr:last-child td { border-bottom: 0; }
    .vyt-offers th:last-child, .vyt-offers td:last-child { padding-right: 0; }

    .vyt-offers .num { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .vyt-offers .ip {
        font-family: 'SFMono-Regular', Consolas, monospace; font-size: 12px;
        color: var(--muted); white-space: nowrap;
    }
    .vyt-offers .sub { display: block; font-size: 12px; color: var(--muted); margin-top: 2px; }
    .vyt-offers .listing { font-weight: 600; }

    .vyt-ostatus {
        display: inline-block; padding: 3px 10px; border-radius: 999px;
        font-size: 10.5px; font-weight: 700; letter-spacing: .06em;
        white-space: nowrap;
    }
    .vyt-ostatus-active   { background:#ecfdf5; color:#047857; }
    .vyt-ostatus-accepted { background:#dbeafe; color:#1e40af; }
    .vyt-ostatus-declined { background:#fee2e2; color:#b91c1c; }
    .vyt-ostatus-expired  { background:#f1f5f9; color:#475569; }
    .vyt-ostatus-pending  { background:#fffbeb; color:#92400e; }
    .vyt-ostatus-withdrawn{ background:#f1f5f9; color:#475569; }

    .vyt-okind {
        display:inline-block; font-size:10px; font-weight:700; letter-spacing:.06em;
        text-transform:uppercase; color:var(--muted); border:1px solid var(--line);
        border-radius:4px; padding:1px 6px; margin-left:6px;
    }

    .vyt-oactions { display:flex; gap:10px; white-space:nowrap; }
    .vyt-oactions button {
        border:0; background:none; padding:0; cursor:pointer;
        font-size:13px; font-weight:600; font-family:inherit;
    }
    .vyt-oactions .accept { color:#047857; }
    .vyt-oactions .decline { color:#b91c1c; }

    .vyt-omsg {
        margin-top:6px; font-size:12.5px; color:var(--ink, #1b1420);
        background:var(--bg, #f8fafc); border-radius:6px; padding:7px 10px;
        max-width:340px; line-height:1.5;
    }
    .vyt-empty {
        text-align:center; padding:52px 20px; color:var(--muted); font-size:14.5px;
    }
</style>
