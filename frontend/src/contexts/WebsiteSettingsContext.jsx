import { useQuery } from '@tanstack/react-query';
import { api } from '../lib/api';
import { WebsiteSettingsContext, defaultSettings } from './useWebsiteSettings';

export function WebsiteSettingsProvider({ children }) {
  const { data, isLoading } = useQuery({
    queryKey: ['website-settings'],
    queryFn: async () => {
      const res = await api.get('/website-settings');
      if (!res.data?.success) throw new Error('Failed to load website settings');
      return res.data.data;
    },
    staleTime: 5 * 60 * 1000, // 5 minutes
    retry: 1,
  });

  const settings = data ?? defaultSettings;

  return (
    <WebsiteSettingsContext.Provider value={{ settings, isLoading }}>
      {children}
    </WebsiteSettingsContext.Provider>
  );
}
