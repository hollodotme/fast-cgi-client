import type {ReactNode} from 'react';

/** Icons of the player controls, all drawn on the same 16 × 16 grid */
const paths: Record<string, ReactNode> = {
  restart: (
    <>
      <path d="M3.5 8a4.5 4.5 0 1 0 1.32-3.18" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
      <path d="M3 2.5v3.5h3.5" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" />
    </>
  ),
  previous: (
    <>
      <rect x="3" y="3" width="2" height="10" rx="1" />
      <path d="M13 3.6v8.8a.6.6 0 0 1-.93.5L6.4 8.5a.6.6 0 0 1 0-1l5.67-4.4a.6.6 0 0 1 .93.5Z" />
    </>
  ),
  play: <path d="M4.5 2.9v10.2a.7.7 0 0 0 1.07.6l8.06-5.1a.7.7 0 0 0 0-1.2L5.57 2.3a.7.7 0 0 0-1.07.6Z" />,
  pause: (
    <>
      <rect x="3.5" y="3" width="3" height="10" rx="1" />
      <rect x="9.5" y="3" width="3" height="10" rx="1" />
    </>
  ),
  next: (
    <>
      <path d="M3 3.6v8.8a.6.6 0 0 0 .93.5L9.6 8.5a.6.6 0 0 0 0-1L3.93 3.1a.6.6 0 0 0-.93.5Z" />
      <rect x="11" y="3" width="2" height="10" rx="1" />
    </>
  ),
  chevron: <path d="M4 6l4 4 4-4" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" />,
};

export type IconName = 'restart' | 'previous' | 'play' | 'pause' | 'next' | 'chevron';

export default function Icon({name, className}: {name: IconName; className?: string}): ReactNode {
  return (
    <svg className={className} viewBox="0 0 16 16" width="16" height="16" fill="currentColor" aria-hidden="true" focusable="false">
      {paths[name]}
    </svg>
  );
}
