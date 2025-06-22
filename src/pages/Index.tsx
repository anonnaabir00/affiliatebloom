
import { useState } from "react";
import { Copy, TrendingUp, Users, DollarSign, Eye, MousePointer, Target, Award } from "lucide-react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, BarChart, Bar } from 'recharts';
import { Navigation } from "@/components/Navigation";

const Index = () => {
  const [currentPage, setCurrentPage] = useState('dashboard');

  // Sample data for charts
  const trafficData = [
    { day: 'Mon', views: 2200, uniqueVisitors: 1400 },
    { day: 'Tue', views: 3000, uniqueVisitors: 1900 },
    { day: 'Wed', views: 2500, uniqueVisitors: 1700 },
    { day: 'Thu', views: 2800, uniqueVisitors: 2000 },
    { day: 'Fri', views: 3500, uniqueVisitors: 2400 },
    { day: 'Sat', views: 4200, uniqueVisitors: 2800 },
    { day: 'Sun', views: 3800, uniqueVisitors: 2600 },
  ];

  const locationData = [
    { country: 'USA', visitors: 5200 },
    { country: 'Canada', visitors: 3800 },
    { country: 'UK', visitors: 2900 },
    { country: 'Germany', visitors: 2400 },
    { country: 'India', visitors: 1800 },
    { country: 'Australia', visitors: 1500 },
  ];

  const earningsData = [
    { month: 'Jan', earnings: 1200, clicks: 2400 },
    { month: 'Feb', earnings: 1900, clicks: 3200 },
    { month: 'Mar', earnings: 2300, clicks: 4100 },
    { month: 'Apr', earnings: 2800, clicks: 4800 },
    { month: 'May', earnings: 3200, clicks: 5200 },
    { month: 'Jun', earnings: 3800, clicks: 6100 },
  ];

  const conversionData = [
    { week: 'W1', conversions: 45, revenue: 890 },
    { week: 'W2', conversions: 52, revenue: 1240 },
    { week: 'W3', conversions: 38, revenue: 720 },
    { week: 'W4', conversions: 61, revenue: 1580 },
  ];

  const affiliateLinks = [
    { id: 1, name: "Premium Course Bundle", url: "https://example.com/ref/abc123", clicks: 1250, conversions: 45 },
    { id: 2, name: "Software Tool Pro", url: "https://example.com/ref/def456", clicks: 890, conversions: 32 },
    { id: 3, name: "Marketing Masterclass", url: "https://example.com/ref/ghi789", clicks: 2100, conversions: 78 },
  ];

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
  };

  const renderDashboard = () => (
    <div className="space-y-8">
      {/* Stats Overview */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <Card className="border border-gray-200 shadow-sm bg-gradient-to-br from-green-50 to-emerald-50">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-gray-600">Total Earnings</CardTitle>
            <div className="p-2 bg-green-100 rounded-full">
              <DollarSign className="h-4 w-4 text-green-600" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-gray-900">$15,200</div>
            <p className="text-xs text-green-600">+12.5% from last month</p>
          </CardContent>
        </Card>

        <Card className="border border-gray-200 shadow-sm bg-gradient-to-br from-blue-50 to-cyan-50">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-gray-600">Total Clicks</CardTitle>
            <div className="p-2 bg-blue-100 rounded-full">
              <MousePointer className="h-4 w-4 text-blue-600" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-gray-900">25,840</div>
            <p className="text-xs text-green-600">+8.2% from last month</p>
          </CardContent>
        </Card>

        <Card className="border border-gray-200 shadow-sm bg-gradient-to-br from-orange-50 to-amber-50">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-gray-600">Conversions</CardTitle>
            <div className="p-2 bg-orange-100 rounded-full">
              <Target className="h-4 w-4 text-orange-600" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-gray-900">1,286</div>
            <p className="text-xs text-green-600">+15.3% from last month</p>
          </CardContent>
        </Card>

        <Card className="border border-gray-200 shadow-sm bg-gradient-to-br from-purple-50 to-violet-50">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-gray-600">Conversion Rate</CardTitle>
            <div className="p-2 bg-purple-100 rounded-full">
              <TrendingUp className="h-4 w-4 text-purple-600" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-gray-900">4.98%</div>
            <p className="text-xs text-green-600">+0.8% from last month</p>
          </CardContent>
        </Card>
      </div>

      {/* Charts */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card className="border border-gray-200 shadow-sm bg-gradient-to-br from-slate-50 to-gray-50">
          <CardHeader className="border-b border-gray-100 bg-white/50">
            <CardTitle className="text-gray-900">Earnings Overview</CardTitle>
            <CardDescription className="text-gray-600">Monthly earnings and clicks performance</CardDescription>
          </CardHeader>
          <CardContent className="pt-6">
            <ResponsiveContainer width="100%" height={300}>
              <LineChart data={earningsData}>
                <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                <XAxis dataKey="month" stroke="#6b7280" />
                <YAxis stroke="#6b7280" />
                <Tooltip 
                  contentStyle={{ 
                    backgroundColor: 'white', 
                    border: '1px solid #e5e7eb',
                    borderRadius: '8px',
                    boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)'
                  }} 
                />
                <Line type="monotone" dataKey="earnings" stroke="#059669" strokeWidth={3} />
                <Line type="monotone" dataKey="clicks" stroke="#2563eb" strokeWidth={3} />
              </LineChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>

        <Card className="border border-gray-200 shadow-sm bg-gradient-to-br from-emerald-50 to-teal-50">
          <CardHeader className="border-b border-gray-100 bg-white/50">
            <CardTitle className="text-gray-900">Weekly Conversions</CardTitle>
            <CardDescription className="text-gray-600">Conversion tracking for this month</CardDescription>
          </CardHeader>
          <CardContent className="pt-6">
            <ResponsiveContainer width="100%" height={300}>
              <BarChart data={conversionData}>
                <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                <XAxis dataKey="week" stroke="#6b7280" />
                <YAxis stroke="#6b7280" />
                <Tooltip 
                  contentStyle={{ 
                    backgroundColor: 'white', 
                    border: '1px solid #e5e7eb',
                    borderRadius: '8px',
                    boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)'
                  }} 
                />
                <Bar dataKey="conversions" fill="#059669" radius={[4, 4, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>
      </div>
    </div>
  );

  const renderLinks = () => (
    <div className="space-y-6">
      <Card className="border border-gray-200 shadow-sm bg-gradient-to-br from-indigo-50 to-blue-50">
        <CardHeader className="border-b border-gray-100 bg-white/50">
          <CardTitle className="text-gray-900">My Affiliate Links</CardTitle>
          <CardDescription className="text-gray-600">Manage and track your affiliate links</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4 pt-6">
          {affiliateLinks.map((link) => (
            <div key={link.id} className="p-4 rounded-lg bg-white border border-gray-200 shadow-sm hover:shadow-md transition-shadow">
              <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div className="flex-1">
                  <h3 className="text-gray-900 font-medium">{link.name}</h3>
                  <p className="text-gray-600 text-sm break-all">{link.url}</p>
                  <div className="flex gap-4 mt-2 text-sm">
                    <span className="text-blue-600 font-medium">{link.clicks} clicks</span>
                    <span className="text-green-600 font-medium">{link.conversions} conversions</span>
                  </div>
                </div>
                <Button
                  onClick={() => copyToClipboard(link.url)}
                  variant="outline"
                  size="sm"
                  className="border-blue-300 text-blue-700 hover:bg-blue-50 hover:border-blue-400"
                >
                  <Copy className="h-4 w-4 mr-2" />
                  Copy
                </Button>
              </div>
            </div>
          ))}
        </CardContent>
      </Card>
    </div>
  );

  const renderPlaceholderPage = (title: string, description: string) => (
    <Card className="border border-gray-200 shadow-sm bg-gradient-to-br from-gray-50 to-slate-50">
      <CardHeader className="border-b border-gray-100 bg-white/50">
        <CardTitle className="text-gray-900">{title}</CardTitle>
        <CardDescription className="text-gray-600">{description}</CardDescription>
      </CardHeader>
      <CardContent className="pt-6">
        <p className="text-gray-700">This section is coming soon...</p>
      </CardContent>
    </Card>
  );

  const renderContent = () => {
    switch (currentPage) {
      case 'dashboard':
        return renderDashboard();
      case 'links':
        return renderLinks();
      case 'analytics':
        return renderPlaceholderPage('Analytics', 'Detailed analytics and insights');
      case 'earnings':
        return renderPlaceholderPage('Earnings', 'Detailed earnings breakdown');
      case 'referrals':
        return renderPlaceholderPage('Referrals', 'Manage your referral network');
      case 'reports':
        return renderPlaceholderPage('Reports', 'Generate and download reports');
      case 'settings':
        return renderPlaceholderPage('Settings', 'Account and notification settings');
      default:
        return renderDashboard();
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-gray-50 via-blue-50/30 to-indigo-50/20">
      {/* Header */}
      <header className="bg-white/80 backdrop-blur-sm border-b border-gray-200 shadow-sm sticky top-0 z-40">
        <div className="container mx-auto px-6 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-4">
              <Navigation currentPage={currentPage} onPageChange={setCurrentPage} />
              <h1 className="text-2xl font-bold bg-gradient-to-r from-gray-900 to-gray-700 bg-clip-text text-transparent">
                {navigationItems.find(item => item.id === currentPage)?.label || 'Dashboard'}
              </h1>
            </div>
            <div className="flex items-center gap-4">
              <div className="text-right">
                <p className="text-gray-500 text-sm">Welcome back,</p>
                <p className="text-gray-900 font-medium">John Doe</p>
              </div>
              <div className="w-10 h-10 rounded-full bg-gradient-to-r from-blue-100 to-indigo-100 flex items-center justify-center">
                <Users className="h-5 w-5 text-blue-600" />
              </div>
            </div>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <main className="container mx-auto px-6 py-8">
        {renderContent()}
      </main>
    </div>
  );
};

// Navigation items for reference
const navigationItems = [
  { id: 'dashboard', label: 'Dashboard' },
  { id: 'analytics', label: 'Analytics' },
  { id: 'links', label: 'My Links' },
  { id: 'earnings', label: 'Earnings' },
  { id: 'referrals', label: 'Referrals' },
  { id: 'reports', label: 'Reports' },
  { id: 'settings', label: 'Settings' },
];

export default Index;
