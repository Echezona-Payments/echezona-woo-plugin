const defaultConfig = require("@wordpress/scripts/config/webpack.config");

module.exports = {
  ...defaultConfig,
  entry: {
    index: "./src/index.js",
    blocks: "./src/blocks.js",
  },
  output: {
    ...defaultConfig.output,
    path: __dirname + "/build",
  },
  plugins: [
    ...defaultConfig.plugins.filter(
      (plugin) =>
        plugin.constructor.name !== "DependencyExtractionWebpackPlugin"
    ),
  ],
};
