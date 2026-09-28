import { lazy } from 'react';

// Import the .jsx file DIRECTLY — importing through the barrel (index.js)
// would statically pull the whole module graph into the main chunk.
export const LazyRoomFilterPanel = lazy(() =>
  import('./RoomFilterPanel.jsx').then((m) => ({ default: m.RoomFilterPanel }))
);
