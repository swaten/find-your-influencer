import React, { useState } from 'react';
import Navbar from './Navbar';
import Sidebar from './Sidebar';

export default function MainLayout({ children }) {
    const [sidebarOpen, setSidebarOpen] = useState(true);

    return (
        <div className="tk-app-shell">
            <Navbar onMenuToggle={() => setSidebarOpen((v) => !v)} />
            <Sidebar open={sidebarOpen} />
            <main
                className="tk-page-inner"
                style={{
                    marginLeft: sidebarOpen ? 'var(--tk-sidebar-width)' : 0,
                    marginTop: 'var(--tk-navbar-height)',
                    // width:100% from .tk-page-inner is relative to the viewport (sidebar is
                    // position:fixed, out of flow) - subtract the margin so it doesn't overflow
                    width: sidebarOpen ? 'calc(100% - var(--tk-sidebar-width))' : '100%',
                    boxSizing: 'border-box',
                    transition: 'margin-left 0.15s, width 0.15s',
                }}
            >
                {children}
            </main>
        </div>
    );
}
