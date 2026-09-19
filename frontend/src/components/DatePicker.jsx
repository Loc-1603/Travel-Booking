import { useEffect, useRef } from 'react';
import flatpickr from 'flatpickr';
import { useTranslation } from 'react-i18next';
import { Vietnamese } from 'flatpickr/dist/esm/l10n/vn.js';
import 'flatpickr/dist/flatpickr.min.css';

function pickLocale(language) {
  return language?.toLowerCase().startsWith('vi') ? Vietnamese : 'default';
}

function toISODate(d) {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

/**
 * Locale-aware date picker built on flatpickr. The calendar language follows
 * the active i18n language (Vietnamese when vi, English otherwise) and updates
 * live when the user switches language.
 */
export function DatePicker({ value, onChange, min, max, className, ariaLabel, ...rest }) {
  const inputRef = useRef(null);
  const fpRef = useRef(null);
  const onChangeRef = useRef(onChange);
  onChangeRef.current = onChange;
  const { i18n } = useTranslation();

  useEffect(() => {
    const fp = flatpickr(inputRef.current, {
      dateFormat: 'Y-m-d',
      locale: pickLocale(i18n.language),
      allowInput: true,
      onChange: (selectedDates) => {
        const val = selectedDates.length ? toISODate(selectedDates[0]) : '';
        onChangeRef.current?.(val);
      },
    });
    fpRef.current = fp;
    return () => {
      fp.destroy();
      fpRef.current = null;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    const fp = fpRef.current;
    if (fp) fp.set('locale', pickLocale(i18n.language));
  }, [i18n.language]);

  useEffect(() => {
    const fp = fpRef.current;
    if (fp) fp.set('minDate', min || undefined);
  }, [min]);

  useEffect(() => {
    const fp = fpRef.current;
    if (fp) fp.set('maxDate', max || undefined);
  }, [max]);

  useEffect(() => {
    const fp = fpRef.current;
    if (!fp) return;
    if (value) {
      if (fp.input.value !== value) {
        fp.setDate(value, false);
      }
    } else if (fp.input.value) {
      fp.clear();
    }
  }, [value]);

  return (
    <input
      ref={inputRef}
      type="text"
      className={className}
      aria-label={ariaLabel}
      {...rest}
    />
  );
}