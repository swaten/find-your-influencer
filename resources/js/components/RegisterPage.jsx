import React from 'react';
import { Link } from 'react-router-dom';
import { useRegister } from '../hooks/useRegister';
import '../../css/LoginPage.css';

const RegisterPage = () => {
    const { fields, errors, loading, onChange, onSubmit } = useRegister();

    return (
        <div className="tk-page">
            <main className="tk-card" role="main">
                <span className="tk-brand-name">
                    <span style={{ color: 'var(--tk-text-primary)' }}>FindYour</span>
                    <span style={{ color: 'var(--tk-accent)' }}>Influencer</span>
                </span>

                <h1 className="tk-form-title">Register</h1>

                <form onSubmit={onSubmit} noValidate>
                    <div className="tk-field">
                        <label htmlFor="name">Full name</label>
                        <input
                            id="name"
                            type="text"
                            name="name"
                            value={fields.name}
                            onChange={onChange}
                            placeholder="Jane Doe"
                            required
                            disabled={loading}
                        />
                        {errors.name && <div className="tk-error">{errors.name[0]}</div>}
                    </div>

                    <div className="tk-field">
                        <label htmlFor="email">Username</label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value={fields.email}
                            onChange={onChange}
                            required
                            disabled={loading}
                        />
                        {errors.email && <div className="tk-error">{errors.email[0]}</div>}
                    </div>

                    <div className="tk-field">
                        <label htmlFor="password">Password</label>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            value={fields.password}
                            onChange={onChange}
                            placeholder="At least 8 characters"
                            required
                            disabled={loading}
                        />
                        {errors.password && <div className="tk-error">{errors.password[0]}</div>}
                    </div>

                    <div className="tk-field">
                        <label htmlFor="password_confirmation">Confirm password</label>
                        <input
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            value={fields.password_confirmation}
                            onChange={onChange}
                            placeholder="Repeat password"
                            required
                            disabled={loading}
                        />
                    </div>

                    {errors.form && <div className="tk-error">{errors.form[0]}</div>}

                    <button type="submit" className="tk-btn-primary" disabled={loading} aria-busy={loading}>
                        {loading ? <span className="tk-spinner" aria-hidden="true" /> : 'Create account'}
                    </button>

                    <p className="tk-form-sub" style={{ textAlign: 'center', marginTop: 14 }}>
                        Already have an account? <Link to="/login" className="tk-forgot">Sign in</Link>
                    </p>
                </form>
            </main>
        </div>
    );
};

export default RegisterPage;
