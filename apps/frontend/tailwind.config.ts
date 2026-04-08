/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        primary: "#2563eb",
        secondary: "#64748b",
        danger: "#ef4444",
        success: "#10b981",
        warning: "#f59e0b",
        info: "#0ea5e9",
      },
    },
  },
  plugins: [],
}
