import React, { useState, useEffect } from 'react';
import { Card, Row, Col, Statistic, Tag, Space } from 'antd';
import { GiftOutlined, DollarOutlined, TrophyOutlined, CalendarOutlined } from '@ant-design/icons';

const LoginBonus = () => {
    const [bonusData, setBonusData] = useState(null);

    useEffect(() => {
        if (affiliateBloom?.login_bonus) {
            setBonusData(affiliateBloom.login_bonus);
        }
    }, []);

    if (!bonusData) {
        return <Card>Login bonus data not available</Card>;
    }

    return (
        <Space direction="vertical" size="middle" style={{ width: '100%' }}>
            {/* Main Bonus Card */}
            <Card
                title={
                    <Space>
                        <GiftOutlined />
                        Daily Login Bonus
                    </Space>
                }
                extra={
                    bonusData.can_claim_today ? (
                        <Tag color="orange">Available Tomorrow</Tag>
                    ) : (
                        <Tag color="green">Received Today</Tag>
                    )
                }
            >
                <Row gutter={16}>
                    <Col span={8}>
                        <Statistic
                            title="Daily Bonus"
                            value={bonusData.bonus_amount}
                            prefix={<DollarOutlined />}
                            precision={2}
                        />
                    </Col>
                    <Col span={8}>
                        <Statistic
                            title="Current Balance"
                            value={bonusData.current_balance}
                            prefix={<DollarOutlined />}
                            precision={2}
                        />
                    </Col>
                    <Col span={8}>
                        <Statistic
                            title="Login Streak"
                            value={bonusData.stats.streak_days}
                            prefix={<TrophyOutlined />}
                            suffix="days"
                        />
                    </Col>
                </Row>
            </Card>

            {/* Statistics Row */}
            <Row gutter={16}>
                <Col span={12}>
                    <Card>
                        <Statistic
                            title="Total Bonuses"
                            value={bonusData.stats.total_bonuses}
                            prefix={<GiftOutlined />}
                        />
                    </Card>
                </Col>
                <Col span={12}>
                    <Card>
                        <Statistic
                            title="Total Earned"
                            value={bonusData.stats.total_amount}
                            prefix={<DollarOutlined />}
                            precision={2}
                        />
                    </Card>
                </Col>
            </Row>

            {/* Last Bonus Info */}
            {bonusData.last_bonus_date && (
                <Card size="small">
                    <Space>
                        <CalendarOutlined />
                        Last bonus received: {bonusData.last_bonus_date}
                    </Space>
                </Card>
            )}
        </Space>
    );
};

export default LoginBonus;
