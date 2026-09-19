import { createContext, useContext } from 'react';

export const defaultSettings = {
  site_name: 'Hotel Booking',
  site_description: 'Find and book the perfect hotel for your next trip',
  site_logo: null,
  site_favicon: null,
  site_email: null,
  site_phone: null,
  site_address: null,
  social_links: {},
  meta_title: 'Hotel Booking',
  meta_description: 'Find and book the perfect hotel for your next trip',
  meta_keywords: 'hotel, booking, travel, accommodation',
};

export const WebsiteSettingsContext = createContext(defaultSettings);

export function useWebsiteSettings() {
  const context = useContext(WebsiteSettingsContext);
  if (!context) {
    return { settings: defaultSettings, isLoading: false };
  }
  return context;
}