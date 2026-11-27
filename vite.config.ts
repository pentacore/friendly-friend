import inertia from '@inertiajs/vite';
import typefinder from '@pentacore/vite-plugin-laravel-typefinder';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';
import type { Plugin } from 'vite-plus';
import { invalidateTypeCache } from 'vue/compiler-sfc';

/**
 * Vue resolves `defineProps<PageProps['x']>()` from Typefinder's .d.ts files
 * and caches them, but those files aren't part of Vite's module graph, so a
 * regenerated type never invalidates the cache: new or changed page props
 * then compile to *no props at all*, silently, until the dev server restarts.
 * Clear the cache and reload whenever Typefinder writes its output.
 */
function refreshVueTypesOnTypefinderChange(): Plugin {
    return {
        name: 'refresh-vue-types-on-typefinder-change',
        apply: 'serve',
        configureServer(server) {
            const onChange = (file: string): void => {
                if (!file.includes('/resources/js/typefinder/')) {
                    return;
                }

                invalidateTypeCache(file);
                server.moduleGraph.invalidateAll();
                server.ws.send({ type: 'full-reload' });
            };

            server.watcher.on('change', onChange);
            server.watcher.on('add', onChange);
        },
    };
}

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
                bunny('Bricolage Grotesque', {
                    weights: [500, 700],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
        // Types are committed. Image builds have no database to read the
        // schema from, so generation is skipped there (see Dockerfile).
        typefinder(
            process.env.TYPEFINDER_SKIP_BUILD ? { buildCommand: false } : {},
        ),
        refreshVueTypesOnTypefinderChange(),
    ]),
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
            'resources/js/typefinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'composer.json',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
            'resources/js/typefinder/**',
            '.agents/**',
            '.claude/**',
            'AGENTS.md',
            'CLAUDE.md',
            'boost.json',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
});
