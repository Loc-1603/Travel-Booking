import { useState } from 'react';

/**
 * Guide avatar — same look as admin/users: grey circle with the first
 * letter of the business name when there is no photo (or it fails to load).
 */
export default function GuideAvatar({ name, avatarUrl, size = 64, className = '' }) {
  const [failed, setFailed] = useState(false);
  const initial = name?.trim()?.charAt(0)?.toUpperCase() || '?';
  const style = {
    width: size,
    height: size,
    fontSize: Math.max(12, Math.round(size * 0.4)),
  };

  if (avatarUrl && !failed) {
    return (
      <img
        src={avatarUrl}
        alt={name || ''}
        className={`rounded-full object-cover shrink-0 ${className}`}
        width={size}
        height={size}
        style={style}
        onError={() => setFailed(true)}
      />
    );
  }

  return (
    <span
      className={`rounded-full flex items-center justify-center font-semibold shrink-0 bg-[#6c757d] text-white ${className}`}
      style={style}
      aria-hidden
    >
      {initial}
    </span>
  );
}
