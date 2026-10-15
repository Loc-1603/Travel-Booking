import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useMemo } from 'react';
import { api } from '../lib/api';

const WISHLIST_KEY = ['wishlist'];

// IDs arrive as strings from useParams but numbers from the API — normalize
// everywhere so add/remove/lookup never disagree (FX-6).
const normalizeId = (id) => Number(id);

function readItems(cached) {
  const raw = cached?.data?.data ?? cached?.data ?? [];
  return Array.isArray(raw) ? raw : [];
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

export function useWishlist(enabled = true) {
  const queryClient = useQueryClient();

  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: WISHLIST_KEY,
    queryFn: async () => {
      const res = await api.get('/wishlist');
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to load wishlist');
      return res.data;
    },
    enabled,
  });

  const items = useMemo(() => readItems(data), [data]);
  const wishlistHotelIds = useMemo(() => new Set(items.map((i) => normalizeId(i.hotel_id))), [items]);

  const addMutation = useMutation({
    mutationFn: async ({ hotelId, checkIn, checkOut }) => {
      const res = await api.post('/wishlist', {
        hotel_id: normalizeId(hotelId),
        ...(checkIn && { check_in: checkIn }),
        ...(checkOut && { check_out: checkOut }),
      });
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to add');
      return res.data;
    },
    // Optimistic fill: the heart turns solid immediately; a failed request
    // rolls back instead of leaving the UI stuck (FX-6).
    onMutate: async ({ hotelId }) => {
      await queryClient.cancelQueries({ queryKey: WISHLIST_KEY });
      const previous = queryClient.getQueryData(WISHLIST_KEY);
      const id = normalizeId(hotelId);
      queryClient.setQueryData(WISHLIST_KEY, (old) =>
        mapItems(old, (list) => (list.some((i) => normalizeId(i.hotel_id) === id) ? list : [...list, { hotel_id: id }]))
      );
      return { previous };
    },
    onError: (_err, _vars, context) => {
      if (context?.previous !== undefined) queryClient.setQueryData(WISHLIST_KEY, context.previous);
    },
    onSettled: () => queryClient.invalidateQueries({ queryKey: WISHLIST_KEY }),
  });

  const removeMutation = useMutation({
    mutationFn: async (hotelId) => {
      await api.delete(`/wishlist/${normalizeId(hotelId)}`);
    },
    // Optimistic unfill: symmetric to add (FX-6).
    onMutate: async (hotelId) => {
      await queryClient.cancelQueries({ queryKey: WISHLIST_KEY });
      const previous = queryClient.getQueryData(WISHLIST_KEY);
      const id = normalizeId(hotelId);
      queryClient.setQueryData(WISHLIST_KEY, (old) =>
        mapItems(old, (list) => list.filter((i) => normalizeId(i.hotel_id) !== id))
      );
      return { previous };
    },
    onError: (_err, _vars, context) => {
      if (context?.previous !== undefined) queryClient.setQueryData(WISHLIST_KEY, context.previous);
    },
    onSettled: () => queryClient.invalidateQueries({ queryKey: WISHLIST_KEY }),
  });

  return {
    wishlist: items,
    wishlistHotelIds,
    isInWishlist: (hotelId) => wishlistHotelIds.has(normalizeId(hotelId)),
    addToWishlist: addMutation.mutateAsync,
    removeFromWishlist: removeMutation.mutateAsync,
    addPending: addMutation.isPending,
    removePending: removeMutation.isPending,
    isLoading,
    isError,
    error,
    refetch,
  };
}
