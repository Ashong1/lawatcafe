import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    build: {
        // Vite 7 defaults to 'baseline-widely-available' (~Chrome 107), which
        // leaves optional chaining and nullish coalescing in the output. The
        // guest portal is opened by whatever phone walks into the shop, often
        // through a captive-portal WebView that is years behind the OS — and a
        // single unsupported token is a SyntaxError that throws away the entire
        // bundle, so Alpine never starts. Transpiling down costs a little size
        // and buys back every pre-2020 device.
        //
        // A bare syntax floor rather than a browser list on purpose. Naming any
        // `safari` version makes esbuild try to work around that engine's
        // destructuring bug, which it cannot actually do — the build dies with
        // "Transforming destructuring to the configured target environment is
        // not supported yet". es2019 is the lowest clean floor and it drops the
        // ES2020 tokens that were doing the damage.
        //
        // This is a ceiling on how far back we can go, not a guarantee: Alpine 3
        // needs Proxy, which cannot be polyfilled at any target. Phones older
        // than roughly 2019 get no Alpine no matter what we emit, so the portal
        // has to stand up as plain HTML — which is why the sign-in form must
        // never be hidden behind x-cloak.
        target: 'es2019',
    },
});
