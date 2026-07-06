import React from 'react';
import { useNavigate } from 'react-router-dom';
import { Menu, LogOut } from 'lucide-react';
import { useAuth } from '../context/AuthContext';

export default function Navbar({ onMenuToggle }) {
    const { user, logout } = useAuth();
    const navigate = useNavigate();

    const handleLogout = async () => {
        await logout();
        navigate('/login', { replace: true });
    };

    const initials = (user?.name || 'U')
        .split(' ')
        .map((n) => n[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();

    return (
        <nav className="tk-navbar">
            <div className="tk-navbar-brand-area">
                <button
                    onClick={onMenuToggle}
                    aria-label="Toggle menu"
                    style={{ background: 'none', border: 'none', cursor: 'pointer', color: 'var(--tk-text-secondary)', display: 'flex' }}
                >
                    <Menu size={18} strokeWidth={1.75} />
                </button>
                <div className="brand-icon">
                    <svg viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg" width="18" height="18">
                        <rect x="4" y="5" width="20" height="4" rx="2" fill="white" />
                        <rect x="12" y="5" width="4" height="18" rx="2" fill="white" />
                    </svg>
                </div>
                <span style={{ fontSize: 13, fontWeight: 700, color: 'var(--tk-text-primary)', letterSpacing: '-0.2px' }}>
                    FindYour<span style={{ color: 'var(--tk-accent)' }}>Influencer</span>
                </span>
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: 14, marginLeft: 'auto' }}>
                <div className="tk-avatar" style={{ width: 30, height: 30, fontSize: 11 }}>
                    {initials}
                </div>

                <button
                    onClick={handleLogout}
                    title="Sign out"
                    style={{ background: 'none', border: 'none', cursor: 'pointer', display: 'flex', color: 'var(--tk-text-muted)' }}
                >
                    <LogOut size={16} strokeWidth={1.75} />
                </button>
            </div>
        </nav>
    );
}
