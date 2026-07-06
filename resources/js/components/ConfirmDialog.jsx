import React from 'react';
import { AlertTriangle, Loader2 } from 'lucide-react';

// generic yes/no modal - replaces window.confirm so destructive actions match the app's look
export default function ConfirmDialog({
    title = 'Are you sure?',
    message,
    confirmLabel = 'Confirm',
    danger = false,
    loading = false,
    onConfirm,
    onCancel,
}) {
    return (
        <div className="tk-modal-backdrop" onClick={onCancel}>
            <div className="tk-modal" style={{ maxWidth: 400 }} onClick={(e) => e.stopPropagation()}>
                <div className="tk-modal-header">
                    <div className="tk-modal-title">{title}</div>
                </div>

                <div className="tk-modal-body">
                    <div style={{ display: 'flex', gap: 10, alignItems: 'flex-start' }}>
                        {danger && (
                            <AlertTriangle
                                size={18}
                                strokeWidth={1.75}
                                style={{ color: 'var(--tk-red)', flexShrink: 0, marginTop: 2 }}
                            />
                        )}
                        <p style={{ margin: 0, fontSize: 13, color: 'var(--tk-text-secondary)' }}>{message}</p>
                    </div>
                </div>

                <div className="tk-modal-footer">
                    <button type="button" className="tk-btn tk-btn-ghost" onClick={onCancel} disabled={loading}>
                        Cancel
                    </button>
                    <button
                        type="button"
                        className={`tk-btn ${danger ? 'tk-btn-danger' : 'tk-btn-primary'}`}
                        onClick={onConfirm}
                        disabled={loading}
                        style={{ marginLeft: 8 }}
                    >
                        {loading && (
                            <Loader2 size={13} strokeWidth={1.75} style={{ animation: 'tk-spin 0.8s linear infinite' }} />
                        )}
                        {confirmLabel}
                    </button>
                </div>
            </div>
        </div>
    );
}
