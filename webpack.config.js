import Encore from '@symfony/webpack-encore';

// S'assure que Node/Babel sait qu'on compile pour la production
if (Encore.isProduction()) {
    process.env.NODE_ENV = 'production';
}

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .addEntry('member_app', './assets/member_app.jsx')
    .splitEntryChunks()
    .enableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())

    // On désactive le preset React automatique d'Encore pour le configurer manuellement sans conflit
    .configureBabel((babelConfig) => {
        // Nettoyage de tout preset React préexistant
        babelConfig.presets = babelConfig.presets.filter(
            preset => !(Array.isArray(preset) && preset[0].includes('preset-react'))
        );

        // Ajout du preset avec l'environnement exact
        babelConfig.presets.push([
            '@babel/preset-react',
            {
                runtime: 'automatic',
                development: !Encore.isProduction(),
            }
        ]);
    })
;

export default Encore.getWebpackConfig();