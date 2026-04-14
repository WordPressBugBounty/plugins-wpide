const path = require('path')
const js = require('@eslint/js')
const globals = require('globals')
const vue = require('eslint-plugin-vue')
const babelParser = require('@babel/eslint-parser')

const babelParserOptions = {
  requireConfigFile: false,
  babelOptions: {
    configFile: path.join(__dirname, 'babel.config.js'),
  },
}

module.exports = [
  {
    ignores: [
      'deploy/**',
      'dist/**',
      'freemius/**',
      'local_modules/**',
      'node_modules/**',
      'src/pricing/**',
      'vendor/**',
    ],
  },
  {
    ...js.configs.recommended,
    files: ['src/**/*.js'],
    languageOptions: {
      ...js.configs.recommended.languageOptions,
      ecmaVersion: 2018,
      sourceType: 'module',
      parser: babelParser,
      parserOptions: babelParserOptions,
      globals: {
        ajaxurl: 'readonly',
        ...globals.browser,
        ...globals.jquery,
        ...globals.node,
        jQuery: 'readonly',
      },
    },
  },
  ...vue.configs['flat/vue2-recommended'].map((config) => ({
    ...config,
    files: ['src/**/*.vue'],
    languageOptions: {
      ...config.languageOptions,
      ecmaVersion: 2018,
      sourceType: 'module',
      globals: {
        ajaxurl: 'readonly',
        ...globals.browser,
        ...globals.jquery,
        ...globals.node,
        ...(config.languageOptions?.globals || {}),
        jQuery: 'readonly',
      },
      parserOptions: {
        ...config.languageOptions?.parserOptions,
        ...babelParserOptions,
        parser: babelParser,
        ecmaVersion: 2018,
        sourceType: 'module',
      },
    },
  })),
  {
    files: ['src/**/*.{js,vue}'],
    rules: {
      'linebreak-style': ['error', 'unix'],
      'no-console': 'off',
      'no-prototype-builtins': 'off',
      'no-trailing-spaces': 'warn',
      'no-unused-vars': 'error',
      quotes: ['error', 'single'],
      semi: ['error', 'never'],
      'vue/attributes-order': 'off',
      'vue/first-attribute-linebreak': 'off',
      'vue/max-attributes-per-line': 'off',
      'vue/multi-word-component-names': 'off',
      'vue/no-mutating-props': 'off',
      'vue/no-reserved-component-names': 'off',
      'vue/no-v-text-v-html-on-component': 'off',
      'vue/require-prop-types': 'off',
    },
  },
]
