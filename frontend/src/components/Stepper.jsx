import { useId } from 'react';
import { cn } from '../lib/utils';

/**
 * Progress stepper: ordered steps with the current step exposed
 * via aria-current="step". Labels collapse on small screens.
 */
export function Stepper({ steps, current = 0, className, ariaLabel }) {
  const stepsArray = Array.isArray(steps) ? steps : Object.values(steps);
  const id = useId();

  return (
    <ol className={cn('flex items-center gap-2 sm:gap-4', className)} aria-label={ariaLabel}>
      {stepsArray.map((step, index) => {
        const label = typeof step === 'string' ? step : step.label;
        const status = index < current ? 'done' : index === current ? 'active' : 'pending';
        const stepId = `${id}-step-${index}`;
        const isCurrent = index === current;

        return (
          <li
            key={stepId}
            aria-current={isCurrent ? 'step' : undefined}
            className="flex min-w-0 items-center gap-2"
          >
            <span
              id={stepId}
              data-testid="step-indicator"
              aria-hidden="true"
              className={cn(
                'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-medium transition-colors',
                status === 'done'
                  ? 'bg-success text-white'
                  : status === 'active'
                    ? 'bg-accent text-white'
                    : 'bg-neutral-200 text-neutral-600'
              )}
            >
              {status === 'done' ? '✓' : index + 1}
            </span>
            <span
              className={cn(
                'hidden truncate text-sm sm:block',
                isCurrent ? 'font-medium text-accent' : 'text-neutral-500'
              )}
            >
              {label}
            </span>
            {index < stepsArray.length - 1 && (
              <span aria-hidden="true" className="h-px w-4 flex-1 bg-neutral-200 sm:w-8" />
            )}
          </li>
        );
      })}
    </ol>
  );
}

export default Stepper;
