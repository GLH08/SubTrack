module.exports = {
  content: [
    './public/dashboard.php',
    './public/calendar.php',
    './public/subscriptions.php',
    './public/settings.php',
    './public/stats.php',
    './public/profile.php',
    './public/subscription.php',
    './public/add-subscription.php',
    './public/edit-subscription.php',
    './public/login.php',
    './public/forgot-password.php',
    './public/reset-password.php',
  ],
  theme: {
    extend: {
      colors: {
        primary: '#111827',
        'primary-hover': '#374151',
        'background-light': '#f2f2f6',
        'card-light': '#ffffff',
        'text-secondary-light': '#6b7280',
        'brand-blue': '#3b82f6',
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
        subtle: '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
        card: '0 10px 40px -5px rgba(0, 0, 0, 0.08)',
      },
    },
  },
};
