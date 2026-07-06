import React from 'react';

export default function Header() {
  return (
    <header id="page-topbar">
      <div className="navbar-header">
        <div className="d-flex">
          <a href="/" className="logo">
            <img src="/shreyu/assets/images/logo-light.png" alt="logo" height="20" />
          </a>
        </div>
        <div className="d-flex">
          {/* top‐right icons, profile dropdown, etc. */}
        </div>
      </div>
    </header>
  );
}
