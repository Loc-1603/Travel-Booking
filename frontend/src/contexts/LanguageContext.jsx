import { createContext, useContext, useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { useWebsiteSettings } from './WebsiteSettingsContext';
import { isAdminRoute } from '../i18n';

const LanguageContext = createContext(null);

export function LanguageProvider({ children }) {
  const { settings, isLoading } = useWebsiteSettings();
  const { i18n } = useTranslation();
  const syncedRef = useRef(false);

  // Sync language with backend settings once on mount (skip for admin routes - they use Vietnamese)
  useEffect(() => {
    if (!isLoading && settings?.locale && !syncedRef.current && !isAdminRoute()) {
      const backendLocale = settings.locale;
      const currentLocale = i18n.language;

      if (backendLocale && backendLocale !== currentLocale) {
        i18n.changeLanguage(backendLocale);
      }
      syncedRef.current = true;
    }
  }, [settings, isLoading, i18n]);

  // isReady is true when not loading and sync is complete
  const isReady = !isLoading && syncedRef.current;

  const changeLanguage = (lng) => {
    i18n.changeLanguage(lng);
    localStorage.setItem('i18nextLng', lng);
  };

  const currentLanguage = i18n.language;

  return (
    <LanguageContext.Provider value={{ changeLanguage, currentLanguage, isReady }}>
      {children}
    </LanguageContext.Provider>
  );
}

export function useLanguage() {
  const context = useContext(LanguageContext);
  if (!context) {
    throw new Error('useLanguage must be used within a LanguageProvider');
  }
  return context;
}