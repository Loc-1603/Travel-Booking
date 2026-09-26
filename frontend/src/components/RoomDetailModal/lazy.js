import { lazy } from 'react';

// Import the .jsx file DIRECTLY — importing through the barrel (index.js)
// would statically pull the whole module graph into the main chunk.
export const LazyRoomDetailModal = lazy(() =>
  import('./RoomDetailModal.jsx').then((m) => ({ default: m.RoomDetailModal }))
);
