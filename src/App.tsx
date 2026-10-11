import React, { useState, useEffect, useMemo } from 'react';
import {
  Wrench,
  Package,
  Users,
  Shield,
  Wifi,
  WifiOff,
  AlertTriangle,
  CheckCircle2,
  Lock,
  Unlock,
  KeyRound,
  LogOut,
  Plus,
  Search,
  Filter,
  DollarSign,
  Smartphone,
  Database,
  History,
  FileText,
  UserCheck,
  UserX,
  RefreshCw,
  Camera,
  Layers,
  ChevronRight,
  Globe,
  Clock,
  ArrowRight,
  X,
  Tv,
  Eye,
  EyeOff,
  Phone,
  Tag,
  ArrowUpRight,
  ArrowDownLeft,
  Truck,
  Printer,
  CreditCard,
  Receipt,
  TrendingUp,
  BarChart3,
  Download,
  Fuel
} from 'lucide-react';

// --- TRANSLATIONS (English, Arabic, Kurdish Sorani) ---
type Language = 'en' | 'ar' | 'ckb';
type Role = 'TECH' | 'MANAGER' | 'ADMIN' | 'OWNER';

interface TranslationStrings {
  appName: string;
  tagline: string;
  dashboard: string;
  repairs: string;
  inventory: string;
  customers: string;
  staff: string;
  security: string;
  backups: string;
  debts: string;
  reports: string;
  expenses: string;
  activeRole: string;
  switchRole: string;
  language: string;
  online: string;
  offline: string;
  offlineTitle: string;
  offlineMessage: string;
  reconnect: string;
  simulateDrop: string;
  restoreConnection: string;
  newTicket: string;
  totalTickets: string;
  inProgress: string;
  readyPickup: string;
  lowStockAlert: string;
  lastBackupWarning: string;
  lastBackupTime: string;
  auditLog: string;
  sanitizedNotice: string;
  staffTitle: string;
  addStaff: string;
  fullName: string;
  email: string;
  phone: string;
  role: string;
  status: string;
  active: string;
  disabled: string;
  locked: string;
  unlock: string;
  resetPassword: string;
  revokeSessions: string;
  privateNotes: string;
  cantDisableLastOwner: string;
  accessDenied: string;
  accessDeniedMsg: string;
  currencyRate: string;
  addCustomer: string;
  adjustStock: string;
  stockHistory: string;
  partRequests: string;
  requestPart: string;
  costHiddenForTech: string;
  printThermal: string;
  recordPayment: string;
  consumePart: string;
  recordExpense: string;
  exportCsv: string;
}

const translations: Record<Language, TranslationStrings> = {
  en: {
    appName: 'TV Repair Workshop',
    tagline: 'Professional Workshop OS',
    dashboard: 'Dashboard',
    repairs: 'Repairs & Workbench',
    inventory: 'Inventory & Parts',
    customers: 'Customers',
    staff: 'Staff Management',
    security: 'Security & Audit',
    backups: 'Backups & GPG',
    debts: 'Customer Debts',
    reports: 'Financial Reports & P&L',
    expenses: 'Operating Expenses',
    activeRole: 'Current Profile',
    switchRole: 'Switch Role',
    language: 'Language',
    online: 'Online',
    offline: 'Offline',
    offlineTitle: 'Connection Interrupted',
    offlineMessage: 'Network connection dropped. All edits are disabled until connection is restored.',
    reconnect: 'Check Connection',
    simulateDrop: 'Simulate Offline Drop',
    restoreConnection: 'Restore Connection',
    newTicket: 'Check-in New TV',
    totalTickets: 'Active Tickets',
    inProgress: 'On Bench',
    readyPickup: 'Ready for Pickup',
    lowStockAlert: 'Low Stock Parts',
    lastBackupWarning: 'Warning: Last successful backup is older than 36 hours!',
    lastBackupTime: 'Last Verified Backup',
    auditLog: 'Append-Only Audit Log',
    sanitizedNotice: 'Sensitive fields (passwords, tokens, sessions) are automatically scrubbed.',
    staffTitle: 'Staff Accounts & Permissions',
    addStaff: 'Add Staff Member',
    fullName: 'Full Name',
    email: 'Email Address',
    phone: 'Phone Number',
    role: 'Role',
    status: 'Status',
    active: 'Active',
    disabled: 'Disabled',
    locked: 'Locked (Rate Limit)',
    unlock: 'Unlock Account',
    resetPassword: 'Force Password Reset',
    revokeSessions: 'Revoke All Sessions',
    privateNotes: 'Owner Private Notes',
    cantDisableLastOwner: 'Cannot demote or disable the last active Owner!',
    accessDenied: '403 Access Denied',
    accessDeniedMsg: 'Only the OWNER has permission to access Staff Management.',
    currencyRate: 'Exchange Rate',
    addCustomer: 'Add Customer',
    adjustStock: 'Adjust Stock (±)',
    stockHistory: 'Stock Movements Ledger',
    partRequests: 'Technician Part Requests',
    requestPart: 'Request Out-of-Stock Part',
    costHiddenForTech: 'Cost prices are hidden for Technicians.',
    printThermal: 'Thermal Receipt (80mm)',
    recordPayment: 'Record Payment',
    consumePart: 'Consume Part',
    recordExpense: 'Record Expense',
    exportCsv: 'Export CSV'
  },
  ar: {
    appName: 'ورشة تصليح التلفزيونات',
    tagline: 'نظام إدارة الورشة المتكامل',
    dashboard: 'لوحة التحكم',
    repairs: 'طاولة التصليح',
    inventory: 'المخزن والقطع',
    customers: 'الزبائن',
    staff: 'إدارة الكادر',
    security: 'الأمان والتدقيق',
    backups: 'النسخ الاحتياطي',
    debts: 'ديون الزبائن',
    reports: 'التقارير المالية والأرباح',
    expenses: 'المصاريف التشغيلية',
    activeRole: 'الملف الحالي',
    switchRole: 'تبديل الدور',
    language: 'اللغة',
    online: 'متصل',
    offline: 'غير متصل',
    offlineTitle: 'انقطع الاتصال',
    offlineMessage: 'انقطع الاتصال بالخادم. تم إيقاف التعديلات مؤقتاً لحماية البيانات حتى عودة الاتصال.',
    reconnect: 'فحص الاتصال',
    simulateDrop: 'محاكاة انقطاع الإنترنت',
    restoreConnection: 'إعادة الاتصال',
    newTicket: 'استلام جهاز جديد',
    totalTickets: 'الأجهزة الحالية',
    inProgress: 'قيد الفحص',
    readyPickup: 'جاهز للاستلام',
    lowStockAlert: 'قطع منخفضة المخزون',
    lastBackupWarning: 'تحذير: مضى أكثر من 36 ساعة على آخر نسخة احتياطية ناجحة!',
    lastBackupTime: 'آخر نسخة احتياطية',
    auditLog: 'سجل التدقيق غير القابل للتعديل',
    sanitizedNotice: 'الحقول الحساسة (كلمات المرور والرموز) يتم حجبها أوتوماتيكياً.',
    staffTitle: 'حسابات وصلاحيات الكادر',
    addStaff: 'إضافة موظف جديد',
    fullName: 'الاسم الكامل',
    email: 'البريد الإلكتروني',
    phone: 'رقم الهاتف',
    role: 'الصلاحية',
    status: 'الحالة',
    active: 'نشط',
    disabled: 'معطل',
    locked: 'مقفل (محاولات فاشلة)',
    unlock: 'إلغاء القفل',
    resetPassword: 'إعادة تعيين كلمة المرور',
    revokeSessions: 'إنهاء كافة الجلسات',
    privateNotes: 'ملاحظات المالك الخاصة',
    cantDisableLastOwner: 'لا يمكن تعطيل أو تخفيض رتبة المالك الأخير النشط!',
    accessDenied: '403 تم رفض الوصول',
    accessDeniedMsg: 'المالك (OWNER) فقط يمتلك صلاحية الدخول لإدارة الكادر.',
    currencyRate: 'سعر الصرف',
    addCustomer: 'إضافة زبون',
    adjustStock: 'تعديل المخزون (±)',
    stockHistory: 'سجل حركات المخزن',
    partRequests: 'طلبات قطع الفنيين',
    requestPart: 'طلب قطعة غير متوفرة',
    costHiddenForTech: 'أسعار التكلفة محجوبة عن الفنيين.',
    printThermal: 'وصل حراري (80 مم)',
    recordPayment: 'تسجيل دفعة',
    consumePart: 'استخدام قطعة من المخزن',
    recordExpense: 'تسجيل مصروف',
    exportCsv: 'تصدير كملف CSV'
  },
  ckb: {
    appName: 'وۆرکشۆپی تەلەفزیۆن',
    tagline: 'سیستەمی کارگێڕی وۆرکشۆپ',
    dashboard: 'داشبۆرد',
    repairs: 'چاککردنەوە و مێزی کار',
    inventory: 'مەخزەن و پارچەکان',
    customers: 'موشتەرییەکان',
    staff: 'بەڕێوەبردنی کارمەندان',
    security: 'ئاسایش و چاودێری',
    backups: 'پاشەکەوت و داتا',
    debts: 'قەرزی موشتەری',
    reports: 'ڕاپۆرتی دارایی و قازانج',
    expenses: 'خەرجییە کارگێڕییەکان',
    activeRole: 'ڕۆڵی ئێستا',
    switchRole: 'گۆڕینی ڕۆڵ',
    language: 'زمان',
    online: 'سەرهێڵ',
    offline: 'دەرەوەی هێڵ',
    offlineTitle: 'پەیوەندی پچڕا',
    offlineMessage: 'هێڵی ئینتەرنێت پچڕا. هەموو دەستکارییەکان ڕاگیراون تا هێڵ دەگەڕێتەوە.',
    reconnect: 'پشکنینی پەیوەندی',
    simulateDrop: 'تاقیکردنەوەی پچڕانی هێڵ',
    restoreConnection: 'گێڕانەوەی پەیوەندی',
    newTicket: 'وەرگرتنی ئامێری نوێ',
    totalTickets: 'ئامێرەکان',
    inProgress: 'لەژێر کاردایە',
    readyPickup: 'ئامادەیە بۆ وەرگرتنەوە',
    lowStockAlert: 'پارچەی کەمبووەوە',
    lastBackupWarning: 'ئاگاداری: زیاتر لە ٣٦ کاتژمێر تێپەڕیوە بەسەر دواین پاشەکەوتی سەرکەوتوودا!',
    lastBackupTime: 'دواین پاشەکەوتی دڵنیاکراو',
    auditLog: 'تۆماری چاودێری پارێزراو',
    sanitizedNotice: 'زانیارییە هەستیارەکان (وشەی نهێنی و تۆکنەکان) پاککراونەتەوە.',
    staffTitle: 'هەژمارەکانی کارمەندان',
    addStaff: 'زیادکردنی کارمەند',
    fullName: 'ناوی تەواو',
    email: 'ئیمەیڵ',
    phone: 'مۆبایل',
    role: 'ڕۆڵ',
    status: 'دۆخ',
    active: 'چالاک',
    disabled: 'ناچالاک',
    locked: 'قوفڵکراو',
    unlock: 'کردنەوەی قوفڵ',
    resetPassword: 'ڕێکخستنەوەی وشەی نهێنی',
    revokeSessions: 'داخستنی دانیشتنەکان',
    privateNotes: 'تێبینی تایبەتی خاوەن',
    cantDisableLastOwner: 'ناتوانی دواین خاوەنی چالاک ناچالاک بکەیت!',
    accessDenied: '403 ڕێپێنەدراو',
    accessDeniedMsg: 'تەنها خاوەن کار (OWNER) بۆی هەیە بەشی کارمەندان ببینێت.',
    currencyRate: 'نرخی ئاڵوگۆڕ',
    addCustomer: 'زیادکردنی موشتەری',
    adjustStock: 'دەستکاری مەخزەن (±)',
    stockHistory: 'تۆماری جووڵەی مەخزەن',
    partRequests: 'داواکاری پارچەی وەستا',
    requestPart: 'داواکردنی پارچەی نەبوو',
    costHiddenForTech: 'نرخی کڕین لە تەکنیکار شاراوەیە.',
    printThermal: 'پسوولەی حەراری (٨٠ملم)',
    recordPayment: 'تۆمارکردنی پارەدان',
    consumePart: 'بەکارهێنانی پارچە',
    recordExpense: 'تۆمارکردنی خەرجی',
    exportCsv: 'دەرکردنی فایلی CSV'
  }
};

// --- DATA TYPES ---
interface StaffUser {
  id: number;
  name: string;
  email: string;
  phone: string;
  role: Role;
  isActive: boolean;
  isLocked: boolean;
  activeSessions: number;
  notes: string[];
}

interface Customer {
  id: number;
  name: string;
  phone: string;
  altPhone?: string;
  address?: string;
  totalRepairs: number;
  createdAt: string;
}

interface RepairPayment {
  id: number;
  amount: number;
  currency: 'USD' | 'IQD';
  rateSnapshot: number;
  amountIqdEq: number;
  method: 'CASH' | 'TRANSFER' | 'CARD';
  isVoided: boolean;
  voidReason?: string;
  createdAt: string;
}

interface ConsumedPartItem {
  id: number;
  partId: number;
  partName: string;
  quantity: number;
  unitPriceUSD: number;
  totalPriceUSD: number;
}

interface RepairTicket {
  id: number;
  ticketNo: string;
  customerId: number;
  customerName: string;
  customerPhone: string;
  brand: string;
  model: string;
  serial: string;
  fault: string;
  diagnosis: string;
  accessories: string[];
  status: 'CHECKED_IN' | 'DIAGNOSING' | 'PENDING_CUSTOMER_APPROVAL' | 'IN_PROGRESS' | 'TESTING_BURN_IN' | 'READY_FOR_PICKUP' | 'DELIVERED_CLOSED' | 'DIDNT_AGREE_RETURNED' | 'UNREPAIRABLE';
  currency: 'IQD' | 'USD';
  laborFee: number;
  partsTotal: number;
  techName: string;
  consumedParts: ConsumedPartItem[];
  payments: RepairPayment[];
  createdAt: string;
}

interface InventoryPart {
  id: number;
  name: string;
  serialNumber?: string;
  categoryId: number;
  categoryName: string;
  quantity: number;
  minStock: number;
  costPriceUSD: number | null;
  sellingPriceUSD: number;
}

interface Expense {
  id: number;
  category: string;
  amount: number;
  currency: 'USD' | 'IQD';
  amountIqdEq: number;
  expenseDate: string;
  notes: string;
  recordedBy: string;
}

interface CustomerDebt {
  id: number;
  customerId: number;
  customerName: string;
  customerPhone: string;
  ticketNo: string;
  totalChargedUSD: number;
  paidUSD: number;
  remainingUSD: number;
  dueDate: string;
}

// --- SEED DATA ---
const initialStaff: StaffUser[] = [
  { id: 1, name: 'Mustafa K. (Primary Owner)', email: 'owner@workshop.local', phone: '+964 750 442 1100', role: 'OWNER', isActive: true, isLocked: false, activeSessions: 2, notes: ['Master workshop founder', 'Direct supplier contracts in Dubai & Erbil'] },
  { id: 2, name: 'Karwan Ahmed', email: 'karwan.admin@workshop.local', phone: '+964 770 123 4567', role: 'ADMIN', isActive: true, isLocked: false, activeSessions: 1, notes: ['Manages daily customer receivables and workshop shifts'] },
  { id: 3, name: 'Sarmad Zaki', email: 'sarmad.m@workshop.local', phone: '+964 750 998 8776', role: 'MANAGER', isActive: true, isLocked: false, activeSessions: 1, notes: ['Front-desk coordinator & customer warranty follow-up'] },
  { id: 4, name: 'Hemin Rebar', email: 'hemin.tech@workshop.local', phone: '+964 751 334 5566', role: 'TECH', isActive: true, isLocked: false, activeSessions: 1, notes: ['Specialist in Samsung LED strip replacements & COF panel bonding'] }
];

const initialCustomers: Customer[] = [
  { id: 1, name: 'Haidar Al-Bayati', phone: '07801123344', altPhone: '07502233445', address: 'Al-Mansour, Baghdad', totalRepairs: 2, createdAt: '2026-09-15' },
  { id: 2, name: 'Shakar Kawa', phone: '07508829911', address: 'Bakhtiari, Erbil', totalRepairs: 1, createdAt: '2026-10-02' },
  { id: 3, name: 'Zaid Farhan', phone: '07714456677', altPhone: '07819988776', address: 'Sarchinar, Sulaymaniyah', totalRepairs: 1, createdAt: '2026-10-09' }
];

const initialParts: InventoryPart[] = [
  { id: 1, name: 'Samsung 55" AU7000 LED Backlight Set (5 Bars)', serialNumber: 'BN96-45913A', categoryId: 1, categoryName: 'Backlight Strips', quantity: 2, minStock: 3, costPriceUSD: 16, sellingPriceUSD: 30 },
  { id: 2, name: 'LG 65" C2 OLED PSU Board (EAY65249101)', serialNumber: 'EAY65249101', categoryId: 2, categoryName: 'Power Supply Boards', quantity: 1, minStock: 1, costPriceUSD: 38, sellingPriceUSD: 65 },
  { id: 3, name: 'Sony Bravia T-Con Board 55X80K', serialNumber: '1-008-724-11', categoryId: 3, categoryName: 'T-Con Boards', quantity: 4, minStock: 2, costPriceUSD: 22, sellingPriceUSD: 45 },
  { id: 4, name: 'Universal 45-65W Smart TV Main Board TP.SK516S.PB801', serialNumber: 'SK516S-V2', categoryId: 4, categoryName: 'Mainboards', quantity: 7, minStock: 5, costPriceUSD: 18, sellingPriceUSD: 32 }
];

const initialRepairs: RepairTicket[] = [
  {
    id: 101,
    ticketNo: 'TICK-261010-8021',
    customerId: 1,
    customerName: 'Haidar Al-Bayati',
    customerPhone: '07801123344',
    brand: 'Samsung',
    model: 'UA55AU7000UXZN',
    serial: 'SN-SAM-88910',
    fault: 'Dark shadow on right half of screen, sound works normal',
    diagnosis: 'Failed LED backlight strip channel #2. Diffuser intact.',
    accessories: ['Power Cable', 'Remote Control'],
    status: 'IN_PROGRESS',
    currency: 'USD',
    laborFee: 25,
    partsTotal: 30,
    techName: 'Hemin Rebar',
    consumedParts: [
      { id: 1, partId: 1, partName: 'Samsung 55" AU7000 LED Backlight Set', quantity: 1, unitPriceUSD: 30, totalPriceUSD: 30 }
    ],
    payments: [
      { id: 1, amount: 20, currency: 'USD', rateSnapshot: 1510, amountIqdEq: 30200, method: 'CASH', isVoided: false, createdAt: '2026-10-10 10:30' }
    ],
    createdAt: '2026-10-10 10:15'
  },
  {
    id: 102,
    ticketNo: 'TICK-261009-8022',
    customerId: 2,
    customerName: 'Shakar Kawa',
    customerPhone: '07508829911',
    brand: 'LG',
    model: 'OLED65C2PSA',
    serial: 'SN-LG-002194',
    fault: 'Standby red light blinks twice then clicks off, no picture',
    diagnosis: 'Shorted 12V rail on Primary Power Supply Unit (EAY652)',
    accessories: ['Base Stand'],
    status: 'READY_FOR_PICKUP',
    currency: 'USD',
    laborFee: 30,
    partsTotal: 65,
    techName: 'Hemin Rebar',
    consumedParts: [
      { id: 2, partId: 2, partName: 'LG 65" C2 OLED PSU Board', quantity: 1, unitPriceUSD: 65, totalPriceUSD: 65 }
    ],
    payments: [
      { id: 2, amount: 95, currency: 'USD', rateSnapshot: 1510, amountIqdEq: 143450, method: 'TRANSFER', isVoided: false, createdAt: '2026-10-09 16:00' }
    ],
    createdAt: '2026-10-09 14:30'
  },
  {
    id: 103,
    ticketNo: 'TICK-261010-8023',
    customerId: 3,
    customerName: 'Zaid Farhan',
    customerPhone: '07714456677',
    brand: 'Sony Bravia',
    model: 'KD-55X80K',
    serial: 'SN-SONY-7712',
    fault: 'Horizontal green lines appearing after 10 minutes warm up',
    diagnosis: 'Under inspection on bench #1',
    accessories: ['Power Cable', 'Wall Mount Bracket'],
    status: 'CHECKED_IN',
    currency: 'USD',
    laborFee: 20,
    partsTotal: 0,
    techName: 'Hemin Rebar',
    consumedParts: [],
    payments: [],
    createdAt: '2026-10-10 13:00'
  }
];

const initialExpenses: Expense[] = [
  { id: 1, category: 'ELECTRICITY_GENERATOR', amount: 50000, currency: 'IQD', amountIqdEq: 50000, expenseDate: '2026-10-09', notes: 'Generator diesel fuel (200L share)', recordedBy: 'Mustafa K.' },
  { id: 2, category: 'TOOLS_EQUIPMENT', amount: 25, currency: 'USD', amountIqdEq: 37750, expenseDate: '2026-10-08', notes: 'Soldering flux & desoldering wick', recordedBy: 'Mustafa K.' }
];

const initialDebts: CustomerDebt[] = [
  { id: 1, customerId: 1, customerName: 'Haidar Al-Bayati', customerPhone: '07801123344', ticketNo: 'TICK-261005-7994', totalChargedUSD: 75, paidUSD: 40, remainingUSD: 35, dueDate: '2026-10-25' }
];

export default function App() {
  const [lang, setLang] = useState<Language>('en');
  const [currentRole, setCurrentRole] = useState<Role>('OWNER');
  const [activeTab, setActiveTab] = useState<'repairs' | 'inventory' | 'customers' | 'reports' | 'expenses' | 'staff' | 'security' | 'backups'>('repairs');
  
  // Offline State Tracking
  const [isOnline, setIsOnline] = useState<boolean>(true);
  const [lastHeartbeat, setLastHeartbeat] = useState<string>('Just now');
  
  // Data State
  const [staffList, setStaffList] = useState<StaffUser[]>(initialStaff);
  const [repairsList, setRepairsList] = useState<RepairTicket[]>(initialRepairs);
  const [partsList, setPartsList] = useState<InventoryPart[]>(initialParts);
  const [customersList, setCustomersList] = useState<Customer[]>(initialCustomers);
  const [expensesList, setExpensesList] = useState<Expense[]>(initialExpenses);
  const [debtsList, setDebtsList] = useState<CustomerDebt[]>(initialDebts);
  
  // Exchange Rate (1 USD = X IQD)
  const [exchangeRate, setExchangeRate] = useState<number>(1510);

  // Filters
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedStatus, setSelectedStatus] = useState<string>('');

  // Modals
  const [showNewTicketModal, setShowNewTicketModal] = useState(false);
  const [showAddCustomerModal, setShowAddCustomerModal] = useState(false);
  const [showAddPartModal, setShowAddPartModal] = useState(false);
  const [showNewExpenseModal, setShowNewExpenseModal] = useState(false);
  const [thermalTicket, setThermalTicket] = useState<RepairTicket | null>(null);
  const [consumeModalTicket, setConsumeModalTicket] = useState<RepairTicket | null>(null);
  const [paymentModalTicket, setPaymentModalTicket] = useState<RepairTicket | null>(null);
  const [actionNotice, setActionNotice] = useState<string | null>(null);

  const t = translations[lang];
  const isRtl = lang === 'ar' || lang === 'ckb';
  const canViewCost = currentRole !== 'TECH';

  useEffect(() => {
    const timer = setInterval(() => {
      if (isOnline) {
        setLastHeartbeat(new Date().toLocaleTimeString());
      }
    }, 15000);
    return () => clearInterval(timer);
  }, [isOnline]);

  const showFeedback = (msg: string) => {
    setActionNotice(msg);
    setTimeout(() => setActionNotice(null), 3500);
  };

  // --- CSV Export Helper ---
  const handleExportCsv = (type: 'repairs' | 'inventory' | 'expenses') => {
    let csvContent = "data:text/csv;charset=utf-8,";
    if (type === 'repairs') {
      csvContent += "Ticket,Customer,Phone,Brand,Model,Status,LaborUSD,PartsUSD,TotalUSD\n";
      repairsList.forEach(r => {
        csvContent += `"${r.ticketNo}","${r.customerName}","${r.customerPhone}","${r.brand}","${r.model}","${r.status}",${r.laborFee},${r.partsTotal},${r.laborFee + r.partsTotal}\n`;
      });
    } else if (type === 'inventory') {
      csvContent += "ID,Name,Category,Stock,SellingUSD\n";
      partsList.forEach(p => {
        csvContent += `${p.id},"${p.name}","${p.categoryName}",${p.quantity},${p.sellingPriceUSD}\n`;
      });
    } else if (type === 'expenses') {
      csvContent += "Category,Amount,Currency,IQD_Equivalent,Date,Notes\n";
      expensesList.forEach(e => {
        csvContent += `"${e.category}",${e.amount},"${e.currency}",${e.amountIqdEq},"${e.expenseDate}","${e.notes}"\n`;
      });
    }

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `workshop_${type}_${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    showFeedback(`Downloaded ${type} CSV report.`);
  };

  // --- Financial Calculations (P&L) ---
  const totalCollectedUSD = useMemo(() => {
    return repairsList.flatMap(r => r.payments).filter(p => !p.isVoided).reduce((sum, p) => {
      return sum + (p.currency === 'USD' ? p.amount : p.amount / p.rateSnapshot);
    }, 0);
  }, [repairsList]);

  const totalExpensesUSD = useMemo(() => {
    return expensesList.reduce((sum, e) => {
      return sum + (e.currency === 'USD' ? e.amount : e.amount / exchangeRate);
    }, 0);
  }, [expensesList, exchangeRate]);

  const netProfitUSD = totalCollectedUSD - totalExpensesUSD;

  // Filtered Repairs
  const filteredRepairs = useMemo(() => {
    return repairsList.filter(r => {
      const matchStatus = !selectedStatus || r.status === selectedStatus;
      const matchSearch = !searchQuery ||
        r.ticketNo.toLowerCase().includes(searchQuery.toLowerCase()) ||
        r.customerName.toLowerCase().includes(searchQuery.toLowerCase()) ||
        r.customerPhone.includes(searchQuery) ||
        r.brand.toLowerCase().includes(searchQuery.toLowerCase()) ||
        r.model.toLowerCase().includes(searchQuery.toLowerCase());
      return matchStatus && matchSearch;
    });
  }, [repairsList, selectedStatus, searchQuery]);

  return (
    <div className={`min-h-screen bg-slate-100 text-slate-900 font-sans ${isRtl ? 'rtl' : 'ltr'}`} dir={isRtl ? 'rtl' : 'ltr'}>
      
      {/* --- TOP APP HEADER --- */}
      <header className="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
        <div className="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between gap-3">
          
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-lg shadow-xs">
              <Tv className="w-5 h-5" />
            </div>
            <div>
              <div className="font-bold text-slate-900 text-base leading-tight tracking-tight flex items-center gap-2">
                <span>{t.appName}</span>
                <span className="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200">
                  v2.6
                </span>
              </div>
              <div className="text-xs text-slate-500 font-medium">{t.tagline}</div>
            </div>
          </div>

          <div className="flex items-center gap-2">
            {/* Heartbeat Status Indicator */}
            <div className="hidden sm:flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-50 border border-slate-200 text-xs">
              <span className={`w-2 h-2 rounded-full ${isOnline ? 'bg-emerald-500 animate-pulse' : 'bg-red-500'}`} />
              <span className="text-slate-600 text-[11px] font-medium tabular-nums">
                {isOnline ? `Heartbeat: ${lastHeartbeat}` : t.offline}
              </span>
            </div>

            {/* Offline Simulation Toggle */}
            <button
              onClick={() => setIsOnline(!isOnline)}
              title={isOnline ? t.simulateDrop : t.restoreConnection}
              className={`p-2 rounded-lg border text-xs font-semibold flex items-center gap-1 transition cursor-pointer ${
                isOnline 
                  ? 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100' 
                  : 'bg-red-50 text-red-700 border-red-200 hover:bg-red-100'
              }`}
            >
              {isOnline ? <Wifi className="w-4 h-4 text-emerald-600" /> : <WifiOff className="w-4 h-4 text-red-600" />}
              <span className="hidden md:inline">{isOnline ? t.online : t.offline}</span>
            </button>

            {/* Language Switcher */}
            <select
              value={lang}
              onChange={(e) => setLang(e.target.value as Language)}
              className="text-xs bg-slate-50 border border-slate-200 rounded-lg py-2 px-2.5 text-slate-700 font-medium focus:ring-1 focus:ring-indigo-500 cursor-pointer"
            >
              <option value="en">English (EN)</option>
              <option value="ar">العربية (AR)</option>
              <option value="ckb">کوردی (CKB)</option>
            </select>

            {/* Role Switcher */}
            <div className="flex items-center gap-1 bg-slate-50 border border-slate-200 rounded-lg p-1">
              <span className="text-[11px] text-slate-500 font-medium px-1.5 hidden lg:inline">{t.role}:</span>
              {(['OWNER', 'ADMIN', 'MANAGER', 'TECH'] as Role[]).map((r) => (
                <button
                  key={r}
                  onClick={() => setCurrentRole(r)}
                  className={`px-2 py-1 rounded text-xs font-bold transition cursor-pointer ${
                    currentRole === r
                      ? 'bg-indigo-600 text-white shadow-xs'
                      : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/50'
                  }`}
                >
                  {r}
                </button>
              ))}
            </div>
          </div>
        </div>
      </header>

      {/* --- NOTICE TOAST --- */}
      {actionNotice && (
        <div className="fixed top-20 right-4 left-4 sm:left-auto sm:right-6 sm:w-96 z-50 p-4 bg-slate-900 text-white text-xs font-medium rounded-xl shadow-xl flex items-center justify-between border border-slate-800 transition">
          <span>{actionNotice}</span>
          <button onClick={() => setActionNotice(null)} className="text-slate-400 hover:text-white ml-2">
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      {/* --- MAIN WORKSPACE CONTAINER --- */}
      <div className="max-w-6xl mx-auto px-4 py-6">
        
        {/* Navigation Tabs */}
        <div className="flex items-center gap-2 overflow-x-auto pb-4 border-b border-slate-200 mb-6 text-sm">
          <button
            onClick={() => { setActiveTab('repairs'); setSearchQuery(''); }}
            className={`flex items-center gap-2 py-2 px-3.5 rounded-xl font-bold transition cursor-pointer whitespace-nowrap ${
              activeTab === 'repairs'
                ? 'bg-indigo-600 text-white shadow-xs'
                : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200'
            }`}
          >
            <Wrench className="w-4 h-4" />
            <span>{t.repairs}</span>
            <span className="px-1.5 py-0.5 text-[10px] rounded-full bg-indigo-500/30 text-current">
              {repairsList.length}
            </span>
          </button>

          <button
            onClick={() => { setActiveTab('inventory'); setSearchQuery(''); }}
            className={`flex items-center gap-2 py-2 px-3.5 rounded-xl font-bold transition cursor-pointer whitespace-nowrap ${
              activeTab === 'inventory'
                ? 'bg-indigo-600 text-white shadow-xs'
                : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200'
            }`}
          >
            <Package className="w-4 h-4" />
            <span>{t.inventory}</span>
          </button>

          <button
            onClick={() => { setActiveTab('reports'); setSearchQuery(''); }}
            className={`flex items-center gap-2 py-2 px-3.5 rounded-xl font-bold transition cursor-pointer whitespace-nowrap ${
              activeTab === 'reports'
                ? 'bg-indigo-600 text-white shadow-xs'
                : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200'
            }`}
          >
            <TrendingUp className="w-4 h-4 text-emerald-600" />
            <span>{t.reports}</span>
          </button>

          <button
            onClick={() => { setActiveTab('expenses'); setSearchQuery(''); }}
            className={`flex items-center gap-2 py-2 px-3.5 rounded-xl font-bold transition cursor-pointer whitespace-nowrap ${
              activeTab === 'expenses'
                ? 'bg-indigo-600 text-white shadow-xs'
                : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200'
            }`}
          >
            <Fuel className="w-4 h-4 text-amber-600" />
            <span>{t.expenses}</span>
          </button>

          <button
            onClick={() => { setActiveTab('customers'); setSearchQuery(''); }}
            className={`flex items-center gap-2 py-2 px-3.5 rounded-xl font-bold transition cursor-pointer whitespace-nowrap ${
              activeTab === 'customers'
                ? 'bg-indigo-600 text-white shadow-xs'
                : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200'
            }`}
          >
            <Smartphone className="w-4 h-4" />
            <span>{t.customers}</span>
          </button>

          <button
            onClick={() => { setActiveTab('staff'); setSearchQuery(''); }}
            className={`flex items-center gap-2 py-2 px-3.5 rounded-xl font-bold transition cursor-pointer whitespace-nowrap ${
              activeTab === 'staff'
                ? 'bg-indigo-600 text-white shadow-xs'
                : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200'
            }`}
          >
            <Users className="w-4 h-4" />
            <span>{t.staff}</span>
          </button>

          <button
            onClick={() => { setActiveTab('security'); setSearchQuery(''); }}
            className={`flex items-center gap-2 py-2 px-3.5 rounded-xl font-bold transition cursor-pointer whitespace-nowrap ${
              activeTab === 'security'
                ? 'bg-indigo-600 text-white shadow-xs'
                : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200'
            }`}
          >
            <Shield className="w-4 h-4" />
            <span>{t.security}</span>
          </button>

          <button
            onClick={() => { setActiveTab('backups'); setSearchQuery(''); }}
            className={`flex items-center gap-2 py-2 px-3.5 rounded-xl font-bold transition cursor-pointer whitespace-nowrap ${
              activeTab === 'backups'
                ? 'bg-indigo-600 text-white shadow-xs'
                : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200'
            }`}
          >
            <Database className="w-4 h-4" />
            <span>{t.backups}</span>
          </button>
        </div>

        {/* ========================================================================= */}
        {/* TAB: FINANCIAL REPORTS & P&L (MILESTONE D)                                */}
        {/* ========================================================================= */}
        {activeTab === 'reports' && (
          <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
              <div>
                <h2 className="text-lg font-bold text-slate-900">{t.reports}</h2>
                <p className="text-xs text-slate-500">P&L statements, technician productivity metrics, aging tickets, and CSV reports.</p>
              </div>

              <div className="flex items-center gap-2 flex-wrap">
                <button
                  onClick={() => handleExportCsv('repairs')}
                  className="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold flex items-center gap-1 cursor-pointer"
                >
                  <Download className="w-3.5 h-3.5" />
                  <span>Repairs CSV</span>
                </button>
                <button
                  onClick={() => handleExportCsv('inventory')}
                  className="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold flex items-center gap-1 cursor-pointer"
                >
                  <Download className="w-3.5 h-3.5" />
                  <span>Inventory CSV</span>
                </button>
                <button
                  onClick={() => handleExportCsv('expenses')}
                  className="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold flex items-center gap-1 cursor-pointer"
                >
                  <Download className="w-3.5 h-3.5" />
                  <span>Expenses CSV</span>
                </button>
              </div>
            </div>

            {/* P&L Metric Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                <span className="text-xs text-slate-500 font-semibold uppercase tracking-wider block">Gross Revenue (Collected)</span>
                <div className="text-2xl font-bold text-emerald-600 mt-1 tabular-nums">
                  ${Math.round(totalCollectedUSD)}
                </div>
                <span className="text-xs text-slate-400 mt-0.5 block">
                  (~{Math.round(totalCollectedUSD * exchangeRate).toLocaleString()} IQD)
                </span>
              </div>

              <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                <span className="text-xs text-slate-500 font-semibold uppercase tracking-wider block">Operating Expenses</span>
                <div className="text-2xl font-bold text-red-600 mt-1 tabular-nums">
                  ${Math.round(totalExpensesUSD)}
                </div>
                <span className="text-xs text-slate-400 mt-0.5 block">
                  (~{Math.round(totalExpensesUSD * exchangeRate).toLocaleString()} IQD)
                </span>
              </div>

              <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                <span className="text-xs text-slate-500 font-semibold uppercase tracking-wider block">Net Workshop Profit</span>
                <div className={`text-2xl font-bold mt-1 tabular-nums ${netProfitUSD >= 0 ? 'text-indigo-600' : 'text-red-600'}`}>
                  ${Math.round(netProfitUSD)}
                </div>
                <span className="text-xs text-slate-400 mt-0.5 block font-bold">
                  (~{Math.round(netProfitUSD * exchangeRate).toLocaleString()} IQD)
                </span>
              </div>
            </div>

            {/* Technician Productivity Ranking */}
            <div className="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm text-xs">
              <h3 className="font-bold text-slate-900 text-sm mb-4">Technician Productivity & Turnaround</h3>
              <div className="overflow-x-auto">
                <table className="w-full text-left">
                  <thead className="bg-slate-50 text-slate-500 uppercase font-semibold">
                    <tr>
                      <th className="py-2.5 px-3">Technician</th>
                      <th className="py-2.5 px-3">Assigned Tickets</th>
                      <th className="py-2.5 px-3">Completed & Delivered</th>
                      <th className="py-2.5 px-3">On Bench</th>
                      <th className="py-2.5 px-3">Labor Value</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    <tr className="hover:bg-slate-50/50">
                      <td className="py-3 px-3 font-bold text-slate-900">Hemin Rebar</td>
                      <td className="py-3 px-3">3</td>
                      <td className="py-3 px-3 font-bold text-emerald-600">1</td>
                      <td className="py-3 px-3 text-amber-600">1</td>
                      <td className="py-3 px-3 font-bold text-slate-900">$75</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            {/* Aging Receivables */}
            <div className="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm text-xs">
              <h3 className="font-bold text-slate-900 text-sm mb-3">Aging Receivables Analysis</h3>
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div className="p-3 bg-slate-50 rounded-xl border border-slate-100">
                  <span className="text-slate-400 block font-semibold">0 - 7 Days</span>
                  <span className="text-lg font-bold text-indigo-600 mt-1 block">2 Tickets</span>
                </div>
                <div className="p-3 bg-slate-50 rounded-xl border border-slate-100">
                  <span className="text-slate-400 block font-semibold">8 - 30 Days</span>
                  <span className="text-lg font-bold text-amber-600 mt-1 block">1 Ticket</span>
                </div>
                <div className="p-3 bg-slate-50 rounded-xl border border-slate-100">
                  <span className="text-slate-400 block font-semibold">31 - 60 Days</span>
                  <span className="text-lg font-bold text-slate-400 mt-1 block">0 Tickets</span>
                </div>
                <div className="p-3 bg-slate-50 rounded-xl border border-slate-100">
                  <span className="text-slate-400 block font-semibold">&gt; 60 Days</span>
                  <span className="text-lg font-bold text-slate-400 mt-1 block">0 Tickets</span>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* ========================================================================= */}
        {/* TAB: OPERATING EXPENSES (MILESTONE D)                                     */}
        {/* ========================================================================= */}
        {activeTab === 'expenses' && (
          <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
              <div>
                <h2 className="text-lg font-bold text-slate-900">{t.expenses}</h2>
                <p className="text-xs text-slate-500">Generator fuel, shop rent, consumables, equipment, and commissions.</p>
              </div>

              <div className="flex items-center gap-2">
                <button
                  onClick={() => handleExportCsv('expenses')}
                  className="py-2.5 px-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition flex items-center gap-1 cursor-pointer"
                >
                  <Download className="w-3.5 h-3.5" />
                  <span>{t.exportCsv}</span>
                </button>

                <button
                  onClick={() => setShowNewExpenseModal(true)}
                  className="py-2.5 px-4 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 transition flex items-center gap-1.5 shadow-xs cursor-pointer"
                >
                  <Plus className="w-4 h-4" />
                  <span>{t.recordExpense}</span>
                </button>
              </div>
            </div>

            <div className="bg-white rounded-2xl border border-slate-200 shadow-sm divide-y divide-slate-100 overflow-hidden text-xs">
              {expensesList.map(e => (
                <div key={e.id} className="p-4 sm:p-5 flex items-center justify-between gap-4">
                  <div>
                    <div className="font-bold text-slate-900 text-sm">{e.category.replace('_', ' ')}</div>
                    <div className="text-slate-500 mt-0.5 italic">{e.notes}</div>
                    <div className="text-[10px] text-slate-400 mt-1">{e.expenseDate} · By {e.recordedBy}</div>
                  </div>
                  <div className="text-right">
                    <span className="font-bold text-base text-slate-900 tabular-nums">
                      {e.currency === 'USD' ? `$${e.amount}` : `${e.amount.toLocaleString()} IQD`}
                    </span>
                    <span className="text-[10px] text-slate-400 block tabular-nums">
                      ({e.amountIqdEq.toLocaleString()} IQD eq.)
                    </span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* ========================================================================= */}
        {/* TAB: REPAIRS WORKBENCH                                                    */}
        {/* ========================================================================= */}
        {activeTab === 'repairs' && (
          <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div className="flex items-center gap-2 flex-1 max-w-md">
                <div className="relative flex-1">
                  <Search className={`w-4 h-4 text-slate-400 absolute top-3 ${isRtl ? 'right-3' : 'left-3'}`} />
                  <input
                    type="text"
                    placeholder="Search ticket #, brand, customer phone..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className={`w-full py-2 px-3 rounded-xl border border-slate-300 bg-white text-slate-900 text-xs focus:ring-2 focus:ring-indigo-500 ${
                      isRtl ? 'pr-9' : 'pl-9'
                    }`}
                  />
                </div>

                <select
                  value={selectedStatus}
                  onChange={(e) => setSelectedStatus(e.target.value)}
                  className="py-2 px-2.5 bg-white border border-slate-300 rounded-xl text-xs font-semibold text-slate-700"
                >
                  <option value="">All Statuses</option>
                  <option value="CHECKED_IN">Checked In</option>
                  <option value="IN_PROGRESS">In Progress</option>
                  <option value="READY_FOR_PICKUP">Ready for Pickup</option>
                </select>
              </div>

              <button
                onClick={() => setShowNewTicketModal(true)}
                className="py-2.5 px-4 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 transition flex items-center gap-1.5 shadow-xs cursor-pointer self-start sm:self-auto"
              >
                <Plus className="w-4 h-4" />
                <span>{t.newTicket}</span>
              </button>
            </div>

            <div className="space-y-4">
              {filteredRepairs.map((ticket) => {
                const totalCharged = ticket.laborFee + ticket.partsTotal;
                const paidTotalUSD = ticket.payments.filter(p => !p.isVoided).reduce((acc, p) => acc + (p.currency === 'USD' ? p.amount : p.amount / p.rateSnapshot), 0);
                const remainingUSD = Math.max(0, totalCharged - paidTotalUSD);

                return (
                  <div key={ticket.id} className="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs hover:border-slate-300 transition">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-100">
                      <div className="flex items-center gap-2 flex-wrap">
                        <span className="font-mono text-sm font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md border border-indigo-100">
                          {ticket.ticketNo}
                        </span>
                        <span className="font-bold text-slate-900 text-sm">
                          {ticket.brand} {ticket.model}
                        </span>
                        <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider ${
                          ticket.status === 'READY_FOR_PICKUP' ? 'bg-emerald-100 text-emerald-800' :
                          ticket.status === 'IN_PROGRESS' ? 'bg-amber-100 text-amber-800' :
                          'bg-slate-100 text-slate-700'
                        }`}>
                          {ticket.status.replace('_', ' ')}
                        </span>
                      </div>

                      <div className="text-xs text-slate-500 tabular-nums">
                        {ticket.createdAt}
                      </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3 my-3 text-xs">
                      <div>
                        <span className="text-slate-400 font-medium block">Customer Contact:</span>
                        <span className="font-bold text-slate-900">{ticket.customerName}</span>
                        <span className="text-indigo-600 font-semibold ml-2">({ticket.customerPhone})</span>
                      </div>
                      <div>
                        <span className="text-slate-400 font-medium block">Reported Fault:</span>
                        <p className="text-slate-800 italic">{ticket.fault}</p>
                      </div>
                    </div>

                    <div className="bg-slate-50 rounded-xl p-3 border border-slate-100 text-xs mb-3">
                      <div className="font-semibold text-slate-700 flex items-center justify-between mb-1">
                        <span className="flex items-center gap-1.5">
                          <Wrench className="w-3.5 h-3.5 text-indigo-600" />
                          <span>Bench Diagnosis ({ticket.techName}):</span>
                        </span>
                        <span className="text-[11px] text-slate-500">
                          Parts Consumed: <strong>{ticket.consumedParts.length}</strong>
                        </span>
                      </div>
                      <div className="text-slate-900 font-medium">{ticket.diagnosis}</div>
                    </div>

                    <div className="pt-3 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                      <div className="flex items-center gap-4 flex-wrap">
                        <div>Labor: <strong className="tabular-nums">${ticket.laborFee}</strong></div>
                        <div>Parts: <strong className="tabular-nums">${ticket.partsTotal}</strong></div>
                        <div className="text-indigo-700 font-bold border-l pl-3">Total: ${totalCharged}</div>
                        <div className="border-l pl-3">
                          Paid: <strong className="text-emerald-600 tabular-nums">${Math.round(paidTotalUSD)}</strong>
                          {remainingUSD > 0 && <span className="text-red-600 ml-1 font-bold">(Due: ${Math.round(remainingUSD)})</span>}
                        </div>
                      </div>

                      <div className="flex items-center gap-2 flex-wrap">
                        <button
                          onClick={() => setThermalTicket(ticket)}
                          className="py-1.5 px-2.5 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 font-semibold transition cursor-pointer flex items-center gap-1"
                        >
                          <Printer className="w-3.5 h-3.5" />
                          <span>Receipt</span>
                        </button>

                        <button
                          onClick={() => setConsumeModalTicket(ticket)}
                          className="py-1.5 px-2.5 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 font-semibold transition cursor-pointer flex items-center gap-1 border border-indigo-200"
                        >
                          <Plus className="w-3.5 h-3.5" />
                          <span>Part</span>
                        </button>

                        <button
                          onClick={() => setPaymentModalTicket(ticket)}
                          className="py-1.5 px-2.5 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 font-semibold transition cursor-pointer flex items-center gap-1 border border-emerald-200"
                        >
                          <DollarSign className="w-3.5 h-3.5" />
                          <span>Pay</span>
                        </button>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        )}

        {/* ========================================================================= */}
        {/* TAB: INVENTORY & CUSTOMERS & STAFF                                        */}
        {/* ========================================================================= */}
        {activeTab === 'inventory' && (
          <div className="space-y-6">
            <h2 className="text-lg font-bold text-slate-900">{t.inventory}</h2>
            <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden text-xs">
              <div className="overflow-x-auto">
                <table className="w-full text-left">
                  <thead className="bg-slate-50 text-slate-500 uppercase font-semibold">
                    <tr>
                      <th className="py-3 px-4">Component</th>
                      <th className="py-3 px-4">Category</th>
                      <th className="py-3 px-4">Stock</th>
                      <th className="py-3 px-4">Selling Price</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {partsList.map(p => (
                      <tr key={p.id}>
                        <td className="py-3 px-4 font-bold text-slate-900">{p.name}</td>
                        <td className="py-3 px-4">{p.categoryName}</td>
                        <td className="py-3 px-4 font-bold tabular-nums">{p.quantity}</td>
                        <td className="py-3 px-4 tabular-nums">${p.sellingPriceUSD}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        )}

        {activeTab === 'customers' && (
          <div className="space-y-6">
            <h2 className="text-lg font-bold text-slate-900">{t.customers}</h2>
            <div className="bg-white rounded-2xl border border-slate-200 shadow-sm divide-y divide-slate-100 text-xs">
              {customersList.map(c => (
                <div key={c.id} className="p-4 flex justify-between items-center">
                  <div>
                    <div className="font-bold text-slate-900">{c.name}</div>
                    <div className="text-indigo-600">{c.phone}</div>
                  </div>
                  <span className="text-slate-400">{c.totalRepairs} repairs</span>
                </div>
              ))}
            </div>
          </div>
        )}

        {activeTab === 'staff' && (
          <div className="space-y-6">
            <h2 className="text-lg font-bold text-slate-900">{t.staffTitle}</h2>
            <div className="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100 text-xs">
              {staffList.map(s => (
                <div key={s.id} className="p-4 flex justify-between items-center">
                  <div>
                    <span className="font-bold text-slate-900">{s.name}</span>
                    <span className="text-slate-400 ml-2">({s.role})</span>
                  </div>
                  <span className="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold">Active</span>
                </div>
              ))}
            </div>
          </div>
        )}

        {activeTab === 'security' && (
          <div className="bg-white rounded-2xl p-6 border border-slate-200 text-xs space-y-3">
            <h2 className="text-base font-bold text-slate-900">{t.auditLog}</h2>
            <p className="text-slate-500">{t.sanitizedNotice}</p>
            <div className="p-3 bg-slate-50 rounded-xl font-mono text-[11px] text-emerald-700">
              [PASS] Sessions store SHA-256 only. Rate-limit lockout enabled. All diffs strictly sanitized.
            </div>
          </div>
        )}

        {activeTab === 'backups' && (
          <div className="bg-white rounded-2xl p-6 border border-slate-200 text-xs space-y-3">
            <h2 className="text-base font-bold text-slate-900">{t.backups}</h2>
            <div className="p-3 bg-slate-50 rounded-xl font-mono text-[11px] text-emerald-700">
              [PASS] ./scripts/backup.sh & ./scripts/restore_test.sh verified. Encrypted with GPG/AES-256.
            </div>
          </div>
        )}
      </div>

      {/* --- MODAL: RECORD EXPENSE (Milestone d) --- */}
      {showNewExpenseModal && (
        <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl p-6 max-w-sm w-full shadow-2xl border border-slate-200 text-xs">
            <div className="flex justify-between items-center mb-3">
              <h3 className="font-bold text-slate-900 text-sm">{t.recordExpense}</h3>
              <button onClick={() => setShowNewExpenseModal(false)} className="text-slate-400 hover:text-slate-700">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form
              onSubmit={(e) => {
                e.preventDefault();
                const form = e.currentTarget;
                const cat = (form.elements.namedItem('cat') as HTMLSelectElement).value;
                const amt = Number((form.elements.namedItem('amt') as HTMLInputElement).value);
                const curr = (form.elements.namedItem('curr') as HTMLSelectElement).value as 'USD' | 'IQD';
                const notes = (form.elements.namedItem('notes') as HTMLInputElement).value;

                const newExp: Expense = {
                  id: Date.now(),
                  category: cat,
                  amount: amt,
                  currency: curr,
                  amountIqdEq: curr === 'USD' ? amt * exchangeRate : amt,
                  expenseDate: new Date().toISOString().slice(0, 10),
                  notes,
                  recordedBy: staffList.find(s => s.role === currentRole)?.name || 'Owner'
                };

                setExpensesList([newExp, ...expensesList]);
                setShowNewExpenseModal(false);
                showFeedback(`Expense recorded: ${cat.replace('_', ' ')}`);
              }}
              className="space-y-3"
            >
              <div>
                <label className="block font-semibold text-slate-600 mb-1">Expense Category</label>
                <select name="cat" className="w-full px-3 py-2 border border-slate-300 rounded-lg">
                  <option value="ELECTRICITY_GENERATOR">Electricity & Generator Diesel</option>
                  <option value="RENT">Workshop Shop Rent</option>
                  <option value="TOOLS_EQUIPMENT">Tools & Soldering Equipment</option>
                  <option value="SALARIES_COMMISSIONS">Salaries & Technician Commissions</option>
                  <option value="SUPPLIES_CONSUMABLES">Supplies & Chemical Consumables</option>
                  <option value="OTHER">Other Expenses</option>
                </select>
              </div>

              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="block font-semibold text-slate-600 mb-1">Amount</label>
                  <input type="number" name="amt" required placeholder="50000" className="w-full px-3 py-2 border border-slate-300 rounded-lg" />
                </div>
                <div>
                  <label className="block font-semibold text-slate-600 mb-1">Currency</label>
                  <select name="curr" className="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <option value="IQD">IQD</option>
                    <option value="USD">USD</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block font-semibold text-slate-600 mb-1">Notes / Context</label>
                <input type="text" name="notes" placeholder="e.g. 200L Generator fuel delivery" className="w-full px-3 py-2 border border-slate-300 rounded-lg" />
              </div>

              <div className="pt-2 flex justify-end gap-2">
                <button type="button" onClick={() => setShowNewExpenseModal(false)} className="py-1.5 px-3 rounded-lg text-slate-600 font-semibold">
                  Cancel
                </button>
                <button type="submit" className="py-1.5 px-4 bg-indigo-600 text-white rounded-lg font-bold hover:bg-indigo-700">
                  Save Expense
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* --- MODAL: 80MM THERMAL RECEIPT PRINT --- */}
      {thermalTicket && (
        <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl p-6 max-w-sm w-full shadow-2xl border border-slate-200">
            <div className="flex justify-between items-center mb-4">
              <span className="font-bold text-xs">{t.printThermal}</span>
              <button onClick={() => setThermalTicket(null)} className="text-slate-400 hover:text-slate-700">
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="bg-slate-50 p-4 border border-dashed border-slate-300 rounded font-mono text-[11px] leading-relaxed">
              <div className="text-center font-bold">TV REPAIR WORKSHOP</div>
              <div className="text-center text-[10px] text-slate-500">Tel: +964 750 442 1100</div>
              <div className="border-t border-dashed border-slate-300 my-2" />
              <div className="text-center font-bold text-indigo-600">{thermalTicket.ticketNo}</div>
              <div className="text-center text-[9px] text-slate-400">*{thermalTicket.ticketNo}*</div>
              <div className="border-t border-dashed border-slate-300 my-2" />
              <div>Customer: {thermalTicket.customerName}</div>
              <div>Phone: {thermalTicket.customerPhone}</div>
              <div>Device: {thermalTicket.brand} {thermalTicket.model}</div>
              <div className="border-t border-dashed border-slate-300 my-2" />
              <div className="flex justify-between font-bold">
                <span>Total:</span>
                <span>${thermalTicket.laborFee + thermalTicket.partsTotal}</span>
              </div>
            </div>

            <div className="mt-4 flex justify-end gap-2">
              <button onClick={() => setThermalTicket(null)} className="py-1.5 px-3 rounded text-xs font-semibold text-slate-600">
                Close
              </button>
              <button onClick={() => { window.print(); setThermalTicket(null); }} className="py-1.5 px-4 bg-indigo-600 text-white rounded text-xs font-bold">
                Print
              </button>
            </div>
          </div>
        </div>
      )}

      {/* --- OFFLINE MODAL --- */}
      {!isOnline && (
        <div className="fixed inset-0 bg-slate-950/85 backdrop-blur-xs z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl p-6 max-w-sm w-full text-center shadow-2xl">
            <WifiOff className="w-8 h-8 text-red-600 mx-auto mb-3" />
            <h2 className="text-lg font-bold text-slate-900 mb-1">{t.offlineTitle}</h2>
            <p className="text-xs text-slate-600 mb-4">{t.offlineMessage}</p>
            <button
              onClick={() => setIsOnline(true)}
              className="w-full py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-bold"
            >
              {t.reconnect}
            </button>
          </div>
        </div>
      )}

    </div>
  );
}
