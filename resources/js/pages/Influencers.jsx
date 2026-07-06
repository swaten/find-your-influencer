import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';
import { Link, useSearchParams } from 'react-router-dom';
import { Plus, RefreshCw, Trash2, Loader2, Eye } from 'lucide-react';
import MainLayout from '../components/MainLayout';
import Card from '../components/Card';
import DataTable from '../components/DataTable';
import AddInfluencerModal from '../components/AddInfluencerModal';
import ConfirmDialog from '../components/ConfirmDialog';

const STATUS_COLORS = {
    pending: 'var(--tk-text-muted)',
    fetching: 'var(--tk-cyan, var(--tk-accent))',
    fetched: 'var(--tk-green)',
    failed: 'var(--tk-red)',
};

function formatFollowers(count) {
    if (count === null || count === undefined) return '—';
    return new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 }).format(count);
}

export default function Influencers() {
    const [searchParams, setSearchParams] = useSearchParams();
    const [modalOpen, setModalOpen] = useState(false);
    const [refreshKey, setRefreshKey] = useState(0);
    const [busyIds, setBusyIds] = useState(new Set());
    const [deleteTarget, setDeleteTarget] = useState(null); // row pending delete confirmation

    // filters live in the URL too, so they're separate state from DataTable's own page/search sync
    const [platform, setPlatform] = useState(searchParams.get('platform') || '');
    const [status, setStatus] = useState(searchParams.get('status') || '');

    const initialPage = Number(searchParams.get('page')) || 1;
    const initialSearch = searchParams.get('search') || '';
    const initialPageSize = Number(searchParams.get('per_page')) || 10;

    // stable reference - only changes when platform/status actually change, not on
    // every render (an inline {} literal here was the root cause of an infinite
    // DataTable refetch loop once onStateChange started updating the URL)
    const extraParams = useMemo(
        () => ({ platform: platform || undefined, status: status || undefined }),
        [platform, status],
    );

    const bump = () => setRefreshKey((k) => k + 1);

    // rows still pending/fetching mean FetchProfileJob hasn't landed yet -
    // poll a few times so status/followers update on their own instead of
    // needing a manual refresh click
    const pollTimeoutRef = useRef(null);
    const POLL_MS = 3000;

    const handleTableLoad = useCallback((_total, rows) => {
        if (pollTimeoutRef.current) {
            clearTimeout(pollTimeoutRef.current);
            pollTimeoutRef.current = null;
        }
        const hasInFlight = (rows || []).some((row) => row.status === 'pending' || row.status === 'fetching');
        if (hasInFlight) {
            pollTimeoutRef.current = setTimeout(() => {
                pollTimeoutRef.current = null;
                bump();
            }, POLL_MS);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => () => {
        if (pollTimeoutRef.current) clearTimeout(pollTimeoutRef.current);
    }, []);

    // called by DataTable whenever page/perPage/search change - merges them into the
    // same URL the platform/status filters live in, so the whole view is shareable/refreshable
    const handleTableStateChange = useCallback(({ page, perPage, search }) => {
        setSearchParams((prev) => {
            const next = new URLSearchParams(prev);
            page > 1 ? next.set('page', String(page)) : next.delete('page');
            perPage !== 10 ? next.set('per_page', String(perPage)) : next.delete('per_page');
            search ? next.set('search', search) : next.delete('search');
            return next;
        }, { replace: true });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const updateFilter = (key, value) => {
        if (key === 'platform') setPlatform(value); else setStatus(value);
        setSearchParams((prev) => {
            const next = new URLSearchParams(prev);
            value ? next.set(key, value) : next.delete(key);
            next.delete('page'); // filters changing invalidates the current page
            return next;
        }, { replace: true });
    };

    const setBusy = (id, busy) => {
        setBusyIds((prev) => {
            const next = new Set(prev);
            if (busy) next.add(id); else next.delete(id);
            return next;
        });
    };

    const handleRefresh = async (id) => {
        setBusy(id, true);
        try {
            await axios.post(`/api/influencers/${id}/refresh`);
            bump();
        } catch (err) {
            window.alert(err.response?.data?.message || `Refresh failed (${err.response?.status || 'network error'}).`);
        } finally {
            setBusy(id, false);
        }
    };

    const confirmDelete = async () => {
        if (!deleteTarget) return;
        const { id } = deleteTarget;
        setBusy(id, true);
        try {
            await axios.delete(`/api/influencers/${id}`);
            setDeleteTarget(null);
            bump();
        } catch (err) {
            window.alert(err.response?.data?.message || `Delete failed (${err.response?.status || 'network error'}).`);
        } finally {
            setBusy(id, false);
        }
    };

    const columns = [
        { key: 'handle', label: 'Handle', width: '20%', render: (row) => (
            <Link to={`/influencers/${row.id}`} style={{ color: 'var(--tk-accent)', fontWeight: 600 }}>
                {row.handle}
            </Link>
        ) },
        { key: 'platform', label: 'Platform', width: '13%', render: (row) => (
            <span style={{ textTransform: 'capitalize' }}>{row.platform}</span>
        ) },
        { key: 'followers', label: 'Followers', width: '13%', render: (row) => formatFollowers(row.followers) },
        { key: 'status', label: 'Status', width: '13%', render: (row) => (
            <span style={{ color: STATUS_COLORS[row.status] || 'inherit', textTransform: 'capitalize', fontWeight: 600 }}>
                {row.status}
            </span>
        ) },
        { key: 'last_synced_at', label: 'Last synced', width: '21%', render: (row) => row.last_synced_at || 'Never' },
        { key: 'actions', label: '', width: '20%', render: (row) => (
            <div style={{ display: 'flex', gap: 6 }}>
                <Link
                    to={`/influencers/${row.id}`}
                    className="tk-btn tk-btn-icon tk-btn-sm"
                    title="View details"
                >
                    <Eye size={13} strokeWidth={1.75} />
                </Link>
                <button
                    type="button"
                    className="tk-btn tk-btn-icon tk-btn-sm"
                    onClick={() => handleRefresh(row.id)}
                    disabled={busyIds.has(row.id)}
                    title="Refresh stats"
                >
                    {busyIds.has(row.id)
                        ? <Loader2 size={13} strokeWidth={1.75} style={{ animation: 'tk-spin 0.8s linear infinite' }} />
                        : <RefreshCw size={13} strokeWidth={1.75} />}
                </button>
                <button
                    type="button"
                    className="tk-btn tk-btn-icon tk-btn-sm tk-btn-danger"
                    onClick={() => setDeleteTarget(row)}
                    disabled={busyIds.has(row.id)}
                    title="Remove"
                >
                    <Trash2 size={13} strokeWidth={1.75} />
                </button>
            </div>
        ) },
    ];

    return (
        <MainLayout>
            <div className="tk-page-header">
                <div>
                    <h1 className="tk-page-title">Influencers</h1>
                    <p className="tk-page-subtitle">Every profile on your watchlist, refreshed automatically.</p>
                </div>
                <div className="tk-page-actions">
                    <select className="tk-select" value={platform} onChange={(e) => updateFilter('platform', e.target.value)}>
                        <option value="">All platforms</option>
                        <option value="instagram">Instagram</option>
                        <option value="youtube">YouTube</option>
                    </select>
                    <select className="tk-select" value={status} onChange={(e) => updateFilter('status', e.target.value)}>
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="fetching">Fetching</option>
                        <option value="fetched">Fetched</option>
                        <option value="failed">Failed</option>
                    </select>
                    <button type="button" className="tk-btn tk-btn-primary tk-btn-sm" onClick={() => setModalOpen(true)}>
                        <Plus size={14} strokeWidth={1.75} /> Add influencer
                    </button>
                </div>
            </div>

            <Card noPad>
                <DataTable
                    endpoint="/api/influencers"
                    columns={columns}
                    fullWidth
                    refreshKey={refreshKey}
                    initialPage={initialPage}
                    initialSearch={initialSearch}
                    initialPageSize={initialPageSize}
                    extraParams={extraParams}
                    onStateChange={handleTableStateChange}
                    onLoad={handleTableLoad}
                />
            </Card>

            {modalOpen && (
                <AddInfluencerModal onClose={() => setModalOpen(false)} onAdded={bump} />
            )}

            {deleteTarget && (
                <ConfirmDialog
                    title="Remove from watchlist?"
                    message={`This will remove ${deleteTarget.handle} from your watchlist. You can re-add the same handle later.`}
                    confirmLabel="Remove"
                    danger
                    loading={busyIds.has(deleteTarget.id)}
                    onConfirm={confirmDelete}
                    onCancel={() => setDeleteTarget(null)}
                />
            )}
        </MainLayout>
    );
}
