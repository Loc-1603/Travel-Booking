import { useTranslation } from 'react-i18next';
import { cn } from '../lib/utils';

function formatCurrency(price, currency = 'VND') {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency,
    minimumFractionDigits: 0,
  }).format(price);
}

export function TourPriceSummary({
  subtotal = 0,
  transport = 0,
  addOn = 0,
  discount = 0,
  tax = 0,
  total,
  currency = 'VND',
  labels,
  className,
  showTax = true,
  compact = false,
}) {
  const { t } = useTranslation();
  const computedTotal = total ?? subtotal + transport + addOn - discount + (showTax ? tax : 0);
  const isVND = currency === 'VND';

  const rows = [];

  if (subtotal > 0) {
    rows.push({
      label: labels?.subtotal ?? (isVND ? t('tours.price.subtotal') : t('common.price')),
      value: formatCurrency(subtotal, currency),
    });
  }
  if (addOn > 0) {
    rows.push({
      label: labels?.addOn ?? t('tours.price.addon'),
      value: formatCurrency(addOn, currency),
    });
  }
  if (transport > 0) {
    rows.push({
      label: labels?.transport ?? t('tours.price.transport'),
      value: formatCurrency(transport, currency),
    });
  }
  if (discount > 0) {
    rows.push({
      label: labels?.discount ?? t('tours.price.discount'),
      value: `-${formatCurrency(discount, currency)}`,
      negative: true,
    });
  }
  if (showTax && tax > 0) {
    rows.push({
      label: labels?.tax ?? t('tours.price.tax'),
      value: formatCurrency(tax, currency),
    });
  }

  return (
    <div className={cn('space-y-2', className)} aria-label={t('tours.price.summary')}>
      {rows.map((row, i) => (
        <div key={i} className={cn('flex justify-between text-sm', row.negative && 'text-error')}>
          <span className="text-neutral-600">{row.label}</span>
          <span className="font-medium text-neutral-900">{row.value}</span>
        </div>
      ))}
      {!compact && (
        <div className="flex justify-between border-t border-neutral-200 pt-2 text-lg font-semibold">
          <span className="text-neutral-700">{labels?.total ?? t('tours.price.total')}</span>
          <span className="text-accent">{formatCurrency(computedTotal, currency)}</span>
        </div>
      )}
    </div>
  );
}

export default TourPriceSummary;