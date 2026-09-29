import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, fireEvent, act } from '@testing-library/react';
import { useLazyImage, BLUR_PLACEHOLDER } from './useLazyImage';
import { LazyImage } from '../components/LazyImage';

type IOEntry = { isIntersecting: boolean };
type IOCallback = (entries: IOEntry[], observer: unknown) => void;

class MockIntersectionObserver {
  static instances: MockIntersectionObserver[] = [];
  callback: IOCallback;
  options: { rootMargin?: string; threshold?: number };
  observe = vi.fn();
  disconnect = vi.fn();
  unobserve = vi.fn();

  constructor(cb: IOCallback, options: { rootMargin?: string; threshold?: number } = {}) {
    this.callback = cb;
    this.options = options;
    MockIntersectionObserver.instances.push(this);
  }
}

beforeEach(() => {
  MockIntersectionObserver.instances.length = 0;
  vi.stubGlobal('IntersectionObserver', MockIntersectionObserver);
});

afterEach(() => {
  vi.unstubAllGlobals();
});

// The hook needs a real DOM node (ref) for the observer branch — go through LazyImage
// so the tests cover the full src/placeholder/opacity lifecycle.
function setupLazyImage(props = {}) {
  const utils = render(<LazyImage src="photo.jpg" alt="Room photo" {...props} />);
  return { img: utils.getByAltText('Room photo'), unmount: utils.unmount, ...utils };
}

function intersect(observer: MockIntersectionObserver, isIntersecting: boolean) {
  act(() => {
    observer.callback([{ isIntersecting }], observer);
  });
}

describe('useLazyImage — IntersectionObserver branch', () => {
  it('starts on the blur placeholder and does not load the real image', () => {
    const { img } = setupLazyImage();
    expect(img.getAttribute('src')).toBe(BLUR_PLACEHOLDER);
    expect(img).toHaveAttribute('loading', 'lazy');
  });

  it('loads the real image once the observer reports intersection', () => {
    const { img } = setupLazyImage();
    const observer = MockIntersectionObserver.instances[0];
    intersect(observer, true);
    expect(img.getAttribute('src')).toBe('photo.jpg');
  });

  it('ignores callbacks where no entry is intersecting', () => {
    const { img } = setupLazyImage();
    const observer = MockIntersectionObserver.instances[0];
    intersect(observer, false);
    expect(img.getAttribute('src')).toBe(BLUR_PLACEHOLDER);
  });

  it('observes the img node with the configured rootMargin', () => {
    render(<LazyImage src="p.jpg" alt="x" />);
    const observer = MockIntersectionObserver.instances[0];
    expect(observer.observe).toHaveBeenCalledTimes(1);
    expect(observer.options.rootMargin).toBe('100px');
  });

  it('does not create a second observer after the image became visible', () => {
    const { img } = setupLazyImage();
    const observer = MockIntersectionObserver.instances[0];
    intersect(observer, true);
    // effect re-runs (deps: isVisible) and must bail out early
    expect(MockIntersectionObserver.instances).toHaveLength(1);
    expect(img.getAttribute('src')).toBe('photo.jpg');
  });

  it('disconnects the observer on unmount', () => {
    const { unmount } = setupLazyImage();
    const observer = MockIntersectionObserver.instances[0];
    unmount();
    expect(observer.disconnect).toHaveBeenCalled();
  });
});

describe('useLazyImage — jsdom / no-IO fallback branch', () => {
  // eslint-env: no IntersectionObserver at all (very old browser / test env default)
  it('loads eagerly from the start when IntersectionObserver is missing', () => {
    vi.stubGlobal('IntersectionObserver', undefined);
    const { img } = setupLazyImage();
    expect(img.getAttribute('src')).toBe('photo.jpg');
    expect(MockIntersectionObserver.instances).toHaveLength(0);
  });
});

describe('useLazyImage — eager mode (enabled: false)', () => {
  it('skips the placeholder and the observer entirely', () => {
    const { img } = setupLazyImage({ eager: true });
    expect(img.getAttribute('src')).toBe('photo.jpg');
    expect(img).toHaveAttribute('loading', 'eager');
    expect(MockIntersectionObserver.instances).toHaveLength(0);
  });
});

describe('LazyImage — decode lifecycle', () => {
  it('keeps the blur (opacity-0) until the image fires load', () => {
    const { img } = setupLazyImage();
    intersect(MockIntersectionObserver.instances[0], true);
    // src set but not decoded yet
    expect(img.className).toContain('opacity-0');
    fireEvent.load(img);
    expect(img.className).toContain('opacity-100');
  });

  it('forwards width/height attributes and decoding=async', () => {
    const { img } = setupLazyImage({ width: 800, height: 600 });
    expect(img).toHaveAttribute('width', '800');
    expect(img).toHaveAttribute('height', '600');
    expect(img).toHaveAttribute('decoding', 'async');
  });
});
