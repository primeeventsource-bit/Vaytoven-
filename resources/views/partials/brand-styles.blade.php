{{--
  Shared brand CSS — pulled into every public-facing branded layout.
  CSS custom properties + the V-pin gradient + the basic typography stack
  live here so every page reads from a single source of truth.
--}}
<style>
    :root {
        --pink:#FF3D8A; --magenta:#D63384; --purple:#7B2CBF;
        --gradient: linear-gradient(135deg,#FF3D8A 0%,#D63384 50%,#7B2CBF 100%);
        --ink:#1d1f21; --muted:#6b7280; --line:#e7e5e4; --bg:#fafaf9;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family:'Geist',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
        color: var(--ink); background: var(--bg); line-height: 1.55;
        -webkit-font-smoothing: antialiased;
    }
    a { color: var(--purple); text-decoration: none; }
    a:hover { text-decoration: underline; }
</style>
