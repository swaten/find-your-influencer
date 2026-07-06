import React from 'react';
import { useLocation } from 'react-router-dom';
import LoginPage from '../components/LoginPage';

export default function Login() {
    const location = useLocation();
    const justRegistered = Boolean(location.state?.registered);

    return (
        <>
            {justRegistered && (
                <div style={{ position: 'fixed', top: 16, left: '50%', transform: 'translateX(-50%)', background: 'var(--tk-green-soft)', color: 'var(--tk-green)', border: '0.5px solid var(--tk-green)', borderRadius: 'var(--tk-radius-md)', padding: '8px 16px', fontSize: 12.5, zIndex: 10 }}>
                    Account created. Sign in to continue.
                </div>
            )}
            <LoginPage />
        </>
    );
}
