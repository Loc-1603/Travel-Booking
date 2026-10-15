import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useMemo } from 'react';
import { api } from '../lib/api';

const SAVED_GUIDES_KEY = ['saved-guides'];

// Guide identity is the provider uuid (string). Normalize everywhere so
// add/remove/lookup never disagree on type or whitespace.
const normalizeUuid = (uuid) => String(uuid ?? '');

function readItems(cached) {
  const raw = cached?.data?.data ?? cached?.data ?? [];
  return Array.isArray(raw) ? raw : [];
}

function itemUuid(item) {
  return normalizeUuid(item?.guide?.uuid ?? item?.provider_uuid);
}

// Apply fn(list) while preserving the { success, data: { data: [...] } } envelope.
function mapItems(cached, fn) {
  const data = cached?.data;
  if (Array.isArray(data)) return { ...cached, data: fn(data) };
  if (data && typeof data === 'object' && Array.isArray(data.data)) {
    return { ...cached, data: { ...data, data: fn(data.data) } };
  }
  return { ...(cached && typeof cached === 'object' ? cached : {}), success: true, data: { data: fn([]) } };
}

export function useSavedGuides(enabled = true) {
  const queryClient = useQueryClient();

  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: SAVED_GUIDES_KEY,
    queryFn: async () => {
      const res = await api.get('/saved-guides');
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to load saved guides');
      return res.data;
    },
    enabled,
  });

  const items = useMemo(() => readItems(data), [data]);
  const savedGuideUuids = useMemo(() => new Set(items.map(itemUuid).filter(Boolean)), [items]);

  const addMutation = useMutation({
    mutationFn: async (providerUuid) => {
      const res = await api.post('/saved-guides', { provider_uuid: normalizeUuid(providerUuid) });
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to save guide');
      return res.data;
    },
    // Optimistic fill: the heart turns solid immediately; a failed request
    // rolls back instead of leaving the UI stuck (same pattern as FX-6).
    onMutate: async (providerUuid) => {
      await queryClient.cancelQueries({ queryKey: SAVED_GUIDES_KEY });
      const previous = queryClient.getQueryData(SAVED_GUIDES_KEY);
      const uuid = normalizeUuid(providerUuid);
      queryClient.setQueryData(SAVED_GUIDES_KEY, (old) =>
        mapItems(old, (list) => (list.some((i) => itemUuid(i) === uuid) ? list : [...list, { provider_uuid: uuid }]))
      );
      return { previous };
    },
    onError: (_err, _vars, context) => {
      if (context?.previous !== undefined) queryClient.setQueryData(SAVED_GUIDES_KEY, context.previous);
    },
    onSettled: () => queryClient.invalidateQueries({ queryKey: SAVED_GUIDES_KEY }),
  });

  const removeMutation = useMutation({
    mutationFn: async (providerUuid) => {
      await api.delete(`/saved-guides/${normalizeUuid(providerUuid)}`);
    },
    // Optimistic unfill: symmetric to add.
    onMutate: async (providerUuid) => {
      await queryClient.cancelQueries({ queryKey: SAVED_GUIDES_KEY });
      const previous = queryClient.getQueryData(SAVED_GUIDES_KEY);
      const uuid = normalizeUuid(providerUuid);
      queryClient.setQueryData(SAVED_GUIDES_KEY, (old) =>
        mapItems(old, (list) => list.filter((i) => itemUuid(i) !== uuid))
      );
      return { previous };
    },
    onError: (_err, _vars, context) => {
      if (context?.previous !== undefined) queryClient.setQueryData(SAVED_GUIDES_KEY, context.previous);
    },
    onSettled: () => queryClient.invalidateQueries({ queryKey: SAVED_GUIDES_KEY }),
  });

  return {
    savedGuides: items,
    savedGuideUuids,
    isSaved: (uuid) => savedGuideUuids.has(normalizeUuid(uuid)),
    saveGuide: addMutation.mutateAsync,
    unsaveGuide: removeMutation.mutateAsync,
    addPending: addMutation.isPending,
    removePending: removeMutation.isPending,
    isLoading,
    isError,
    error,
    refetch,
  };
}
