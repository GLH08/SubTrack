module.exports = {
  content: ['./public/calendar.php'],
  theme: {
    extend: {
      colors: {
        primary: '#111827',
        'primary-hover': '#374151',
        'background-light': '#f2f2f6',
        'card-light': '#ffffff',
        'text-secondary-light': '#6b7280',
      },
      fontFamily: {
        display: ['Inter', 'system-ui', 'sans-serif'],
        sans: ['Inter', 'system-ui', 'sans-serif'],
      },
      borderRadius: {
        xl: '0.75rem',
        '2xl': '1rem',
        '3xl': '1.5rem',
      },
      boxShadow: {
        soft: '0 4px 20px -2px rgba(0, 0, 0, 0.05)',
      },
    },
  },
};
