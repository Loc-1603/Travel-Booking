// Fire-and-forget chunk preload; the dynamic import is cached by the module system,
// so when Suspense later suspends on the same spec the chunk is already in flight.
export function preloadRoomDetailModal() {
  return import('./RoomDetailModal.jsx');
}
