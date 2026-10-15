import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, waitFor, act } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useSavedGuides } from './useSavedGuides';

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

const listEnvelope = (uuids: string[]) => ({
  success: true,
  data: { data: uuids.map((uuid, i) => ({ id: i + 1, provider_uuid: uuid, guide: { uuid } })) },
});

beforeEach(() => {
  apiGet.mockReset();
  apiPost.mockReset();
  apiDelete.mockReset();
  // Stateful fake backend: POST adds, DELETE removes, GET reflects both.
  let saved = ['g-1'];
  apiGet.mockImplementation(() => Promise.resolve({ data: listEnvelope(saved) }));
  apiPost.mockImplementation((_url: string, body: { provider_uuid: string }) => {
    saved = [...new Set([...saved, String(body.provider_uuid)])];
    return Promise.resolve({ data: { success: true, data: { id: 99, guide: { uuid: body.provider_uuid } } } });
  });
  apiDelete.mockImplementation((url: string) => {
    const uuid = url.split('/').pop();
    saved = saved.filter((s) => s !== uuid);
    return Promise.resolve({ data: '' });
  });
});

describe('useSavedGuides', () => {
  it('reads guide uuids from the envelope', async () => {
    const { Wrapper } = wrapper();
    const { result } = renderHook(() => useSavedGuides(true), { wrapper: Wrapper });
    await waitFor(() => expect(result.current.isLoading).toBe(false));
    expect(result.current.isSaved('g-1')).toBe(true);
    expect(result.current.isSaved('g-2')).toBe(false);
  });

  it('toggles add → remove with immediate optimistic UI (no stuck heart)', async () => {
    const { Wrapper } = wrapper();
    const { result } = renderHook(() => useSavedGuides(true), { wrapper: Wrapper });
    await waitFor(() => expect(result.current.isLoading).toBe(false));
    expect(result.current.isSaved('g-2')).toBe(false);

    // add: heart fills immediately, API called with the provider uuid
    await act(async () => {
      await result.current.saveGuide('g-2');
    });
    expect(apiPost).toHaveBeenCalledWith('/saved-guides', { provider_uuid: 'g-2' });
    await waitFor(() => expect(result.current.isSaved('g-2')).toBe(true));

    // remove: heart empties immediately
    await act(async () => {
      await result.current.unsaveGuide('g-2');
    });
    expect(apiDelete).toHaveBeenCalledWith('/saved-guides/g-2');
    await waitFor(() => expect(result.current.isSaved('g-2')).toBe(false));
  });

  it('rolls back the optimistic save when the API fails', async () => {
    apiPost.mockRejectedValueOnce(new Error('boom'));
    const { Wrapper } = wrapper();
    const { result } = renderHook(() => useSavedGuides(true), { wrapper: Wrapper });
    await waitFor(() => expect(result.current.isLoading).toBe(false));

    await act(async () => {
      await expect(result.current.saveGuide('g-42')).rejects.toThrow('boom');
    });
    await waitFor(() => expect(result.current.isSaved('g-42')).toBe(false));
  });
});
