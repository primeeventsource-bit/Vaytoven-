#!/usr/bin/env node
/**
 * Build the self-contained admin console.
 *
 * Reads:  admin/index.html        (JSX source)
 * Writes: admin/prebuilt.html     (single self-contained file)
 *
 * Setup:  npm install
 * Run:    node build.js
 *
 * The prebuilt file uses an in-memory mock backend by default.
 * To point it at the real API, see "Switching to the production API"
 * in admin/README.md.
 */

const fs   = require('fs');
const path = require('path');
const { transformSync } = require('@babel/core');

const SRC = path.join(__dirname, 'index.html');
const OUT = path.join(__dirname, 'prebuilt.html');

function resolveReactUmd() {
  const tryPaths = [
    path.join(__dirname, 'node_modules/react/umd/react.production.min.js'),
    path.join(__dirname, '../node_modules/react/umd/react.production.min.js'),
  ];
  for (const p of tryPaths) {
    if (fs.existsSync(p)) {
      return {
        react:    fs.readFileSync(p, 'utf8'),
        reactDom: fs.readFileSync(p.replace('react/umd/react.production.min.js',
                                             'react-dom/umd/react-dom.production.min.js'), 'utf8'),
      };
    }
  }
  throw new Error('Could not find React UMD bundles. Run `npm install` first.');
}

const { react: REACT_UMD, reactDom: REACTDOM_UMD } = resolveReactUmd();
const html = fs.readFileSync(SRC, 'utf8');

const jsxMatch = html.match(/<script type="text\/babel"[^>]*>([\s\S]*?)<\/script>/);
if (!jsxMatch) throw new Error('Could not find <script type="text/babel"> block in index.html');
const jsxSrc = jsxMatch[1];
console.log('JSX source:', jsxSrc.length, 'chars');

const { code: compiledJs } = transformSync(jsxSrc, {
  presets: [
    ['@babel/preset-env',   { targets: '> 0.5%, last 2 versions, not dead, not IE 11' }],
    ['@babel/preset-react', { runtime: 'classic' }],
  ],
  babelrc:    false,
  configFile: false,
});
console.log('Compiled JS:', compiledJs.length, 'chars');

let out = html;
out = out.replace(/<script src="https:\/\/unpkg\.com\/[^"]+"[^>]*><\/script>\s*/g, '');
out = out.replace(/<script type="text\/babel"[^>]*>[\s\S]*?<\/script>/, '');

const inlineScript = `
<script>
${REACT_UMD}
</script>
<script>
${REACTDOM_UMD}
</script>
<script>
${compiledJs}
</script>
`;
out = out.replace('</body>', inlineScript + '\n</body>');

fs.writeFileSync(OUT, out);
console.log('Wrote:', path.relative(process.cwd(), OUT), '—', (fs.statSync(OUT).size / 1024).toFixed(1), 'KB');
