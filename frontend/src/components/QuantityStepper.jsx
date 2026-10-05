import { useId, useState } from 'react';
import { cn } from '../lib/utils';

const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

export function QuantityStepper({
  value,
  onChange,
  min = 0,
  max = Infinity,
  id,
  ariaLabel,
  decreaseLabel,
  increaseLabel,
  disabled = false,
  className,
}) {
  const generatedId = useId();
  const inputId = id ?? `stepper-input-${generatedId}`;
  const [draft, setDraft] = useState(String(value ?? min));
  const [isFocused, setIsFocused] = useState(false);
  const [prevValue, setPrevValue] = useState(value);

  // Sync the editable draft when the controlled value changes externally
  // (React "adjust state during render" pattern — no effect needed).
  if (!isFocused && value !== prevValue) {
    setPrevValue(value);
    setDraft(String(value ?? min));
  }

  const applyChange = (newValue) => {
    const clamped = clamp(newValue, min, max);
    setDraft(String(clamped));
    onChange?.(clamped);
  };

  const handleChange = (e) => {
    const raw = e.target.value;
    if (raw === '') {
      setDraft('');
      return;
    }
    const num = Number(raw);
    if (!Number.isNaN(num)) {
      setDraft(String(num));
    }
  };

  const commitDraft = () => {
    if (draft === '') {
      applyChange(min);
    } else {
      const num = Number(draft);
      if (!Number.isNaN(num)) {
        applyChange(num);
      } else {
        setDraft(String(value ?? min));
      }
    }
  };

  const handleBlur = () => {
    setIsFocused(false);
    commitDraft();
  };

  const handleFocus = () => {
    setIsFocused(true);
  };

  const handleKeyDown = (e) => {
    if (e.key === 'ArrowUp') {
      e.preventDefault();
      applyChange((value ?? min) + 1);
    } else if (e.key === 'ArrowDown') {
      e.preventDefault();
      applyChange((value ?? min) - 1);
    } else if (e.key === 'Enter') {
      commitDraft();
      e.target.blur?.();
    }
  };

  return (
    <div className={cn('inline-flex items-center gap-1', className)} role="group">
      <button
        type="button"
        onClick={() => applyChange((value ?? min) - 1)}
        disabled={disabled || (value ?? min) <= min}
        aria-label={decreaseLabel}
        className={cn(
          'flex h-8 w-8 items-center justify-center rounded-md border border-neutral-300 bg-white text-neutral-600 transition-colors hover:bg-neutral-50 disabled:cursor-not-allowed disabled:opacity-40'
        )}
      >
        -
      </button>
      <input
        id={inputId}
        type="number"
        value={draft}
        min={min}
        max={Number.isFinite(max) ? max : undefined}
        disabled={disabled}
        aria-label={ariaLabel}
        onChange={handleChange}
        onBlur={handleBlur}
        onFocus={handleFocus}
        onKeyDown={handleKeyDown}
        className={cn(
          'flex h-8 w-10 items-center justify-center rounded-md border border-neutral-300 bg-white text-center text-sm text-neutral-900 transition-colors focus:outline-none focus:ring-2 focus:ring-accent',
          disabled && 'cursor-not-allowed opacity-50'
        )}
      />
      <button
        type="button"
        onClick={() => applyChange((value ?? min) + 1)}
        disabled={disabled || (value ?? min) >= max}
        aria-label={increaseLabel}
        className={cn(
          'flex h-8 w-8 items-center justify-center rounded-md border border-neutral-300 bg-white text-neutral-600 transition-colors hover:bg-neutral-50 disabled:cursor-not-allowed disabled:opacity-40'
        )}
      >
        +
      </button>
    </div>
  );
}

export default QuantityStepper;
