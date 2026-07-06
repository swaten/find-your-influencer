import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export function useRegister() {
    const { register } = useAuth();
    const navigate = useNavigate();
    const [fields, setFields] = useState({ name: '', email: '', password: '', password_confirmation: '' });
    const [errors, setErrors] = useState({});
    const [loading, setLoading] = useState(false);

    const onChange = (e) => {
        const { name, value } = e.target;
        setFields((f) => ({ ...f, [name]: value }));
    };

    const onSubmit = async (e) => {
        e.preventDefault();
        setErrors({});
        setLoading(true);
        try {
            await register(fields);
            // per spec: registration never auto-logs-in, always send the user to /login
            navigate('/login', { replace: true, state: { registered: true } });
        } catch (err) {
            setErrors(err.response?.data?.errors || { form: ['Unable to register. Please try again.'] });
        } finally {
            setLoading(false);
        }
    };

    return { fields, errors, loading, onChange, onSubmit };
}
