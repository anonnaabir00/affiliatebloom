import React, { useState } from 'react';
import { Form, Input, Button, Checkbox, Alert, Typography } from 'antd';
import { UserOutlined, LockOutlined } from '@ant-design/icons';
import {fetchData} from "../services/fetchData.js";

const { Title } = Typography;

const AuthLogin = () => {
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState({ type: '', text: '' });
    const [form] = Form.useForm();

    // Handle login form submission
    const handleLogin = (values) => {
        setLoading(true);
        setMessage({ type: '', text: '' });

        fetchData('affiliate_bloom_partner_login', (response) => {
            setLoading(false);
            if (response.success) {
                setMessage({ type: 'success', text: response.data.message });
                // Redirect if URL provided
                if (response.data.redirect_url) {
                    setTimeout(() => {
                        window.location.href = response.data.redirect_url;
                    }, 1500);
                }
            } else {
                setMessage({
                    type: 'error',
                    text: response.data?.message || 'Login failed. Please try again.'
                });
            }
        }, {
            username: values.username,
            password: values.password,
            remember: values.remember || false
        });
    };

    return (
        <div className="flex items-center justify-center">
            <div className="w-full max-w-md">
                <div className="">
                    {/* Alert Message */}
                    {message.text && (
                        <Alert
                            message={message.text}
                            type={message.type}
                            showIcon
                            className="mb-6"
                            closable
                            onClose={() => setMessage({ type: '', text: '' })}
                        />
                    )}

                    <Form
                        form={form}
                        name="login"
                        onFinish={handleLogin}
                        layout="vertical"
                        size="large"
                    >
                        <Form.Item
                            name="username"
                            label="Username or Email"
                            rules={[
                                {
                                    required: true,
                                    message: 'Please enter your username or email!',
                                },
                            ]}
                        >
                            <Input
                                prefix={<UserOutlined className="text-gray-400" />}
                                placeholder="Enter username or email"
                                disabled={loading}
                            />
                        </Form.Item>

                        <Form.Item
                            name="password"
                            label="Password"
                            rules={[
                                {
                                    required: true,
                                    message: 'Please enter your password!',
                                },
                            ]}
                        >
                            <Input.Password
                                prefix={<LockOutlined className="text-gray-400" />}
                                placeholder="Enter password"
                                disabled={loading}
                            />
                        </Form.Item>

                        <Form.Item name="remember" valuePropName="checked" className="mb-6">
                            <Checkbox disabled={loading}>
                                Remember me
                            </Checkbox>
                        </Form.Item>

                        <Form.Item className="mb-4">
                            <Button
                                type="primary"
                                htmlType="submit"
                                className="w-full h-12"
                                loading={loading}
                                size="large"
                            >
                                {loading ? 'Signing In...' : 'Sign In'}
                            </Button>
                        </Form.Item>

                        <div className="text-center">
                            <a
                                href="/forgot-password"
                                className="text-blue-600 hover:text-blue-800 text-sm"
                            >
                                Forgot your password?
                            </a>
                        </div>

                        <div className="text-center mt-6 pt-6 border-t border-gray-200">
                            <span className="text-gray-600 text-sm">
                                Don't have an account?{' '}
                            </span>
                            <a
                                href="/register"
                                className="text-blue-600 hover:text-blue-800 font-medium text-sm"
                            >
                                Sign up here
                            </a>
                        </div>
                    </Form>
                </div>
            </div>
        </div>
    );
};

export default AuthLogin;