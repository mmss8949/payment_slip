const { defineConfig } = require('@vue/cli-service')
module.exports = defineConfig({
  transpileDependencies: [
    'vuetify'
  ],
  // project deployment base
  publicPath: '',
  // where to output built files
  outputDir: 'dist',
  // where to put static assets (js/css/img/font/...)
  assetsDir: 'paymentSlip/tools',//assetsDir: 'swap-attachment',
  // filename for index.html (relative to outputDir)
  indexPath: 'paymentSlip.html',
})
