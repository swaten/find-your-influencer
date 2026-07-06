// resources/js/Components/Card.jsx
import React from 'react';

/**
 * Card — dark surface container.
 *
 * Props:
 *   title?     – card header title
 *   subtitle?  – muted text below title
 *   actions?   – JSX rendered on the right side of the header
 *   noPad?     – remove body padding (use for DataTable, custom layouts)
 *   className? – extra class names on the root element
 *   children   – card body
 */
export default function Card({
  title,
  subtitle,
  actions,
  noPad = false,
  className = '',
  children,
}) {
  const hasHeader = title || subtitle || actions;

  return (
    <div className={`tk-card ${className}`.trim()}>
      {hasHeader && (
        <div className="tk-card-header">
          <div style={{ minWidth: 0 }}>
            {title    && <h5 className="tk-card-title">{title}</h5>}
            {subtitle && <p className="tk-card-subtitle">{subtitle}</p>}
          </div>
          {actions && (
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexShrink: 0 }}>
              {actions}
            </div>
          )}
        </div>
      )}
      <div className={`tk-card-body${noPad ? ' no-pad' : ''}`}>
        {children}
      </div>
    </div>
  );
}