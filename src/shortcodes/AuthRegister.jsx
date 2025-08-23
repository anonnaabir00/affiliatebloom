import React, { useState } from 'react';
import { Form, Input, Button, Checkbox, Alert, Typography, Row, Col } from 'antd';
import { UserOutlined, LockOutlined, MailOutlined, PhoneOutlined, GlobalOutlined } from '@ant-design/icons';
import {fetchData} from "../services/fetchData.js";

const { Title } = Typography;

const AuthRegister = () => {
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState({ type: '', text: '' });
    const [form] = Form.useForm();

    // Handle registration form submission
    const handleRegister = (values) => {
        setLoading(true);
        setMessage({ type: '', text: '' });

        fetchData('affiliate_bloom_partner_register', (response) => {
            setLoading(false);
            if (response.success) {
                setMessage({ type: 'success', text: response.data.message });
                // Clear form on success
                form.resetFields();
                // Redirect if URL provided
                if (response.data.redirect_url) {
                    setTimeout(() => {
                        window.location.href = response.data.redirect_url;
                    }, 2000);
                }
            } else {
                setMessage({
                    type: 'error',
                    text: response.data?.message || 'Registration failed. Please try again.'
                });
            }
        }, {
            username: values.username,
            email: values.email,
            password: values.password,
            confirm_password: values.confirm_password,
            first_name: values.first_name,
            last_name: values.last_name,
            phone: values.phone || '',
            website: values.website || '',
            terms: values.terms || false
        });
    };

    return (
        <div className="min-h-screen flex items-center justify-center p-4">
            <div className="w-full max-w-2xl">
                <div className="bg-white p-8 rounded-lg shadow-lg">
                    <div className="text-center mb-8">
                        <Title level={2} className="!mb-2">
                            Partner Registration
                        </Title>
                        <p className="text-gray-600">
                            Join our partner program and start earning
                        </p>
                    </div>

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
                        name="register"
                        onFinish={handleRegister}
                        layout="vertical"
                        size="large"
                        scrollToFirstError
                    >
                        {/* Name Fields */}
                        <Row gutter={16}>
                            <Col xs={24} sm={12}>
                                <Form.Item
                                    name="first_name"
                                    label="First Name"
                                    rules={[
                                        {
                                            required: true,
                                            message: 'Please enter your first name!',
                                        },
                                        {
                                            min: 2,
                                            message: 'First name must be at least 2 characters!',
                                        },
                                    ]}
                                >
                                    <Input
                                        placeholder="Enter first name"
                                        disabled={loading}
                                    />
                                </Form.Item>
                            </Col>
                            <Col xs={24} sm={12}>
                                <Form.Item
                                    name="last_name"
                                    label="Last Name"
                                    rules={[
                                        {
                                            required: true,
                                            message: 'Please enter your last name!',
                                        },
                                        {
                                            min: 2,
                                            message: 'Last name must be at least 2 characters!',
                                        },
                                    ]}
                                >
                                    <Input
                                        placeholder="Enter last name"
                                        disabled={loading}
                                    />
                                </Form.Item>
                            </Col>
                        </Row>

                        {/* Username */}
                        <Form.Item
                            name="username"
                            label="Username"
                            rules={[
                                {
                                    required: true,
                                    message: 'Please enter your username!',
                                },
                                {
                                    min: 3,
                                    message: 'Username must be at least 3 characters!',
                                },
                                {
                                    pattern: /^[a-zA-Z0-9_]+$/,
                                    message: 'Username can only contain letters, numbers, and underscores!',
                                },
                            ]}
                        >
                            <Input
                                prefix={<UserOutlined className="text-gray-400" />}
                                placeholder="Enter username"
                                disabled={loading}
                            />
                        </Form.Item>

                        {/* Email */}
                        <Form.Item
                            name="email"
                            label="Email Address"
                            rules={[
                                {
                                    required: true,
                                    message: 'Please enter your email!',
                                },
                                {
                                    type: 'email',
                                    message: 'Please enter a valid email address!',
                                },
                            ]}
                        >
                            <Input
                                prefix={<MailOutlined className="text-gray-400" />}
                                placeholder="Enter email address"
                                disabled={loading}
                            />
                        </Form.Item>

                        {/* Optional Fields */}
                        <Row gutter={16}>
                            <Col xs={24} sm={12}>
                                <Form.Item
                                    name="phone"
                                    label="Phone Number (Optional)"
                                    rules={[
                                        {
                                            pattern: /^[+]?[\d\s\-()]+$/,
                                            message: 'Please enter a valid phone number!',
                                        },
                                    ]}
                                >
                                    <Input
                                        prefix={<PhoneOutlined className="text-gray-400" />}
                                        placeholder="Enter phone number"
                                        disabled={loading}
                                    />
                                </Form.Item>
                            </Col>
                            <Col xs={24} sm={12}>
                                <Form.Item
                                    name="website"
                                    label="Website URL (Optional)"
                                    rules={[
                                        {
                                            type: 'url',
                                            message: 'Please enter a valid website URL!',
                                        },
                                    ]}
                                >
                                    <Input
                                        prefix={<GlobalOutlined className="text-gray-400" />}
                                        placeholder="https://example.com"
                                        disabled={loading}
                                    />
                                </Form.Item>
                            </Col>
                        </Row>

                        {/* Password Fields */}
                        <Row gutter={16}>
                            <Col xs={24} sm={12}>
                                <Form.Item
                                    name="password"
                                    label="Password"
                                    rules={[
                                        {
                                            required: true,
                                            message: 'Please enter your password!',
                                        },
                                        {
                                            min: 6,
                                            message: 'Password must be at least 6 characters!',
                                        },
                                    ]}
                                    hasFeedback
                                >
                                    <Input.Password
                                        prefix={<LockOutlined className="text-gray-400" />}
                                        placeholder="Enter password"
                                        disabled={loading}
                                    />
                                </Form.Item>
                            </Col>
                            <Col xs={24} sm={12}>
                                <Form.Item
                                    name="confirm_password"
                                    label="Confirm Password"
                                    dependencies={['password']}
                                    rules={[
                                        {
                                            required: true,
                                            message: 'Please confirm your password!',
                                        },
                                        ({ getFieldValue }) => ({
                                            validator(_, value) {
                                                if (!value || getFieldValue('password') === value) {
                                                    return Promise.resolve();
                                                }
                                                return Promise.reject(new Error('Passwords do not match!'));
                                            },
                                        }),
                                    ]}
                                    hasFeedback
                                >
                                    <Input.Password
                                        prefix={<LockOutlined className="text-gray-400" />}
                                        placeholder="Confirm password"
                                        disabled={loading}
                                    />
                                </Form.Item>
                            </Col>
                        </Row>

                        {/* Terms and Conditions */}
                        <Form.Item
                            name="terms"
                            valuePropName="checked"
                            rules={[
                                {
                                    validator: (_, value) =>
                                        value ? Promise.resolve() : Promise.reject(new Error('You must accept the terms and conditions')),
                                },
                            ]}
                            className="mb-6"
                        >
                            <Checkbox disabled={loading}>
                                I agree to the{' '}
                                <a
                                    href="/terms"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-blue-600 hover:text-blue-800"
                                >
                                    Terms and Conditions
                                </a>
                                {' '}and{' '}
                                <a
                                    href="/privacy"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-blue-600 hover:text-blue-800"
                                >
                                    Privacy Policy
                                </a>
                            </Checkbox>
                        </Form.Item>

                        {/* Submit Button */}
                        <Form.Item className="mb-4">
                            <Button
                                type="primary"
                                htmlType="submit"
                                className="w-full h-12"
                                loading={loading}
                                size="large"
                            >
                                {loading ? 'Creating Account...' : 'Create Account'}
                            </Button>
                        </Form.Item>

                        {/* Login Link */}
                        <div className="text-center pt-6 border-t border-gray-200">
                            <span className="text-gray-600 text-sm">
                                Already have an account?{' '}
                            </span>
                            <a
                                href="/login"
                                className="text-blue-600 hover:text-blue-800 font-medium text-sm"
                            >
                                Sign in here
                            </a>
                        </div>
                    </Form>
                </div>
            </div>
        </div>
    );
};

export default AuthRegister;