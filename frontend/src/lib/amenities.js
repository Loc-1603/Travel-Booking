/**
 * Resolves a display label for an amenity from i18n by slug,
 * falling back to the API-provided name (or the slug itself).
 * Pass `t = null` for components without i18n (hardcoded-Vietnamese UI):
 * the API-provided name is used directly.
 */
export function getAmenityLabel(t, amenity) {
  if (!amenity) return '';
  const slug = typeof amenity === 'string' ? amenity : amenity.slug;
  const name = typeof amenity === 'string' ? amenity : amenity.name;
  if (!slug) return name || '';
  if (!t) return name || slug;
  return t(`amenities.${slug}`, { defaultValue: name || slug });
}
