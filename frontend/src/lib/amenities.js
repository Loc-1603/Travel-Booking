/**
 * Resolves a display label for an amenity from i18n by slug,
 * falling back to the API-provided name (or the slug itself).
 */
export function getAmenityLabel(t, amenity) {
  if (!amenity) return '';
  const slug = typeof amenity === 'string' ? amenity : amenity.slug;
  const name = typeof amenity === 'string' ? amenity : amenity.name;
  if (!slug) return name || '';
  return t(`amenities.${slug}`, { defaultValue: name || slug });
}
