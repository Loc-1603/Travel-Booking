import axe from 'axe-core';
import type { Result } from 'axe-core';

/**
 * Runs axe-core (WCAG 2.x A/AA rules) against a rendered container.
 * jsdom has no layout engine, so purely visual rules (color-contrast, etc.)
 * come back "incomplete" rather than as violations — what remains here are
 * structural issues: names, roles, labels, aria validity.
 */
export async function collectAxeViolations(container: HTMLElement): Promise<Result[]> {
  const results = await axe.run(container);
  return results.violations;
}

export function formatAxeViolations(violations: Result[]): string {
  return violations
    .map(
      (v) =>
        `- [${v.impact}] ${v.id}: ${v.help} (${v.helpUrl})\n` +
        v.nodes.map((n) => `    target: ${n.target.join(' ')} — ${n.failureSummary}`).join('\n')
    )
    .join('\n');
}

export async function expectNoAxeViolations(container: HTMLElement): Promise<void> {
  const violations = await collectAxeViolations(container);
  expect(violations, `axe-core violations:\n${formatAxeViolations(violations)}`).toEqual([]);
}
