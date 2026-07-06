// resources/js/Components/DataTable.jsx
import React, { useEffect, useState, useRef } from 'react';
import axios from 'axios';
import {
  Search, X, Loader2, Database,
  ChevronLeft, ChevronRight, AlertTriangle,
} from 'lucide-react';

/**
 * DataTable — Thinkone design system table.
 *
 * Props:
 *   endpoint        – Laravel paginator API URL
 *   columns         – [{ key, label, width?, render? }]
 *                     `width` is a CSS value e.g. '20%' or '120px'.
 *                     Providing widths that sum to ~100% prevents column stretch.
 *   initialPageSize – default rows per page (default: 10)
 *   extraParams     – extra query params merged into each request
 *   onLoad          – callback(total, rows) fired after a successful fetch
 *   onError         – callback(message) fired when a fetch fails
 *   fullWidth       – stretch the table to fill its container instead of
 *                     sizing to content (use for tables with few columns)
 *   initialPage     – starting page (e.g. read from the URL by the parent)
 *   initialSearch   – starting search text (e.g. read from the URL by the parent)
 *   onStateChange   – callback({ page, perPage, search }) fired whenever any of
 *                     those change, so a parent can mirror them into the URL
 */
export default function DataTable({
  endpoint,
  columns,
  initialPageSize = 10,
  initialPage = 1,
  initialSearch = '',
  extraParams,
  onLoad,
  onError,
  onStateChange,
  refreshKey = 0,   // increment this from outside to trigger a re-fetch (e.g. after bulk import)
  fullWidth = false,
}) {
  const [rows,     setRows]     = useState([]);
  const [page,     setPage]     = useState(initialPage);
  const [perPage,  setPerPage]  = useState(initialPageSize);
  const [total,    setTotal]    = useState(0);
  const [lastPage, setLastPage] = useState(1);
  const [search,   setSearch]   = useState(initialSearch);
  const [loading,  setLoading]  = useState(false);
  const [error,    setError]    = useState(null);
  const prevParamsRef = useRef(null);

  const fetchData = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await axios.get(endpoint, {
        params: {
          page,
          per_page: perPage,
          search: search || undefined,
          ...(extraParams || {}),
        },
      });
      const p = res.data;
      setRows(p.data || []);
      setTotal(p.total ?? 0);
      setPage(p.current_page ?? page);
      setPerPage(p.per_page ?? perPage);
      setLastPage(p.last_page ?? 1);
      if (typeof onLoad === 'function') onLoad(p.total ?? 0, p.data ?? []);
    } catch (err) {
      const msg = err.response?.data?.message || err.message || 'Failed to load data.';
      setError(msg);
      if (typeof onError === 'function') onError(msg);
    } finally {
      setLoading(false);
    }
  };

  // stringified once per render so the effect below reacts to actual content
  // changes, not the object literal's identity (a fresh {} every render would
  // otherwise re-fire this effect on every unrelated re-render of the parent)
  const paramsStr = JSON.stringify(extraParams || {});

  useEffect(() => {
    if (prevParamsRef.current !== null && prevParamsRef.current !== paramsStr && page !== 1) {
      prevParamsRef.current = paramsStr;
      setPage(1);
      return;
    }
    prevParamsRef.current = paramsStr;
    fetchData();
    if (typeof onStateChange === 'function') {
      onStateChange({ page, perPage, search });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [endpoint, page, perPage, search, paramsStr, refreshKey]);

  const goTo = (p) => setPage(Math.max(1, Math.min(p, lastPage)));

  const pageNumbers = () => {
    const pages = [];
    const delta = 2;
    const left  = page - delta;
    const right = page + delta;
    for (let i = 1; i <= lastPage; i++) {
      if (i === 1 || i === lastPage || (i >= left && i <= right)) {
        pages.push(i);
      } else if (pages[pages.length - 1] !== '…') {
        pages.push('…');
      }
    }
    return pages;
  };

  const start = rows.length ? (page - 1) * perPage + 1 : 0;
  const end   = Math.min(page * perPage, total);

  return (
    <div>
      {/* ── Toolbar ── */}
      <div className="tk-table-toolbar">
        <div className="tt-left">
          <select
            className="tk-select"
            value={perPage}
            onChange={e => { setPerPage(Number(e.target.value)); setPage(1); }}
          >
            {[5, 10, 25, 50, 100].map(n => (
              <option key={n} value={n}>{n} / page</option>
            ))}
          </select>
        </div>

        <div className="tt-right">
          <div className="tk-table-search">
            <Search size={13} color="var(--tk-text-muted)" strokeWidth={1.75} />
            <input
              type="text"
              placeholder="Search records…"
              value={search}
              onChange={e => { setSearch(e.target.value); setPage(1); }}
            />
            {search && (
              <button
                onClick={() => { setSearch(''); setPage(1); }}
                style={{
                  background: 'none', border: 'none', cursor: 'pointer',
                  color: 'var(--tk-text-muted)', padding: 0,
                  display: 'flex', alignItems: 'center',
                }}
                aria-label="Clear search"
              >
                <X size={13} strokeWidth={1.75} />
              </button>
            )}
          </div>
        </div>
      </div>

      {/* ── Table ──
          tk-table-wrap allows horizontal scroll on very small screens
          without ever stretching the card beyond its container.
      ── */}
      <div className="tk-table-wrap">
        <table
          className="tk-table"
          style={fullWidth ? { width: '100%', tableLayout: 'fixed', minWidth: 0 } : undefined}
        >
          <thead>
            <tr>
              {columns.map(col => (
                <th key={col.key} style={col.width ? { width: col.width } : undefined}>
                  {col.label}
                </th>
              ))}
            </tr>
          </thead>

          <tbody>
            {/* Loading */}
            {loading && (
              <tr>
                <td colSpan={columns.length} style={{ textAlign: 'center', padding: '36px 20px', color: 'var(--tk-text-muted)' }}>
                  <Loader2 size={16} strokeWidth={1.75} style={{ marginRight: 8, display: 'inline', animation: 'tk-spin 0.8s linear infinite' }} />
                  Loading…
                </td>
              </tr>
            )}

            {/* Empty */}
            {!loading && rows.length === 0 && (
              <tr>
                <td colSpan={columns.length} style={{ padding: 0 }}>
                  <div className="tk-empty-state">
                    <Database size={28} strokeWidth={1.5} />
                    <p>No records found</p>
                  </div>
                </td>
              </tr>
            )}

            {/* Rows */}
            {!loading && rows.map(row => (
              <tr key={row.id ?? JSON.stringify(row)}>
                {columns.map(col => (
                  <td key={col.key}>
                    {col.render ? col.render(row) : (row[col.key] ?? '—')}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* ── Pagination ── */}
      {!loading && rows.length > 0 && (
        <div className="tk-pagination">
          <div className="pg-info">
            Showing <strong>{start}</strong>–<strong>{end}</strong> of <strong>{total}</strong>
          </div>

          <div className="pg-controls">
            <button className="pg-btn" onClick={() => goTo(page - 1)} disabled={page <= 1}>
              <ChevronLeft size={14} strokeWidth={1.75} />
            </button>

            {pageNumbers().map((p, i) =>
              p === '…'
                ? <span key={`ellipsis-${i}`} className="pg-page-info" style={{ padding: '0 4px' }}>…</span>
                : (
                  <button
                    key={p}
                    className={`pg-btn${page === p ? ' active' : ''}`}
                    onClick={() => goTo(p)}
                  >
                    {p}
                  </button>
                )
            )}

            <button className="pg-btn" onClick={() => goTo(page + 1)} disabled={page >= lastPage}>
              <ChevronRight size={14} strokeWidth={1.75} />
            </button>
          </div>

          <div className="pg-page-info">Page {page} / {lastPage}</div>
        </div>
      )}

      {/* ── Error ── */}
      {error && (
        <div className="tk-table-error">
          <AlertTriangle size={14} strokeWidth={1.75} />
          {error}
        </div>
      )}
    </div>
  );
}