import type { CSSProperties } from 'react';
import { Fragment } from 'react';

/** A delay for `[data-intro]` and `[data-reveal]` (landing.css). */
export function delay(ms: number): CSSProperties {
    return { '--delay': `${ms}ms` } as CSSProperties;
}

/** A heading that arrives word by word; readers get it in one piece. */
export function WordReveal({ text }: { text: string }) {
    return (
        <>
            <span className="sr-only">{text}</span>
            <span aria-hidden>
                {text.split(' ').map((word, index) => (
                    <Fragment key={`${word}-${index}`}>
                        <span
                            className="landing-word"
                            style={{ '--i': index } as CSSProperties}
                        >
                            {word}
                        </span>{' '}
                    </Fragment>
                ))}
            </span>
        </>
    );
}
