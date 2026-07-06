import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';

import '../css/app.css';
import './bootstrap';

import { AuthProvider } from './context/AuthContext';
import ProtectedRoute from './routes/ProtectedRoute';

import Login from './pages/Login';
import Register from './pages/Register';
import Influencers from './pages/Influencers';
import InfluencerDetail from './pages/InfluencerDetail';

createRoot(document.getElementById('app')).render(
    <BrowserRouter>
        <AuthProvider>
            <Routes>
                <Route path="/" element={<Navigate to="/influencers" replace />} />
                <Route path="/login" element={<Login />} />
                <Route path="/register" element={<Register />} />
                <Route
                    path="/influencers"
                    element={(
                        <ProtectedRoute>
                            <Influencers />
                        </ProtectedRoute>
                    )}
                />
                <Route
                    path="/influencers/:id"
                    element={(
                        <ProtectedRoute>
                            <InfluencerDetail />
                        </ProtectedRoute>
                    )}
                />
                <Route path="*" element={<Navigate to="/influencers" replace />} />
            </Routes>
        </AuthProvider>
    </BrowserRouter>,
);
