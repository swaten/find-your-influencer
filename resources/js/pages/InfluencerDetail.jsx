import React, { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import axios from 'axios';
import { ArrowLeft, RefreshCw, Loader2, Users, UserPlus, Grid3x3, AlertTriangle } from 'lucide-react';
import MainLayout from '../components/MainLayout';
import Card from '../components/Card';

const STATUS_COLORS = {
    pending: 'var(--tk-text-muted)',
    fetching: 'var(--tk-cyan, var(--tk-accent))',
    fetched: 'var(--tk-green)',
    failed: 'var(--tk-red)',
};

function formatCount(count) {
    if (count === null || count === undefined) return '—';
    return new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 }).format(count);
}

function formatDelta(delta) {
    if (delta === null || delta === undefined) return '—';
    if (delta === 0) return '±0';
    const sign = delta > 0 ? '+' : '';
    return `${sign}${new Intl.NumberFormat('en-US').format(delta)}`;
}

export default function InfluencerDetail() {
    const { id } = useParams();
    const [profile, setProfile] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [refreshing, setRefreshing] = useState(false);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const { data } = await axios.get(`/api/influencers/${id}`);
            setProfile(data);
        } catch (err) {
            setError(err.response?.data?.message || 'Could not load this profile.');
        } finally {
            setLoading(false);
        }
    }, [id]);

    useEffect(() => { load(); }, [load]);

    // job runs on the queue, not inline - while status is pending/fetching keep
    // re-polling every few seconds so this settles into fetched/failed on its own
    useEffect(() => {
        if (!profile || (profile.status !== 'pending' && profile.status !== 'fetching')) return;
        const t = setTimeout(load, 3000);
        return () => clearTimeout(t);
    }, [profile, load]);

    const handleRefresh = async () => {
        setRefreshing(true);
        try {
            await axios.post(`/api/influencers/${id}/refresh`);
            // kick off the first re-poll immediately after; the effect above takes over from there
            setTimeout(load, 3000);
        } finally {
            setRefreshing(false);
        }
    };

    if (loading) {
        return (
            <MainLayout>
                <div className="tk-page-loading">Loading…</div>
            </MainLayout>
        );
    }

    if (error || !profile) {
        return (
            <MainLayout>
                <div className="tk-form-error-strip">
                    <AlertTriangle size={14} strokeWidth={1.75} />
                    {error || 'Profile not found.'}
                </div>
            </MainLayout>
        );
    }

    const snapshots = profile.snapshots || [];
    const rows = snapshots.map((snap, i) => {
        const older = snapshots[i + 1];
        return {
            ...snap,
            delta: older ? snap.followers_count - older.followers_count : null,
        };
    });

    return (
        <MainLayout>
            <div className="tk-page-header">
                <div>
                    <Link to="/influencers" className="tk-btn tk-btn-ghost tk-btn-sm" style={{ marginBottom: 10 }}>
                        <ArrowLeft size={13} strokeWidth={1.75} /> Back to watchlist
                    </Link>
                    <h1 className="tk-page-title">{profile.display_name || `@${profile.username}`}</h1>
                    <p className="tk-page-subtitle">
                        @{profile.username} · <span style={{ textTransform: 'capitalize' }}>{profile.platform}</span> ·{' '}
                        <span style={{ color: STATUS_COLORS[profile.status], fontWeight: 600, textTransform: 'capitalize' }}>
                            {profile.status}
                        </span>
                    </p>
                </div>
                <div className="tk-page-actions">
                    <button type="button" className="tk-btn tk-btn-primary tk-btn-sm" onClick={handleRefresh} disabled={refreshing}>
                        {refreshing
                            ? <Loader2 size={14} strokeWidth={1.75} style={{ animation: 'tk-spin 0.8s linear infinite' }} />
                            : <RefreshCw size={14} strokeWidth={1.75} />}
                        Refresh stats
                    </button>
                </div>
            </div>

            {profile.status === 'failed' && profile.last_error && (
                <div className="tk-form-error-strip" style={{ marginBottom: 16 }}>
                    <AlertTriangle size={14} strokeWidth={1.75} />
                    Last fetch failed: {profile.last_error} ({profile.consecutive_failures} consecutive failures)
                </div>
            )}

            <div style={{ display: 'flex', gap: 16, marginBottom: 20 }}>
                <div style={{ flex: 1 }} className="tk-stat-card blue">
                    <div className="sc-icon blue"><Users size={16} /></div>
                    <div className="sc-label">Followers</div>
                    <div className="sc-value blue">{formatCount(profile.last_followers_count)}</div>
                    <div className="ph-sub">
                        {profile.last_fetched_at ? `Synced ${new Date(profile.last_fetched_at).toLocaleString()}` : 'Never synced'}
                    </div>
                </div>
                <div style={{ flex: 1 }} className="tk-stat-card purple">
                    <div className="sc-icon purple"><UserPlus size={16} /></div>
                    <div className="sc-label">Following</div>
                    <div className="sc-value purple">{formatCount(profile.last_following_count)}</div>
                    <div className="ph-sub">&nbsp;</div>
                </div>
                <div style={{ flex: 1 }} className="tk-stat-card cyan">
                    <div className="sc-icon cyan"><Grid3x3 size={16} /></div>
                    <div className="sc-label">Posts</div>
                    <div className="sc-value cyan">{formatCount(profile.last_posts_count)}</div>
                    <div className="ph-sub">&nbsp;</div>
                </div>
            </div>

            <Card title="Snapshot history" subtitle="Every recorded fetch, most recent first, with follower deltas.">
                {rows.length === 0 ? (
                    <p className="tk-page-subtitle">No snapshots yet — stats will appear here after the first successful fetch.</p>
                ) : (
                    <div className="tk-table-wrap">
                        <table className="tk-table" style={{ width: '100%', tableLayout: 'fixed' }}>
                            <thead>
                                <tr>
                                    <th style={{ width: '30%' }}>Fetched at</th>
                                    <th style={{ width: '20%' }}>Followers</th>
                                    <th style={{ width: '20%' }}>Δ Followers</th>
                                    <th style={{ width: '15%' }}>Following</th>
                                    <th style={{ width: '15%' }}>Posts</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) => (
                                    <tr key={row.id}>
                                        <td>{new Date(row.fetched_at).toLocaleString()}</td>
                                        <td>{formatCount(row.followers_count)}</td>
                                        <td style={{
                                            color: row.delta > 0 ? 'var(--tk-green)' : row.delta < 0 ? 'var(--tk-red)' : 'var(--tk-text-muted)',
                                            fontWeight: 600,
                                        }}
                                        >
                                            {formatDelta(row.delta)}
                                        </td>
                                        <td>{formatCount(row.following_count)}</td>
                                        <td>{formatCount(row.posts_count)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>
        </MainLayout>
    );
}
