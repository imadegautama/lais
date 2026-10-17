import type { SVGAttributes } from 'react';

/**
 * The small Lais mark: the logo's L with its orange dot in the open corner.
 * For places too small for the wordmark (collapsed sidebar, favicon).
 * Decorative unless given an aria-label.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            viewBox="0 65 301 365"
            aria-hidden={props['aria-label'] ? undefined : true}
            {...props}
        >
            <path
                fill="currentColor"
                d="M17.3569 429.17C7.77098 429.17 0 421.399 0 411.813V82.3569C0 72.771 7.77096 65 17.3569 65H82.9741C92.5601 65 100.331 72.771 100.331 82.3569L100.331 411.813C100.331 421.399 92.56 429.17 82.974 429.17H17.3569ZM94.682 429.17C85.096 429.17 77.3251 421.399 77.3251 411.813V358.338C77.3251 348.752 85.0961 340.981 94.682 340.981H282.997C292.583 340.981 300.354 348.752 300.354 358.338V411.813C300.354 421.399 292.583 429.17 282.997 429.17H94.682Z"
            />
            <rect
                width="90.6233"
                height="89"
                rx="14"
                transform="matrix(1 0 -0.241922 0.970296 209.731 65)"
                className="fill-brand-strong"
            />
        </svg>
    );
}
