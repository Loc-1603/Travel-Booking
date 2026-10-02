import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, waitFor, act } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useWishlist } from './useWishlist';

const { apiGet, apiPost, apiDelete } = vi.hoisted(() => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiDelete: vi.fn(),
}));

vi.mock('../lib/api', () => ({
  api: { get: apiGet, post: apiPost, delete: apiDelete },
}));

function wrapper() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
  function Wrapper({ children }: { children: React.ReactNode }) {
    return <QueryClientProvider client={qc}>{children}</QueryClientProvider>;
  }
  return { Wrapper, qc };
}

const listEnvelope = (ids: number[]) => ({
  success: true,
  data: { data: ids.map((hotel_id) => ({ id: hotel_id, hotel_id })) },
});

beforeEach(() => {
  apiGet.mockReset();
  apiPost.mockReset();
  apiDelete.mockReset();
  // Stateful fake backend: POST adds, DELETE removes, GET reflects both.
  let saved = [7];
  apiGet.mockImplementation(() => Promise.resolve({ data: listEnvelope(saved) }));
  apiPost.mockImplementation((_url: string, body: { hotel_id: number }) => {
    saved = [...new Set([...saved, Number(body.hotel_id)])];
    return Promise.resolve({ data: { success: true, data: { id: 99, hotel_id: Number(body.hotel_id) } } });
  });
  apiDelete.mockImplementation((url: string) => {
    const id = Number(url.split('/').pop());
    saved = saved.filter((s) => s !== id);
    return Promise.resolve({ data: '' });
  });
});

describe('useWishlist (FX-6)', () => {
  it('reads ids from the envelope and matches string/number forms', async () => {
    const { Wrapper } = wrapper();
    const { result } = renderHook(() => useWishlist(true), { wrapper: Wrapper });
    await waitFor(() => expect(result.current.isLoading).toBe(false));
    expect(result.current.isInWishlist(7)).toBe(true);
    expect(result.current.isInWishlist('7')).toBe(true);
    expect(result.current.isInWishlist(8)).toBe(false);
  });

  it('toggles add → remove with immediate optimistic UI (no stuck heart)', async () => {
    const { Wrapper } = wrapper();
    const { result } = renderHook(() => useWishlist(true), { wrapper: Wrapper });
    await waitFor(() => expect(result.current.isLoading).toBe(false));
    expect(result.current.isInWishlist(9)).toBe(false);

    // add: heart fills immediately, API called with a numeric hotel_id
    await act(async () => {
      await result.current.addToWishlist({ hotelId: '9', checkIn: '2026-10-01', checkOut: '2026-10-03' });
    });
    expect(apiPost).toHaveBeenCalledWith('/wishlist', {
      hotel_id: 9,
      check_in: '2026-10-01',
      check_out: '2026-10-03',
    });
    await waitFor(() => expect(result.current.isInWishlist(9)).toBe(true));

    // remove: heart empties immediately (the reported FX-6 bug left it filled)
    await act(async () => {
      await result.current.removeFromWishlist('9');
    });
    expect(apiDelete).toHaveBeenCalledWith('/wishlist/9');
    await waitFor(() => expect(result.current.isInWishlist(9)).toBe(false));
  });

  it('rolls back the optimistic add when the API fails', async () => {
    apiPost.mockRejectedValueOnce(new Error('boom'));
    const { Wrapper } = wrapper();
    const { result } = renderHook(() => useWishlist(true), { wrapper: Wrapper });
    await waitFor(() => expect(result.current.isLoading).toBe(false));

    await act(async () => {
      await expect(result.current.addToWishlist({ hotelId: 42 })).rejects.toThrow('boom');
    });
    await waitFor(() => expect(result.current.isInWishlist(42)).toBe(false));
  });
});
