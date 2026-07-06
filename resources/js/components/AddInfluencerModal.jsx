import React, { useState } from 'react';
import axios from 'axios';
import { X, Loader2 } from 'lucide-react';

// add-handle form - platform + username, first fetch is queued server-side on submit
export default function AddInfluencerModal({ onClose, onAdded }) {
    const [platform, setPlatform] = useState('instagram');
    const [username, setUsername] = useState('');
    const [error, setError] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const submit = async (e) => {
        e.preventDefault();
        setError('');
        setSubmitting(true);
        try {
            await axios.post('/api/influencers', { platform, username });
            onAdded();
            onClose();
        } catch (err) {
            const msg = err.response?.data?.errors?.username?.[0]
                || err.response?.data?.message
                || 'Could not add this profile.';
            setError(msg);
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <div className="tk-modal-backdrop" onClick={onClose}>
            <div className="tk-modal" style={{ maxWidth: 420 }} onClick={(e) => e.stopPropagation()}>
                <div className="tk-modal-header">
                    <div>
                        <div className="tk-modal-title">Add influencer</div>
                        <div className="tk-modal-subtitle">Stats fetch automatically once added.</div>
                    </div>
                    <button type="button" className="tk-btn tk-btn-icon" onClick={onClose} aria-label="Close">
                        <X size={14} strokeWidth={1.75} />
                    </button>
                </div>

                <form onSubmit={submit}>
                    <div className="tk-modal-body">
                        <div className="tk-form-stack">
                            <div>
                                <label className="form-label">Platform</label>
                                <select
                                    className="tk-select"
                                    value={platform}
                                    onChange={(e) => setPlatform(e.target.value)}
                                    style={{ width: '100%' }}
                                >
                                    <option value="instagram">Instagram</option>
                                    <option value="youtube">YouTube</option>
                                </select>
                            </div>
                            <div>
                                <label className="form-label">Username</label>
                                <input
                                    className="tk-input"
                                    type="text"
                                    value={username}
                                    onChange={(e) => setUsername(e.target.value)}
                                    placeholder={platform === 'youtube' ? 'e.g. MrBeast (with or without @)' : 'e.g. dhruvrathee'}
                                    style={{ width: '100%' }}
                                    required
                                    autoFocus
                                />
                            </div>
                        </div>

                        {error && <div className="tk-form-error-strip">{error}</div>}
                    </div>

                    <div className="tk-modal-footer">
                        <button type="button" className="tk-btn tk-btn-ghost" onClick={onClose}>
                            Cancel
                        </button>
                        <button
                            type="submit"
                            className="tk-btn tk-btn-primary"
                            disabled={submitting || !username.trim()}
                            style={{ marginLeft: 8 }}
                        >
                            {submitting && (
                                <Loader2 size={13} strokeWidth={1.75} style={{ animation: 'tk-spin 0.8s linear infinite' }} />
                            )}
                            Add
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
