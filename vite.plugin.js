import { resolve } from 'path';

export default function AiPlugin() {
    const pluginPath = resolve(__dirname);

    return {
        name: 'ai-plugin',
        config: () => ({
            build: {
                rollupOptions: {
                    input: {
                        'ai': resolve(pluginPath, 'resources/js/app.js'),
                    },
                    output: {
                        entryFileNames: 'js/[name].js',
                        chunkFileNames: 'js/[name].js',
                        assetFileNames: (assetInfo) => {
                            if (assetInfo.name.endsWith('.css')) {
                                return 'css/[name][extname]';
                            }
                            return 'assets/[name][extname]';
                        },
                    },
                },
                outDir: resolve(pluginPath, 'dist'),
                emptyOutDir: true,
            },
        }),
    };
}
