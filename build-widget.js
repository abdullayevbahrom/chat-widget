// build-widget.js - esbuild bilan widget.js bundle
import * as esbuild from 'esbuild';

await esbuild.build({
  entryPoints: ['resources/js/widget.js'],
  bundle: true,
  outfile: 'public/js/widget.js',
  format: 'iife',
  globalName: 'ChatWidgetSDK',
  minify: true,
  sourcemap: false,
  target: ['es2020'],
});

console.log('✅ Widget.js built successfully with esbuild');
