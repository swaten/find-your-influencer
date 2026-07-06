import { useEffect, useState } from 'react';

// two themes only: light and obsidian-gold (default)
export function useTheme() {
    const [theme, setTheme] = useState(() => localStorage.getItem('fyi-theme') || 'light');

    useEffect(() => {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('fyi-theme', theme);
    }, [theme]);

    const toggleTheme = () => setTheme((t) => (t === 'light' ? 'obsidian-gold' : 'light'));

    return { theme, toggleTheme };
}
