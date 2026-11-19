import { expect } from 'vitest';
import { toHaveNoViolations } from 'vitest-axe/matchers';

expect.extend({ toHaveNoViolations });

if (typeof HTMLCanvasElement !== 'undefined') {
    HTMLCanvasElement.prototype.getContext = () =>
        ({
            fillRect: () => undefined,
            clearRect: () => undefined,
            getImageData: () => ({ data: [] }),
            putImageData: () => undefined,
            createImageData: () => [],
            setTransform: () => undefined,
            drawImage: () => undefined,
            save: () => undefined,
            fillText: () => undefined,
            restore: () => undefined,
            beginPath: () => undefined,
            closePath: () => undefined,
            stroke: () => undefined,
            translate: () => undefined,
            scale: () => undefined,
            rotate: () => undefined,
            arc: () => undefined,
            fill: () => undefined,
            measureText: () => ({ width: 0 }),
            transform: () => undefined,
            rect: () => undefined,
            clip: () => undefined,
        }) as CanvasRenderingContext2D;
}
