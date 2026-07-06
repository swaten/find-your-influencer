import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export function useLogin() {
    const { login } = useAuth();
    const navigate = useNavigate();
    const [fields, setFields] = useState({ email: '', password: '', remember: false });
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);

    const onChange = (e) => {
        const { name, value, type, checked } = e.target;
        setFields((f) => ({ ...f, [name]: type === 'checkbox' ? checked : value }));
    };

    const onSubmit = async (e) => {
        e.preventDefault();
        setError(null);
        setLoading(true);
        try {
            await login(fields);
            navigate('/influencers', { replace: true });
        } catch (err) {
            const message = err.response?.data?.message
                || err.response?.data?.errors?.email?.[0]
                || 'Unable to sign in. Check your credentials.';
            setError(message);
        } finally {
            setLoading(false);
        }
    };

    return { fields, error, loading, onChange, onSubmit };
}
