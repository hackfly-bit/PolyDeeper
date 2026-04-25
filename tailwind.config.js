/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
  ],
  theme: {
    extend: {
      colors: {
        'brand-50': '#f0f7ff',
        'brand-100': '#e0effe',
        'brand-500': '#0055ff',
        'brand-600': '#0044cc',
        'brand-700': '#003399',
        'brand-900': '#001a4d',
        'dark-bg': '#030303',
        'dark-surface': '#0a0a0a',
        'dark-border': '#1a1a1a',
      },
      fontFamily: {
        heading: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
        sans: ['"Inter"', 'system-ui', 'sans-serif'],
        mono: ['"Space Mono"', 'monospace'],
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
  ],
}