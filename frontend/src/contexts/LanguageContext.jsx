import { useRef, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { useWebsiteSettings } from './useWebsiteSettings';
import { LanguageContext } from './useLanguage';
import { isAdminRoute } from '../i18n';

export function LanguageProvider({ children }) {
  const { settings, isLoading } = useWebsiteSettings();
  const { i18n } = useTranslation();
  const syncedRef = useRef(false);

  // Sync language with backend settings once on mount (skip for admin routes - they use Vietnamese)
  useEffect(() => {
    if (isLoading || !settings?.locale || syncedRef.current || isAdminRoute()) return;
    syncedRef.current = true;
    const backendLocale = settings.locale;
    if (backendLocale !== i18n.language) {
      i18n.changeLanguage(backendLocale);
    }
  }, [settings, isLoading, i18n]);

  // isReady is true when not loading and sync is complete
  const isReady = !isLoading && !isAdminRoute() && !!settings?.locale;

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