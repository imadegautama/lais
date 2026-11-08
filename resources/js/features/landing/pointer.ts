/**
 * Whether a click came from a mouse. On the landing page a tap toggles a
 * hold, while a mouse already holds by hovering. A browser that sends a
 * plain MouseEvent counts as touch, which only adds a toggle.
 */
export function isMouse(event: Event): boolean {
    return event instanceof PointerEvent && event.pointerType === 'mouse';
}
