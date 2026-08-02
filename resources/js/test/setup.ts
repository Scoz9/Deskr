import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';

// jsdom has no ResizeObserver. Radix primitives that measure themselves
// (Checkbox's indicator, among others) call it on mount regardless of
// whether a test cares about size, so every test needs the stub and not
// just the ones that happen to render such a component.
class ResizeObserverStub {
    observe(): void {}
    unobserve(): void {}
    disconnect(): void {}
}

global.ResizeObserver = ResizeObserverStub;

afterEach(() => {
    cleanup();
});
