import { useRef, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { useWebsiteSettings } from './useWebsiteSettings';
import { LanguageContext } from './useLanguage';
import { isAdminRoute } from '../i18n';

// Marks that the visitor explicitly picked a language, so the backend default
// must not override it on the next full page load (e.g. after using an email link).
const LANGUAGE_CHOICE_KEY = 'i18nextLngChosen';

function hasExplicitLanguageChoice() {
  try {
    return localStorage.getItem(LANGUAGE_CHOICE_KEY) === '1';
  } catch {
    return false;
  }
}

export function LanguageProvider({ children }) {
  const { settings, isLoading } = useWebsiteSettings();
  const { i18n } = useTranslation();
  const syncedRef = useRef(false);

  // Sync language with backend settings once on mount (skip for admin routes - they use Vietnamese)
  useEffect(() => {
    if (isLoading || !settings?.locale || syncedRef.current || isAdminRoute()) return;
    syncedRef.current = true;

    // The visitor's own choice wins over the site default.
    if (hasExplicitLanguageChoice()) return;

    const backendLocale = settings.locale;
    if (backendLocale !== i18n.language) {
      i18n.changeLanguage(backendLocale);
    }
  }, [settings, isLoading, i18n]);

  // isReady is true when not loading and sync is complete
  const isReady = !isLoading && !isAdminRoute() && !!settings?.locale;

  const changeLanguage = (lng) => {
    i18n.changeLanguage(lng);
    try {
      localStorage.setItem('i18nextLng', lng);
      localStorage.setItem(LANGUAGE_CHOICE_KEY, '1');
    } catch {
      // Storage can be unavailable (private mode); language still changes for this session.
    }
  };

  const currentLanguage = i18n.language;

  return (
    <LanguageContext.Provider value={{ changeLanguage, currentLanguage, isReady }}>
      {children}
    </LanguageContext.Provider>
  );
}