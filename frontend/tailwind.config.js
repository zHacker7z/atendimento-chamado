/** @type {import('tailwindcss').Config} */
export default {
  content: ["./index.html", "./src/**/*.{js,ts,jsx,tsx,vue}"],
  theme: {
    extend: {
      colors: {
        slate: {
          900: "#0f1117",
          800: "#181c27",
          700: "#1f2437",
          600: "#2a2f42",
          500: "#131620",
          400: "#4a5270",
          300: "#6b7fad",
          200: "#9ba8c9",
          100: "#c5cde0",
          50: "#e8ecf5",
        },
        accent: {
          warning: "#f5a623",
          danger: "#f06292",
          info: "#60a5fa",
          success: "#4ade80",
        },
      },
    },
  },
  plugins: [],
};
