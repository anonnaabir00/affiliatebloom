
import { useState } from 'react';
import { Menu, Home, BarChart3, Users, Settings, Wallet, Link, FileText } from 'lucide-react';
import {
  Drawer,
  DrawerClose,
  DrawerContent,
  DrawerHeader,
  DrawerTitle,
  DrawerTrigger,
} from "@/components/ui/drawer";
import { Button } from "@/components/ui/button";

interface NavigationProps {
  currentPage: string;
  onPageChange: (page: string) => void;
}

const navigationItems = [
  { id: 'dashboard', label: 'Dashboard', icon: Home },
  { id: 'analytics', label: 'Analytics', icon: BarChart3 },
  { id: 'links', label: 'My Links', icon: Link },
  { id: 'earnings', label: 'Earnings', icon: Wallet },
  { id: 'referrals', label: 'Referrals', icon: Users },
  { id: 'reports', label: 'Reports', icon: FileText },
  { id: 'settings', label: 'Settings', icon: Settings },
];

export function Navigation({ currentPage, onPageChange }: NavigationProps) {
  const [open, setOpen] = useState(false);

  const handlePageChange = (pageId: string) => {
    onPageChange(pageId);
    setOpen(false);
  };

  return (
    <Drawer open={open} onOpenChange={setOpen} direction="left">
      <DrawerTrigger asChild>
        <Button variant="ghost" size="icon" className="text-gray-700 hover:bg-gray-100">
          <Menu className="h-6 w-6" />
        </Button>
      </DrawerTrigger>
      <DrawerContent className="h-full w-80 max-w-[80vw] bg-white border-r border-gray-200 rounded-none">
        <DrawerHeader className="border-b border-gray-100">
          <DrawerTitle className="text-2xl font-bold text-gray-900">
            Affiliate Portal
          </DrawerTitle>
        </DrawerHeader>
        <div className="px-4 py-6 flex-1">
          <div className="grid gap-2">
            {navigationItems.map((item) => {
              const Icon = item.icon;
              const isActive = currentPage === item.id;
              
              return (
                <Button
                  key={item.id}
                  variant={isActive ? "default" : "ghost"}
                  className={`justify-start gap-3 h-12 ${
                    isActive 
                      ? "bg-blue-600 text-white hover:bg-blue-700" 
                      : "text-gray-700 hover:bg-gray-100"
                  }`}
                  onClick={() => handlePageChange(item.id)}
                >
                  <Icon className="h-5 w-5" />
                  {item.label}
                </Button>
              );
            })}
          </div>
        </div>
      </DrawerContent>
    </Drawer>
  );
}
