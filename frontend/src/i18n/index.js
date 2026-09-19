import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';
import en from './locales/en.json';
import vi from './locales/vi.json';

export const isAdminRoute = () => {
  const path = window.location.pathname;
  return (
    path.startsWith('/admin') ||
    path.startsWith('/profile') ||
    path.startsWith('/bookings') ||
    path.startsWith('/wishlist') ||
    path.startsWith('/support') ||
    path.startsWith('/book') ||
    path.startsWith('/checkout') ||
    path.startsWith('/hotels')
  );
};

i18n
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    resources: {
      en: { translation: en },
      vi: { translation: vi },
    },
    lng: isAdminRoute() ? 'vi' : undefined,
    fallbackLng: 'en',
    interpolation: { escapeValue: false },
    detection: {
      order: ['localStorage', 'navigator'],
      caches: ['localStorage'],
      lookupLocalStorage: 'i18nextLng',
    },
    react: { useSuspense: false },
  });

i18n.on('languageChanged', (lng) => {
  const htmlLang = lng?.startsWith('vi') ? 'vi-VN' : 'en-US';
  if (typeof document !== 'undefined') {
    document.documentElement.lang = htmlLang;
  }
});

if (typeof document !== 'undefined') {
  const initialLang = i18n.language?.startsWith('vi') ? 'vi-VN' : 'en-US';
  document.documentElement.lang = initialLang;
}

export default i18n;