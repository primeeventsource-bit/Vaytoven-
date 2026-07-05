<style>
    .vyt-setnav {
        display:flex; flex-wrap:wrap; gap:8px; margin-bottom:20px;
    }
    .vyt-setnav a {
        display:inline-block; padding:7px 14px; border-radius:999px;
        border:1px solid var(--line); background:#fff; color:inherit;
        text-decoration:none; font-size:13px; font-weight:600;
    }
    .vyt-setnav a:hover { border-color:var(--magenta); }
    .vyt-setnav a.active { background:var(--gradient); color:#fff; border-color:transparent; }
    .vyt-setnav .vyt-setnav-sep { flex-basis:100%; height:0; }

    .vyt-setform { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; }
    .vyt-setrow {
        display:grid; grid-template-columns:1fr; gap:6px;
        padding:16px 20px; border-bottom:1px solid var(--line);
    }
    @media (min-width:820px) { .vyt-setrow { grid-template-columns:minmax(220px,340px) 1fr; gap:24px; align-items:start; } }
    .vyt-setrow:last-of-type { border-bottom:0; }
    .vyt-setrow .vyt-setlabel { font-weight:600; font-size:14px; padding-top:8px; }
    .vyt-setrow .vyt-sethelp { display:block; font-weight:400; font-size:12px; color:var(--muted); margin-top:3px; }
    .vyt-setrow input[type=text], .vyt-setrow input[type=password], .vyt-setrow input[type=number],
    .vyt-setrow input[type=email], .vyt-setrow select, .vyt-setrow textarea {
        width:100%; max-width:520px; padding:9px 12px; border:1px solid var(--line);
        border-radius:8px; font-size:14px; background:var(--bg); outline:none; font-family:inherit;
    }
    .vyt-setrow textarea { min-height:90px; font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:13px; }
    .vyt-setrow input:focus, .vyt-setrow select:focus, .vyt-setrow textarea:focus { border-color:var(--magenta); background:#fff; }
    .vyt-seterror { color:#b91c1c; font-size:12px; margin-top:4px; }
    .vyt-setaffix { display:flex; align-items:center; gap:8px; max-width:520px; }
    .vyt-setaffix span { color:var(--muted); font-size:14px; font-weight:600; }

    .vyt-savebar {
        position:sticky; bottom:0; display:flex; justify-content:flex-end; align-items:center; gap:14px;
        padding:12px 20px; background:#fff; border-top:1px solid var(--line);
    }
    .vyt-savebar .vyt-lastchange { margin-right:auto; font-size:12px; color:var(--muted); }
    .vyt-savebar button {
        background:var(--gradient); color:#fff; border:0; padding:10px 26px;
        border-radius:999px; font-weight:600; font-size:14px; cursor:pointer;
    }
    .vyt-savebar button:disabled { opacity:.4; cursor:not-allowed; }

    .vyt-switch { position:relative; display:inline-block; width:42px; height:24px; vertical-align:middle; }
    .vyt-switch input { opacity:0; width:0; height:0; }
    .vyt-switch .vyt-slider {
        position:absolute; inset:0; border-radius:999px; background:#e2e8f0; transition:.15s; cursor:pointer;
    }
    .vyt-switch .vyt-slider:before {
        content:""; position:absolute; left:3px; top:3px; width:18px; height:18px;
        border-radius:50%; background:#fff; transition:.15s; box-shadow:0 1px 2px rgba(0,0,0,.25);
    }
    .vyt-switch input:checked + .vyt-slider { background:var(--magenta); }
    .vyt-switch input:checked + .vyt-slider:before { transform:translateX(18px); }

    .vyt-colform {
        display:grid; grid-template-columns:1fr 1fr; gap:12px;
        padding:16px 20px; border-top:1px solid var(--line); background:var(--bg);
    }
    @media (min-width:900px) { .vyt-colform { grid-template-columns:repeat(3,1fr); } }
    .vyt-colform label { font-size:12px; font-weight:600; display:block; margin-bottom:4px; }
    .vyt-colform input, .vyt-colform select, .vyt-colform textarea {
        width:100%; padding:8px 10px; border:1px solid var(--line); border-radius:8px;
        font-size:13px; background:#fff; outline:none; font-family:inherit;
    }
    .vyt-colform textarea { grid-column:1/-1; min-height:64px; font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; }
    .vyt-colform .full { grid-column:1/-1; }
    .vyt-colform .actions { grid-column:1/-1; display:flex; gap:10px; }
    .vyt-colform button {
        background:var(--gradient); color:#fff; border:0; padding:8px 20px;
        border-radius:999px; font-weight:600; font-size:13px; cursor:pointer;
    }
    .vyt-flag-on  { background:#ecfdf5; color:#047857; }
    .vyt-flag-off { background:#fee2e2; color:#b91c1c; }
</style>
