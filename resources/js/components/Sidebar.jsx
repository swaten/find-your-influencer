import React from 'react';
import { NavLink } from 'react-router-dom';
import { Users2, LayoutDashboard } from 'lucide-react';

// static nav for now - swap to an API-driven menu once more modules exist
const NAV_ITEMS = [
    { to: '/influencers', label: 'Influencers', icon: Users2 },
];

function NavItem({ to, label, icon: Icon }) {
    return (
        <NavLink to={to} className={({ isActive }) => `tk-nav-item${isActive ? ' active' : ''}`}>
            <Icon size={16} strokeWidth={1.75} className="tk-nav-icon" />
            <span>{label}</span>
        </NavLink>
    );
}

export default function Sidebar({ open = true }) {
    return (
        <aside className={`tk-sidebar${open ? '' : ' mobile-open'}`}>
            <nav className="tk-sidebar-nav" aria-label="Sidebar navigation">
                <div className="tk-nav-section">
                    <span className="tk-nav-label">Navigation</span>
                    {NAV_ITEMS.map((item) => (
                        <NavItem key={item.to} {...item} />
                    ))}
                </div>
            </nav>
        </aside>
    );
}
