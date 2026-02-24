/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: ['./index.html', './src/**/*.{js,jsx}'],
  theme: {
    extend: {
      colors: {
        brand: {
          500: '#2563eb',
          600: '#1d4ed8'
        }
      }
    }
  },
  plugins: []
};
