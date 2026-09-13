import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useMemo } from 'react';
import { api } from '../lib/api';

export function useSavedTours(enabled = true) {
  const queryClient = useQueryClient();

  const { data, isLoading } = useQuery({
    queryKey: ['saved-tours'],
    queryFn: async () => {
      const res = await api.get('/saved-tours');
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to load saved tours');
      return res.data;
    },
    enabled,
  });

  const items = data?.data?.data ?? data?.data ?? [];
  const savedTourIds = useMemo(() => {
    const list = data?.data?.data ?? data?.data ?? [];
    return new Set(list.map((i) => Number(i.tour_id ?? i.tour?.id)).filter(Boolean));
  }, [data]);

  const addMutation = useMutation({
    mutationFn: async (tourId) => {
      const res = await api.post('/saved-tours', { tour_id: Number(tourId) });
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to save');
      return res.data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['saved-tours'] }),
  });

  const removeMutation = useMutation({
    mutationFn: async (tourId) => {
      await api.delete(`/saved-tours/${tourId}`);
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['saved-tours'] }),
  });

  return {
    savedTours: items,
    savedTourIds,
    isSaved: (tourId) => savedTourIds.has(Number(tourId)),
    saveTour: addMutation.mutateAsync,
    unsaveTour: removeMutation.mutateAsync,
    addPending: addMutation.isPending,
    removePending: removeMutation.isPending,
    isLoading,
  };
}
