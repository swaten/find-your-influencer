import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useLogin } from '../hooks/useLogin';
import '../../css/LoginPage.css';

const EyeIcon = ({ open }) => (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
        {open ? (
            <>
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                <circle cx="12" cy="12" r="3" />
            </>
        ) : (
            <>
                <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94" />
                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19" />
                <line x1="1" y1="1" x2="23" y2="23" />
            </>
        )}
    </svg>
);

const LoginPage = () => {
    const { fields, error, loading, onChange, onSubmit } = useLogin();
    const [showPassword, setShowPassword] = useState(false);

    return (
        <div className="tk-page">
            <main className="tk-card" role="main">
                <span className="tk-brand-name">
                    <span style={{ color: 'var(--tk-text-primary)' }}>FindYour</span>
                    <span style={{ color: 'var(--tk-accent)' }}>Influencer</span>
                </span>

                <h1 className="tk-form-title">Sign in</h1>

                <form id="loginForm" onSubmit={onSubmit} noValidate>
                    <div className="tk-field">
                        <label htmlFor="email">Username</label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value={fields.email}
                            onChange={onChange}
                            autoComplete="email"
                            required
                            disabled={loading}
                            aria-describedby={error ? 'tk-error' : undefined}
                        />
                    </div>

                    <div className="tk-field">
                        <label htmlFor="password">Password</label>
                        <div className="tk-input-wrap">
                            <input
                                id="password"
                                type={showPassword ? 'text' : 'password'}
                                name="password"
                                value={fields.password}
                                onChange={onChange}
                                autoComplete="current-password"
                                required
                                disabled={loading}
                            />
                            <button
                                type="button"
                                className="tk-eye-btn"
                                onClick={() => setShowPassword((v) => !v)}
                                aria-label={showPassword ? 'Hide password' : 'Show password'}
                            >
                                <EyeIcon open={showPassword} />
                            </button>
                        </div>
                    </div>

                    {error && (
                        <div id="tk-error" className="tk-error" role="alert">
                            {error}
                        </div>
                    )}

                    <button
                        type="submit"
                        className="tk-btn-primary"
                        disabled={loading}
                        aria-busy={loading}
                    >
                        {loading ? <span className="tk-spinner" aria-hidden="true" /> : 'Sign in'}
                    </button>

                    <p className="tk-form-sub" style={{ textAlign: 'center', marginTop: 14 }}>
                        Don't have an account? <Link to="/register" className="tk-forgot">Register</Link>
                    </p>
                </form>
            </main>
        </div>
    );
};

export default LoginPage;
